<?php

namespace App\Controller\Api;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\UserHero;
use App\Repository\FactionRepository;
use App\Repository\HeroRepository;
use App\Repository\UserHeroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Onboarding — choix de la faction de départ.
 *
 * GET  /api/onboarding          → { done: bool, factions: [...] }
 * POST /api/onboarding/choose   → { factionId } → donne 4 héros (2×2★ + 2×3★)
 */
#[Route('/api/onboarding', name: 'api_onboarding_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly FactionRepository  $factionRepository,
        private readonly HeroRepository     $heroRepository,
        private readonly UserHeroRepository $userHeroRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user     = $this->getUser();
        $factions = $this->factionRepository->findAll();

        return $this->json([
            'done'     => $user->isStarterDone(),
            'factions' => array_map(fn($f) => [
                'id'                 => $f->getId(),
                'name'               => $f->getName(),
                'description'        => $f->getDescription(),
                'passiveName'        => $f->getPassiveName(),
                'passiveDescription' => $f->getPassiveDescription(),
            ], $factions),
        ]);
    }

    #[Route('/choose', name: 'choose', methods: ['POST'])]
    public function choose(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user->isStarterDone()) {
            return $this->json(['message' => 'Équipe de départ déjà choisie.'], Response::HTTP_CONFLICT);
        }

        $data      = json_decode($request->getContent(), true) ?? [];
        $factionId = $data['factionId'] ?? null;

        if (!$factionId) {
            return $this->json(['message' => 'factionId est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $faction = $this->factionRepository->find((int) $factionId);
        if (!$faction) {
            return $this->json(['message' => 'Faction introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $heroes2 = $this->heroRepository->findByFactionAndRarity($faction, 2);
        $heroes3 = $this->heroRepository->findByFactionAndRarity($faction, 3);

        $missing2 = max(0, 2 - count($heroes2));
        if (count($heroes2) === 0 && count($heroes3) < 4) {
            return $this->json(
                ['message' => 'Cette faction ne possède pas assez de héros (au moins 1×2★ ou 4×3★ requis).'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        if (count($heroes3) < 2 + $missing2) {
            return $this->json(
                ['message' => 'Cette faction ne possède pas assez de héros 3★.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $picked2 = $this->pickRandom($heroes2, 2);
        $picked3 = $this->pickRandom($heroes3, 2 + $missing2);

        $userHeroes = [];
        foreach (array_merge($picked2, $picked3) as $hero) {
            $userHeroes[] = $this->createUserHero($user, $hero);
        }

        $user->setStarterDone(true);
        $this->em->flush();

        return $this->json([
            'heroes' => array_map(fn(UserHero $uh) => [
                'id'     => $uh->getId(),
                'name'   => $uh->getHero()->getName(),
                'rarity' => $uh->getHero()->getRarity(),
                'type'   => $uh->getHero()->getType(),
            ], $userHeroes),
        ], Response::HTTP_CREATED);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function pickRandom(array $pool, int $n): array
    {
        if (count($pool) <= $n) {
            return $pool;
        }
        $keys = (array) array_rand($pool, $n);
        return array_map(fn($k) => $pool[$k], $keys);
    }

    private function createUserHero(\App\Entity\User $user, \App\Entity\Hero $hero): UserHero
    {
        $uh = new UserHero();
        $uh->setUser($user)->setHero($hero);

        for ($moduleSlot = 1; $moduleSlot <= 3; $moduleSlot++) {
            $module = new HeroModule();
            $module->setSlotIndex($moduleSlot)->setLevel(1);

            for ($extSlot = 1; $extSlot <= 2; $extSlot++) {
                $slot = new EquippedExtension();
                $slot->setSlotIndex($extSlot);
                $module->addSlot($slot);
            }

            $uh->addModule($module);
        }

        $this->em->persist($uh);
        return $uh;
    }
}
