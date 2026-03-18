<?php

namespace App\Controller\Api;

use App\Entity\UserInventory;
use App\Repository\ItemRepository;
use App\Repository\UserHeroRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Puit des âmes — sacrifier des héros 1/2/3 étoiles pour obtenir des Pierres d'âme.
 *
 * GET  /api/soul-well/heroes   → heroes 1-3★ de l'utilisateur
 * POST /api/soul-well/sacrifice → { heroIds: int[] } → sacrifie, donne des pierres d'âme
 */
#[Route('/api/soul-well', name: 'api_soul_well_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class SoulWellController extends AbstractController
{
    public function __construct(
        private readonly UserHeroRepository      $userHeroRepository,
        private readonly UserInventoryRepository $inventoryRepository,
        private readonly ItemRepository          $itemRepository,
        private readonly EntityManagerInterface  $em,
    ) {}

    /** Liste les héros 1/2/3★ du joueur (candidats au sacrifice). */
    #[Route('/heroes', name: 'heroes', methods: ['GET'])]
    public function heroes(): JsonResponse
    {
        $user = $this->getUser();
        $all  = $this->userHeroRepository->findByUser($user);

        $eligible = array_values(array_filter($all, fn($uh) => $uh->getHero()->getRarity() <= 3));

        return $this->json(array_map(fn($uh) => [
            'id'     => $uh->getId(),
            'hero'   => [
                'id'     => $uh->getHero()->getId(),
                'name'   => $uh->getHero()->getName(),
                'rarity' => $uh->getHero()->getRarity(),
                'type'   => $uh->getHero()->getType(),
            ],
        ], $eligible));
    }

    /** Sacrifie les héros donnés et crédite les pierres d'âme correspondantes. */
    #[Route('/sacrifice', name: 'sacrifice', methods: ['POST'])]
    public function sacrifice(Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true) ?? [];
        $heroIds  = $data['heroIds'] ?? [];

        if (empty($heroIds) || !is_array($heroIds)) {
            return $this->json(['message' => 'heroIds est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $user      = $this->getUser();
        $starTotal = 0;
        $toRemove  = [];

        foreach ($heroIds as $uhId) {
            $uh = $this->userHeroRepository->find((int) $uhId);

            if (!$uh || $uh->getUser()?->getId() !== $user->getId()) {
                return $this->json(['message' => "Héros #$uhId introuvable."], Response::HTTP_NOT_FOUND);
            }

            $rarity = $uh->getHero()->getRarity();
            if ($rarity > 3) {
                return $this->json(
                    ['message' => "Seuls les héros 1/2/3★ peuvent être jetés au puit (héros #{$uh->getHero()->getId()})."],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $starTotal += $rarity;
            $toRemove[] = $uh;
        }

        $stonesEarned = intdiv($starTotal, 5);

        // Supprimer les héros sacrifiés
        foreach ($toRemove as $uh) {
            $this->em->remove($uh);
        }

        // Créditer les pierres d'âme si on en a gagné au moins 1
        if ($stonesEarned > 0) {
            $soulStoneItem = $this->itemRepository->findByEffectType('soul_stone');
            if (!$soulStoneItem) {
                return $this->json(['message' => 'Pierre d\'âme introuvable dans le catalogue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $entry = $this->inventoryRepository->findSoulStones($user);
            if (!$entry) {
                $entry = new UserInventory();
                $entry->setUser($user)->setItem($soulStoneItem)->setQuantity(0);
                $this->em->persist($entry);
            }
            $entry->setQuantity($entry->getQuantity() + $stonesEarned);
        }

        $this->em->flush();

        return $this->json([
            'sacrificed'   => count($toRemove),
            'totalStars'   => $starTotal,
            'stonesEarned' => $stonesEarned,
            'remainder'    => $starTotal % 10,
        ]);
    }
}
