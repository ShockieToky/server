<?php

namespace App\Controller\Api;

use App\Entity\DungeonAutoSession;
use App\Entity\User;
use App\Entity\UserInventory;
use App\Repository\DungeonAutoSessionRepository;
use App\Repository\DungeonRepository;
use App\Repository\UserDungeonProgressRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Auto-ferme des donjons.
 *
 * POST /api/dungeon/{id}/auto/start  → démarrer une session
 * GET  /api/dungeon/auto/status      → session active/réclamable du joueur
 * POST /api/dungeon/auto/{sid}/claim → réclamer les récompenses
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DungeonAutoController extends AbstractController
{
    public function __construct(
        private readonly DungeonRepository             $dungeonRepository,
        private readonly UserDungeonProgressRepository $progressRepository,
        private readonly DungeonAutoSessionRepository  $sessionRepository,
        private readonly UserInventoryRepository       $inventoryRepository,
        private readonly EntityManagerInterface        $em,
    ) {}

    // ── Start ─────────────────────────────────────────────────────────────────

    #[Route('/api/dungeon/{id}/auto/start', name: 'api_dungeon_auto_start', methods: ['POST'])]
    public function start(int $id, Request $request): JsonResponse
    {
        $dungeon = $this->dungeonRepository->find($id);
        if (!$dungeon || !$dungeon->isActive()) {
            return $this->json(['message' => 'Donjon introuvable'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());

        // Vérifier qu'aucune session n'est déjà en cours (non réclamée)
        $existing = $this->sessionRepository->findActiveForUser($user);
        if ($existing !== null && !$existing->hasEnded()) {
            return $this->json(['message' => 'Une session est déjà en cours'], Response::HTTP_CONFLICT);
        }

        // Vérifier que le donjon est débloqué (au moins 1 run manuel réussi)
        $progress = $this->progressRepository->findOneByUserAndDungeon($user, $dungeon);
        if ($progress === null || !$progress->hasCompleted()) {
            return $this->json(['message' => 'Terminez ce donjon manuellement au moins une fois pour débloquer l\'auto-ferme'], Response::HTTP_FORBIDDEN);
        }

        $bestTurnCount = $progress->getBestTurnCount();
        if ($bestTurnCount === null || $bestTurnCount <= 0) {
            return $this->json(['message' => 'Aucun score de référence disponible'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Durée en minutes → secondes
        $body            = json_decode($request->getContent(), true) ?? [];
        $durationMinutes = (int) ($body['durationMinutes'] ?? 0);
        $durationMap     = [10 => 600, 30 => 1800, 60 => 3600, 120 => 7200];

        if (!isset($durationMap[$durationMinutes])) {
            return $this->json(['message' => 'Durée invalide (10, 30, 60 ou 120 minutes)'], Response::HTTP_BAD_REQUEST);
        }
        $durationSeconds = $durationMap[$durationMinutes];

        // Calcul du nombre de runs
        $secondsPerRun = $bestTurnCount * DungeonAutoSession::SECONDS_PER_ACTION;
        $completions   = (int) floor($durationSeconds / max(1, $secondsPerRun));
        $completions   = max(1, $completions); // au moins 1 run

        // Pré-calculer les récompenses
        $rewards = [];
        for ($run = 0; $run < $completions; $run++) {
            foreach ($dungeon->getRewards() as $r) {
                if (random_int(1, 100) > $r->getDropChance()) continue;
                $qty = $r->rollQuantity();
                $rewards[] = [
                    'rewardType' => $r->getRewardType(),
                    'quantity'   => $qty,
                    'item'       => $r->getItem()   ? ['id' => $r->getItem()->getId(),   'name' => $r->getItem()->getName()]   : null,
                    'scroll'     => $r->getScroll() ? ['id' => $r->getScroll()->getId(), 'name' => $r->getScroll()->getName()] : null,
                ];
            }
        }

        $session = new DungeonAutoSession();
        $session->setUser($user);
        $session->setDungeon($dungeon);
        $session->setDurationSeconds($durationSeconds);
        $session->setCompletions($completions);
        $session->setRewardsJson(json_encode($rewards));
        $this->em->persist($session);
        $this->em->flush();

        return $this->json($this->serialize($session), Response::HTTP_CREATED);
    }

    // ── Status ────────────────────────────────────────────────────────────────

    #[Route('/api/dungeon/auto/status', name: 'api_dungeon_auto_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user    = $this->em->find(User::class, $this->getUser()->getId());
        $session = $this->sessionRepository->findActiveForUser($user);

        if ($session === null) {
            return $this->json(null);
        }

        return $this->json($this->serialize($session));
    }

    // ── Claim ─────────────────────────────────────────────────────────────────

    #[Route('/api/dungeon/auto/{sid}/claim', name: 'api_dungeon_auto_claim', methods: ['POST'])]
    public function claim(int $sid): JsonResponse
    {
        /** @var User $user */
        $user    = $this->em->find(User::class, $this->getUser()->getId());
        $session = $this->sessionRepository->find($sid);

        if ($session === null || $session->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Session introuvable'], Response::HTTP_NOT_FOUND);
        }
        if ($session->isClaimed()) {
            return $this->json(['message' => 'Déjà réclamée'], Response::HTTP_CONFLICT);
        }
        if (!$session->hasEnded()) {
            return $this->json(['message' => 'La session n\'est pas encore terminée'], Response::HTTP_FORBIDDEN);
        }

        $rewards = json_decode($session->getRewardsJson() ?? '[]', true);

        // Attribution effective des récompenses
        foreach ($rewards as $r) {
            $type = $r['rewardType'];
            $qty  = (int) ($r['quantity'] ?? 1);
            if ($type === 'gold') {
                $user->setGoldToken($user->getGoldToken() + $qty);
            } elseif ($type === 'item' && isset($r['item']['id'])) {
                $item = $this->em->find(\App\Entity\Item::class, $r['item']['id']);
                if ($item !== null) {
                    $inv = $this->inventoryRepository->findByUserAndItem($user, $item);
                    if ($inv === null) {
                        $inv = (new UserInventory())->setUser($user)->setItem($item)->setQuantity($qty);
                        $this->em->persist($inv);
                    } else {
                        $inv->setQuantity($inv->getQuantity() + $qty);
                    }
                }
            } elseif ($type === 'scroll' && isset($r['scroll']['id'])) {
                $scroll = $this->em->find(\App\Entity\Scroll::class, $r['scroll']['id']);
                if ($scroll !== null) {
                    $inv = $this->inventoryRepository->findByUserAndScroll($user, $scroll);
                    if ($inv === null) {
                        $inv = (new UserInventory())->setUser($user)->setScroll($scroll)->setQuantity($qty);
                        $this->em->persist($inv);
                    } else {
                        $inv->setQuantity($inv->getQuantity() + $qty);
                    }
                }
            }
        }

        $session->setIsClaimed(true);
        $this->em->flush();

        return $this->json([
            'claimed'  => true,
            'rewards'  => $rewards,
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function serialize(DungeonAutoSession $s): array
    {
        return [
            'id'              => $s->getId(),
            'dungeonId'       => $s->getDungeon()->getId(),
            'dungeonName'     => $s->getDungeon()->getName(),
            'dungeonDifficulty' => $s->getDungeon()->getDifficulty(),
            'durationSeconds' => $s->getDurationSeconds(),
            'startedAt'       => $s->getStartedAt()->format(\DateTimeInterface::ATOM),
            'endsAt'          => $s->getEndsAt()->format(\DateTimeInterface::ATOM),
            'remainingSeconds'=> $s->getRemainingSeconds(),
            'completions'     => $s->getCompletions(),
            'isClaimed'       => $s->isClaimed(),
            'hasEnded'        => $s->hasEnded(),
        ];
    }
}
