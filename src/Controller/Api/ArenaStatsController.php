<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ArenaDefenseRepository;
use App\Repository\ArenaSeasonPlayerRepository;
use App\Repository\ArenaSeasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Stats de l'arène pour le joueur courant.
 *
 * GET /api/arena/my/stats  → wins, losses, attaques restantes, infos saison
 */
#[Route('/api/arena/my/stats', name: 'api_arena_stats_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArenaStatsController extends AbstractController
{
    private const DAILY_LIMIT = 10;

    public function __construct(
        private readonly ArenaSeasonRepository       $seasonRepository,
        private readonly ArenaSeasonPlayerRepository $playerRepository,
        private readonly ArenaDefenseRepository      $defenseRepository,
        private readonly EntityManagerInterface      $em,
    ) {}

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $season = $this->seasonRepository->findActive();
        if (!$season) {
            return $this->json([
                'seasonActive'     => false,
                'wins'             => 0,
                'losses'           => 0,
                'attacksRemaining' => 0,
                'hasDefense'       => false,
            ]);
        }

        /** @var User $user */
        $user  = $this->em->find(User::class, $this->getUser()->getId());
        $entry = $this->playerRepository->findOrCreate($user, $season, $this->em);
        $this->em->flush();

        $defenses   = $this->defenseRepository->findByUser($user);
        $hasDefense = false;
        foreach ($defenses as $d) {
            if (!$d->isEmpty()) { $hasDefense = true; break; }
        }

        return $this->json([
            'seasonActive'     => true,
            'seasonId'         => $season->getId(),
            'seasonName'       => $season->getName(),
            'seasonEndsAt'     => $season->getEndsAt()?->format(\DateTime::ATOM),
            'wins'             => $entry->getWins(),
            'losses'           => $entry->getLosses(),
            'attacksRemaining' => $entry->getAttacksRemaining(self::DAILY_LIMIT),
            'attacksUsedToday' => $entry->getAttacksUsedToday(),
            'dailyLimit'       => self::DAILY_LIMIT,
            'hasDefense'       => $hasDefense,
        ]);
    }
}
