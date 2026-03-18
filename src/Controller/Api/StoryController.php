<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\UserInventory;
use App\Entity\UserStoryProgress;
use App\Repository\StoryStageRepository;
use App\Repository\UserInventoryRepository;
use App\Repository\UserStoryProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Mode histoire — côté joueur.
 *
 * GET  /api/story/stages      → liste des étapes avec progression du joueur
 * GET  /api/story/stage/{id}  → détail d'une étape (waves + monstres)
 * POST /api/story/stage/{id}/complete  → marquer l'étape comme terminée
 * POST /api/story/stage/{id}/claim     → réclamer les récompenses
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class StoryController extends AbstractController
{
    public function __construct(
        private readonly StoryStageRepository         $stageRepository,
        private readonly UserStoryProgressRepository  $progressRepository,
        private readonly UserInventoryRepository      $inventoryRepository,
        private readonly EntityManagerInterface       $em,
    ) {}

    // ── Liste des étapes ──────────────────────────────────────────────────────

    #[Route('/api/story/stages', name: 'api_story_stages', methods: ['GET'])]
    public function stages(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $stages = $this->stageRepository->findActive();
        $completedIds = $this->progressRepository->findCompletedStageIds($user);
        $progressMap  = [];
        foreach ($this->progressRepository->findAllByUser($user) as $p) {
            $progressMap[$p->getStage()->getId()] = $p;
        }

        return $this->json(array_map(function ($stage) use ($completedIds, $progressMap) {
            $stageId   = $stage->getId();
            $progress  = $progressMap[$stageId] ?? null;
            $completed = $progress?->isCompleted() ?? false;
            $claimed   = $progress?->isRewardClaimed() ?? false;

            // Débloquée si c'est la première étape ou si la précédente est terminée
            $unlocked  = $stage->getStageNumber() === 1 || in_array($stageId - 1, $completedIds, true)
                // chercher l'étape précédente par numéro
                || $this->isPreviousCompleted($stage->getStageNumber(), $completedIds);

            return [
                'id'          => $stageId,
                'stageNumber' => $stage->getStageNumber(),
                'name'        => $stage->getName(),
                'description' => $stage->getDescription(),
                'unlocked'    => $unlocked,
                'completed'   => $completed,
                'rewardClaimed' => $claimed,
                'rewards'     => array_map(fn($r) => [
                    'id'         => $r->getId(),
                    'rewardType' => $r->getRewardType(),
                    'quantity'   => $r->getQuantity(),
                    'item'       => $r->getItem() ? ['id' => $r->getItem()->getId(), 'name' => $r->getItem()->getName()] : null,
                    'scroll'     => $r->getScroll() ? ['id' => $r->getScroll()->getId(), 'name' => $r->getScroll()->getName()] : null,
                ], $stage->getRewards()->toArray()),
            ];
        }, $stages));
    }

    // ── Détail d'une étape ────────────────────────────────────────────────────

    #[Route('/api/story/stage/{id}', name: 'api_story_stage_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $stage = $this->stageRepository->findWithWaves($id);
        if (!$stage || !$stage->isActive()) {
            return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);
        }
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $progress = $this->progressRepository->findOneByUserAndStage($user, $stage);

        return $this->json([
            'id'          => $stage->getId(),
            'stageNumber' => $stage->getStageNumber(),
            'name'        => $stage->getName(),
            'description' => $stage->getDescription(),
            'completed'   => $progress?->isCompleted() ?? false,
            'rewardClaimed' => $progress?->isRewardClaimed() ?? false,
            'waves'       => array_map(fn($w) => [
                'waveNumber' => $w->getWaveNumber(),
                'monsters'   => array_map(fn($wm) => [
                    'name'     => $wm->getMonster()?->getName(),
                    'level'    => $wm->getMonster()?->getLevel(),
                    'type'     => $wm->getMonster()?->getType(),
                    'hp'       => $wm->getMonster()?->getHp(),
                    'attack'   => $wm->getMonster()?->getAttack(),
                    'defense'  => $wm->getMonster()?->getDefense(),
                    'speed'    => $wm->getMonster()?->getSpeed(),
                    'quantity' => $wm->getQuantity(),
                ], $w->getWaveMonsters()->toArray()),
            ], $stage->getWaves()->toArray()),
            'rewards' => array_map(fn($r) => [
                'rewardType' => $r->getRewardType(),
                'quantity'   => $r->getQuantity(),
                'item'       => $r->getItem() ? ['id' => $r->getItem()->getId(), 'name' => $r->getItem()->getName()] : null,
                'scroll'     => $r->getScroll() ? ['id' => $r->getScroll()->getId(), 'name' => $r->getScroll()->getName()] : null,
            ], $stage->getRewards()->toArray()),
        ]);
    }

    // ── Compléter une étape ───────────────────────────────────────────────────

    #[Route('/api/story/stage/{id}/complete', name: 'api_story_stage_complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        if (!$stage || !$stage->isActive()) {
            return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Vérification débloquage
        if ($stage->getStageNumber() > 1) {
            $completedIds = $this->progressRepository->findCompletedStageIds($user);
            if (!$this->isPreviousCompleted($stage->getStageNumber(), $completedIds)) {
                return $this->json(['message' => "L'étape précédente n'est pas encore terminée"], Response::HTTP_FORBIDDEN);
            }
        }

        $progress = $this->progressRepository->findOneByUserAndStage($user, $stage);
        if ($progress && $progress->isCompleted()) {
            return $this->json(['message' => 'Étape déjà terminée', 'rewardClaimed' => $progress->isRewardClaimed()]);
        }

        if (!$progress) {
            $progress = new UserStoryProgress();
            $progress->setUser($user);
            $progress->setStage($stage);
            $this->em->persist($progress);
        }
        $progress->setCompletedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(['message' => 'Étape terminée !', 'rewardClaimed' => false]);
    }

    // ── Réclamer les récompenses ──────────────────────────────────────────────

    #[Route('/api/story/stage/{id}/claim', name: 'api_story_stage_claim', methods: ['POST'])]
    public function claim(int $id): JsonResponse
    {
        $stage = $this->stageRepository->findWithWaves($id);
        if (!$stage) return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);

        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $progress = $this->progressRepository->findOneByUserAndStage($user, $stage);

        if (!$progress || !$progress->isCompleted()) {
            return $this->json(['message' => "Terminez l'étape avant de réclamer les récompenses"], Response::HTTP_FORBIDDEN);
        }
        if ($progress->isRewardClaimed()) {
            return $this->json(['message' => 'Récompenses déjà réclamées'], Response::HTTP_CONFLICT);
        }

        $given = [];
        foreach ($stage->getRewards() as $reward) {
            match ($reward->getRewardType()) {
                'history_token', 'history_to', 'gold' => $this->giveHistoryToken($user, $reward->getQuantity(), $given),
                'item'                                 => $this->giveItem($user, $reward, $given),
                'scroll'                               => $this->giveScroll($user, $reward, $given),
                default                                => null,
            };
        }

        $progress->setRewardClaimed(true);
        $this->em->flush();

        return $this->json(['message' => 'Récompenses réclamées !', 'rewards' => $given]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Vérifie qu'une étape de numéro (stageNumber - 1) est bien complétée.
     * On cherche par stageNumber dans les étapes actives.
     */
    private function isPreviousCompleted(int $stageNumber, array $completedIds): bool
    {
        if ($stageNumber <= 1) return true;
        $prev = $this->stageRepository->findOneBy(['stageNumber' => $stageNumber - 1]);
        return $prev && in_array($prev->getId(), $completedIds, true);
    }

    private function giveHistoryToken(\App\Entity\User $user, int $qty, array &$given): void
    {
        $user->setHistoryToken($user->getHistoryToken() + $qty);
        $given[] = ['rewardType' => 'history_token', 'quantity' => $qty];
    }

    private function giveItem(\App\Entity\User $user, \App\Entity\StoryReward $reward, array &$given): void
    {
        $item = $reward->getItem();
        if (!$item) return;
        $inv = $this->inventoryRepository->findByUserAndItem($user, $item);
        if (!$inv) {
            $inv = new UserInventory();
            $inv->setUser($user);
            $inv->setItem($item);
            $inv->setQuantity(0);
            $this->em->persist($inv);
        }
        $inv->setQuantity($inv->getQuantity() + $reward->getQuantity());
        $given[] = ['rewardType' => 'item', 'name' => $item->getName(), 'quantity' => $reward->getQuantity()];
    }

    private function giveScroll(\App\Entity\User $user, \App\Entity\StoryReward $reward, array &$given): void
    {
        $scroll = $reward->getScroll();
        if (!$scroll) return;
        $inv = $this->inventoryRepository->findByUserAndScroll($user, $scroll);
        if (!$inv) {
            $inv = new UserInventory();
            $inv->setUser($user);
            $inv->setScroll($scroll);
            $inv->setQuantity(0);
            $this->em->persist($inv);
        }
        $inv->setQuantity($inv->getQuantity() + $reward->getQuantity());
        $given[] = ['rewardType' => 'scroll', 'name' => $scroll->getName(), 'quantity' => $reward->getQuantity()];
    }
}
