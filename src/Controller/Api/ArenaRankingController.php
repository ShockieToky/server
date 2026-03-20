<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ArenaBattleRepository;
use App\Repository\ArenaSeasonPlayerRepository;
use App\Repository\ArenaSeasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArenaRankingController extends AbstractController
{
    public function __construct(
        private readonly ArenaSeasonRepository       $seasonRepository,
        private readonly ArenaSeasonPlayerRepository $playerRepository,
        private readonly ArenaBattleRepository       $battleRepository,
        private readonly EntityManagerInterface      $em,
    ) {}

    /**
     * GET /api/arena/ranking
     * Classement de la saison active (wins DESC, losses ASC).
     */
    #[Route('/api/arena/ranking', name: 'api_arena_ranking', methods: ['GET'])]
    public function ranking(): JsonResponse
    {
        $season = $this->seasonRepository->findActive();
        if (!$season) {
            return $this->json(['message' => "Aucune saison d'arène en cours"], Response::HTTP_CONFLICT);
        }

        /** @var User $user */
        $user    = $this->em->find(User::class, $this->getUser()->getId());
        $entries = $this->playerRepository->findRanking($season, 100);

        $currentUserId = $user->getId();
        $rankData      = [];
        $myRank        = null;

        foreach ($entries as $rank => $entry) {
            $row = [
                'rank'    => $rank + 1,
                'userId'  => $entry->getUser()?->getId(),
                'pseudo'  => $entry->getUser()?->getPseudo(),
                'wins'    => $entry->getWins(),
                'losses'  => $entry->getLosses(),
                'isMe'    => $entry->getUser()?->getId() === $currentUserId,
            ];
            $rankData[] = $row;
            if ($entry->getUser()?->getId() === $currentUserId) {
                $myRank = $row;
            }
        }

        // Si l'utilisateur n'est pas dans le classement (< 1 victoire, non affiché)
        if ($myRank === null) {
            $myEntry = $this->playerRepository->findByUserAndSeason($user, $season);
            if ($myEntry) {
                $myRank = [
                    'rank'    => null,
                    'userId'  => $user->getId(),
                    'pseudo'  => $user->getPseudo(),
                    'wins'    => $myEntry->getWins(),
                    'losses'  => $myEntry->getLosses(),
                    'isMe'    => true,
                ];
            }
        }

        return $this->json([
            'season' => [
                'id'     => $season->getId(),
                'name'   => $season->getName(),
                'endsAt' => $season->getEndsAt()?->format('Y-m-d'),
            ],
            'ranking' => $rankData,
            'me'      => $myRank,
        ]);
    }

    /**
     * GET /api/arena/history
     * Derniers combats de l'utilisateur courant (attaquant ou défenseur).
     */
    #[Route('/api/arena/history', name: 'api_arena_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $season = $this->seasonRepository->findActive();
        if (!$season) {
            return $this->json(['battles' => []]);
        }

        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());

        $asAttacker = $this->battleRepository->findByAttackerAndSeason($user, $season);
        $asDefender = $this->battleRepository->findByDefenderAndSeason($user, $season);

        $all = array_merge($asAttacker, $asDefender);
        usort($all, fn($a, $b) => $b->getFoughtAt() <=> $a->getFoughtAt());

        $battles = [];
        foreach (array_slice($all, 0, 30) as $b) {
            $isAttacker = $b->getAttacker()?->getId() === $user->getId();
            $battles[]  = [
                'id'              => $b->getId(),
                'foughtAt'        => $b->getFoughtAt()->format('Y-m-d H:i'),
                'role'            => $isAttacker ? 'attacker' : 'defender',
                'opponentId'      => $isAttacker ? $b->getDefender()?->getId()   : $b->getAttacker()?->getId(),
                'opponentPseudo'  => $isAttacker ? $b->getDefender()?->getPseudo() : $b->getAttacker()?->getPseudo(),
                'victory'         => $isAttacker ? $b->isVictory() : !$b->isVictory(),
                'defenseSnapshot' => $b->getDefenseSnapshot(),
            ];
        }

        return $this->json(['battles' => $battles]);
    }
}
