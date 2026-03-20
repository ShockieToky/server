<?php

namespace App\Controller\Api;

use App\Entity\ArenaDefense;
use App\Entity\User;
use App\Repository\ArenaDefenseRepository;
use App\Repository\UserHeroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des 3 défenses d'arène d'un joueur.
 *
 * GET /api/arena/my/defense              → retourne les 3 slots (valeurs nulles pour les slots vides)
 * PUT /api/arena/my/defense/{slot}       → configure le slot {1|2|3}
 *     Body : { heroIds: [id1..4], leadFactionId?: int, leadOrigineId?: int }
 */
#[Route('/api/arena/my/defense', name: 'api_arena_defense_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArenaDefenseController extends AbstractController
{
    public function __construct(
        private readonly ArenaDefenseRepository $defenseRepository,
        private readonly UserHeroRepository     $userHeroRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    // ── GET ───────────────────────────────────────────────────────────────────

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user     = $this->em->find(User::class, $this->getUser()->getId());
        $defenses = $this->defenseRepository->findByUser($user);
        $bySlot   = [];
        foreach ($defenses as $d) {
            $bySlot[$d->getSlotIndex()] = $d;
        }

        $result = [];
        for ($slot = 1; $slot <= 3; $slot++) {
            $result[] = isset($bySlot[$slot])
                ? $this->serialize($bySlot[$slot])
                : $this->emptySlot($slot);
        }

        return $this->json($result);
    }

    // ── PUT ───────────────────────────────────────────────────────────────────

    #[Route('/{slot}', name: 'set', methods: ['PUT'], requirements: ['slot' => '[1-3]'])]
    public function set(int $slot, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());
        $data = json_decode($request->getContent(), true) ?? [];

        $defense = $this->defenseRepository->findByUserAndSlot($user, $slot);
        if ($defense === null) {
            $defense = (new ArenaDefense())->setUser($user)->setSlotIndex($slot);
            $this->em->persist($defense);
        }

        $this->applyHeroes($defense, $user, $data);
        $defense->setLeadFactionId(isset($data['leadFactionId']) && $data['leadFactionId'] ? (int) $data['leadFactionId'] : null);
        $defense->setLeadOrigineId(isset($data['leadOrigineId']) && $data['leadOrigineId'] ? (int) $data['leadOrigineId'] : null);
        $defense->touch();

        $this->em->flush();

        return $this->json($this->serialize($defense));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function applyHeroes(ArenaDefense $defense, User $user, array $data): void
    {
        $heroIds = array_slice(array_values(array_filter((array) ($data['heroIds'] ?? []))), 0, 4);
        $slots   = ['setHero1', 'setHero2', 'setHero3', 'setHero4'];

        foreach ($slots as $i => $setter) {
            if (isset($heroIds[$i])) {
                $uh = $this->userHeroRepository->find((int) $heroIds[$i]);
                $defense->$setter(
                    ($uh && $uh->getUser()?->getId() === $user->getId()) ? $uh : null
                );
            } else {
                $defense->$setter(null);
            }
        }
    }

    private function serialize(ArenaDefense $d): array
    {
        return [
            'id'            => $d->getId(),
            'slot'          => $d->getSlotIndex(),
            'hero1'         => $this->serializeHero($d->getHero1()),
            'hero2'         => $this->serializeHero($d->getHero2()),
            'hero3'         => $this->serializeHero($d->getHero3()),
            'hero4'         => $this->serializeHero($d->getHero4()),
            'leadFactionId' => $d->getLeadFactionId(),
            'leadOrigineId' => $d->getLeadOrigineId(),
            'updatedAt'     => $d->getUpdatedAt()->format(\DateTime::ATOM),
        ];
    }

    private function serializeHero(?object $uh): ?array
    {
        if (!$uh) return null;
        $hero    = $uh->getHero();
        $faction = $hero?->getFaction();
        $origine = $hero?->getOrigine();
        return [
            'id'     => $uh->getId(),
            'hero'   => [
                'id'      => $hero?->getId(),
                'name'    => $hero?->getName(),
                'rarity'  => $hero?->getRarity(),
                'type'    => $hero?->getType(),
                'faction' => $faction ? ['id' => $faction->getId(), 'name' => $faction->getName()] : null,
                'origine' => $origine ? ['id' => $origine->getId(), 'name' => $origine->getName()] : null,
            ],
        ];
    }

    private function emptySlot(int $slot): array
    {
        return [
            'id'            => null,
            'slot'          => $slot,
            'hero1'         => null,
            'hero2'         => null,
            'hero3'         => null,
            'hero4'         => null,
            'leadFactionId' => null,
            'leadOrigineId' => null,
            'updatedAt'     => null,
        ];
    }
}
