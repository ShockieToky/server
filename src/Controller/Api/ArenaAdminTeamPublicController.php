<?php

namespace App\Controller\Api;

use App\Repository\ArenaAdminTeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /api/arena/admin-teams
 * Liste les équipes bot actives que le joueur peut attaquer.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArenaAdminTeamPublicController extends AbstractController
{
    public function __construct(
        private readonly ArenaAdminTeamRepository $adminTeamRepository,
    ) {}

    #[Route('/api/arena/admin-teams', name: 'api_arena_admin_teams_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $teams = $this->adminTeamRepository->findActive();

        $data = array_map(fn($team) => [
            'id'            => $team->getId(),
            'name'          => $team->getName(),
            'slotIndex'     => $team->getSlotIndex(),
            'isEmpty'       => $team->isEmpty(),
            'leadFactionId' => $team->getLeadFactionId(),
            'leadOrigineId' => $team->getLeadOrigineId(),
            'heroes'        => array_map(fn($h) => [
                'id'      => $h->getId(),
                'name'    => $h->getName(),
                'rarity'  => $h->getRarity(),
                'faction' => $h->getFaction()?->getName(),
                'origine' => $h->getOrigine()?->getName(),
            ], $team->getHeroes()),
        ], $teams);

        return $this->json($data);
    }
}
