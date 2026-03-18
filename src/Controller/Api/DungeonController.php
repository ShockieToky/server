<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\DungeonRepository;
use App\Repository\UserDungeonProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Donjons PVE — côté joueur.
 *
 * GET /api/dungeons        → liste des donjons actifs avec progression
 * GET /api/dungeon/{id}    → détail d'un donjon (waves + récompenses)
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DungeonController extends AbstractController
{
    public function __construct(
        private readonly DungeonRepository             $dungeonRepository,
        private readonly UserDungeonProgressRepository $progressRepository,
        private readonly EntityManagerInterface        $em,
    ) {}

    #[Route('/api/dungeons', name: 'api_dungeons_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user     = $this->em->find(User::class, $this->getUser()->getId());
        $dungeons = $this->dungeonRepository->findActive();

        // Index progressions de ce joueur
        $progressMap = [];
        foreach ($this->progressRepository->findByUser($user) as $p) {
            $progressMap[$p->getDungeon()->getId()] = $p;
        }

        return $this->json(array_map(function ($dungeon) use ($progressMap) {
            $progress = $progressMap[$dungeon->getId()] ?? null;

            return [
                'id'          => $dungeon->getId(),
                'name'        => $dungeon->getName(),
                'description' => $dungeon->getDescription(),
                'difficulty'  => $dungeon->getDifficulty(),
                'runCount'    => $progress?->getRunCount() ?? 0,
                'lastCompletedAt' => $progress?->getLastCompletedAt()?->format(\DateTimeInterface::ATOM),
                'rewards'     => array_map(fn($r) => [
                    'rewardType'  => $r->getRewardType(),
                    'quantityMin' => $r->getQuantityMin(),
                    'quantityMax' => $r->getQuantityMax(),
                    'item'        => $r->getItem() ? ['id' => $r->getItem()->getId(), 'name' => $r->getItem()->getName()] : null,
                    'scroll'      => $r->getScroll() ? ['id' => $r->getScroll()->getId(), 'name' => $r->getScroll()->getName()] : null,
                ], $dungeon->getRewards()->toArray()),
            ];
        }, $dungeons));
    }

    #[Route('/api/dungeon/{id}', name: 'api_dungeon_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $dungeon = $this->dungeonRepository->findWithWaves($id);
        if (!$dungeon || !$dungeon->isActive()) {
            return $this->json(['message' => 'Donjon introuvable'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user     = $this->em->find(User::class, $this->getUser()->getId());
        $progress = $this->progressRepository->findOneByUserAndDungeon($user, $dungeon);

        return $this->json([
            'id'          => $dungeon->getId(),
            'name'        => $dungeon->getName(),
            'description' => $dungeon->getDescription(),
            'difficulty'  => $dungeon->getDifficulty(),
            'runCount'    => $progress?->getRunCount() ?? 0,
            'lastCompletedAt' => $progress?->getLastCompletedAt()?->format(\DateTimeInterface::ATOM),
            'waves'       => array_map(fn($wave) => [
                'waveNumber' => $wave->getWaveNumber(),
                'monsters'   => array_map(fn($wm) => [
                    'name'     => $wm->getMonster()?->getName(),
                    'quantity' => $wm->getQuantity(),
                ], $wave->getWaveMonsters()->toArray()),
            ], $dungeon->getWaves()->toArray()),
            'rewards' => array_map(fn($r) => [
                'rewardType'  => $r->getRewardType(),
                'quantityMin' => $r->getQuantityMin(),
                'quantityMax' => $r->getQuantityMax(),
                'item'        => $r->getItem() ? ['id' => $r->getItem()->getId(), 'name' => $r->getItem()->getName()] : null,
                'scroll'      => $r->getScroll() ? ['id' => $r->getScroll()->getId(), 'name' => $r->getScroll()->getName()] : null,
            ], $dungeon->getRewards()->toArray()),
        ]);
    }
}
