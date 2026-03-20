<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ArenaDefenseRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Liste les adversaires disponibles et leurs défenses.
 *
 * GET /api/arena/opponents                  → joueurs ayant au moins 1 défense configurée
 * GET /api/arena/opponents/{userId}/defenses → les 3 slots de défense d'un adversaire
 */
#[Route('/api/arena/opponents', name: 'api_arena_opponents_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArenaOpponentController extends AbstractController
{
    public function __construct(
        private readonly ArenaDefenseRepository $defenseRepository,
        private readonly UserRepository         $userRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $me */
        $me  = $this->em->find(User::class, $this->getUser()->getId());
        $ids = $this->defenseRepository->findActiveDefenderIds($me);

        if (empty($ids)) {
            return $this->json([]);
        }

        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn(User $u) => [
            'id'     => $u->getId(),
            'pseudo' => $u->getPseudo(),
        ], $users));
    }

    #[Route('/{userId}/defenses', name: 'defenses', methods: ['GET'])]
    public function defenses(int $userId): JsonResponse
    {
        /** @var User $me */
        $me = $this->em->find(User::class, $this->getUser()->getId());

        if ($userId === $me->getId()) {
            return $this->json(['message' => 'Vous ne pouvez pas attaquer votre propre défense'], Response::HTTP_BAD_REQUEST);
        }

        $target = $this->userRepository->find($userId);
        if (!$target) {
            return $this->json(['message' => 'Joueur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $defenses = $this->defenseRepository->findByUser($target);
        $bySlot   = [];
        foreach ($defenses as $d) {
            $bySlot[$d->getSlotIndex()] = $d;
        }

        $result = [];
        for ($slot = 1; $slot <= 3; $slot++) {
            if (isset($bySlot[$slot]) && !$bySlot[$slot]->isEmpty()) {
                $result[] = $this->serializePublic($bySlot[$slot]);
            }
        }

        return $this->json([
            'defender' => ['id' => $target->getId(), 'pseudo' => $target->getPseudo()],
            'defenses' => $result,
        ]);
    }

    private function serializePublic(object $d): array
    {
        return [
            'id'            => $d->getId(),
            'slot'          => $d->getSlotIndex(),
            'heroes'        => array_map(fn($uh) => [
                'id'     => $uh->getId(),
                'hero'   => [
                    'id'     => $uh->getHero()->getId(),
                    'name'   => $uh->getHero()->getName(),
                    'rarity' => $uh->getHero()->getRarity(),
                    'type'   => $uh->getHero()->getType(),
                ],
            ], $d->getHeroes()),
            'leadFactionId' => $d->getLeadFactionId(),
            'leadOrigineId' => $d->getLeadOrigineId(),
        ];
    }
}
