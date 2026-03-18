<?php
namespace App\Controller\Api;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\UserHero;
use App\Repository\HeroRepository;
use App\Repository\UserInventoryRepository;
use App\Service\ScrollPullService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ouverture reelle de parchemins depuis l inventaire.
 *
 * POST /api/me/inventory/{invId}/open     body: { count: 1|10 }
 *   - scroll x1  -> { type:'scroll', hero }       (UserHero cree, inventaire decremente)
 *   - scroll x10 -> { type:'multi', heroes, maxRarity } (idem x10)
 *   - choice     -> { type:'choice', heroes }       (rien consomme, attente confirmaton)
 *
 * POST /api/me/inventory/{invId}/confirm  body: { heroId }
 *   - uniquement pour choice : cree le UserHero choisi + consomme 1 inventaire
 */
#[Route('/api/me/inventory', name: 'api_inv_open_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class InventoryOpenController extends AbstractController
{
    public function __construct(
        private readonly UserInventoryRepository $inventoryRepository,
        private readonly HeroRepository          $heroRepository,
        private readonly ScrollPullService       $pullService,
        private readonly EntityManagerInterface  $em,
    ) {}

    #[Route('/{invId}/open', name: 'open', methods: ['POST'])]
    public function open(int $invId, Request $request): JsonResponse
    {
        $entry = $this->inventoryRepository->find($invId);
        if (!$entry || $entry->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Entree introuvable'], Response::HTTP_NOT_FOUND);
        }

        $scroll = $entry->getScroll();
        if (!$scroll) {
            return $this->json(['message' => 'Cette entree n est pas un parchemin'], Response::HTTP_BAD_REQUEST);
        }

        $data  = json_decode($request->getContent(), true) ?? [];
        $count = ($scroll->getType() === 'choice') ? 1 : max(1, min(10, (int) ($data['count'] ?? 1)));

        if ($entry->getQuantity() < $count) {
            return $this->json(['message' => 'Quantite insuffisante'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Parchemin de choix : on ne consomme pas encore, on renvoie les 5 options
        if ($scroll->getType() === 'choice') {
            try {
                $result = $this->pullService->pull($scroll);
            } catch (\RuntimeException $e) {
                return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            return $this->json([
                'type'   => 'choice',
                'heroes' => array_map($this->serializeHero(...), $result['heroes']),
            ]);
        }

        // Parchemin normal : on tire $count fois, on cree les UserHero et on consomme
        $heroes = [];
        for ($i = 0; $i < $count; $i++) {
            try {
                $result = $this->pullService->pull($scroll);
            } catch (\RuntimeException $e) {
                return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->createUserHero($result['hero']);
            $heroes[] = $result['hero'];
        }

        $newQty = $entry->getQuantity() - $count;
        if ($newQty <= 0) {
            $this->inventoryRepository->remove($entry);
        } else {
            $entry->setQuantity($newQty);
        }
        $this->em->flush();

        if ($count === 1) {
            return $this->json(['type' => 'scroll', 'hero' => $this->serializeHero($heroes[0])]);
        }

        $maxRarity = max(array_map(fn($h) => $h->getRarity(), $heroes));
        return $this->json([
            'type'      => 'multi',
            'heroes'    => array_map($this->serializeHero(...), $heroes),
            'maxRarity' => $maxRarity,
        ]);
    }

    #[Route('/{invId}/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(int $invId, Request $request): JsonResponse
    {
        $entry = $this->inventoryRepository->find($invId);
        if (!$entry || $entry->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Entree introuvable'], Response::HTTP_NOT_FOUND);
        }

        $scroll = $entry->getScroll();
        if (!$scroll || $scroll->getType() !== 'choice') {
            return $this->json(['message' => 'Ce parchemin n est pas un parchemin de choix'], Response::HTTP_BAD_REQUEST);
        }

        if ($entry->getQuantity() < 1) {
            return $this->json(['message' => 'Quantite insuffisante'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $heroId = isset($data['heroId']) ? (int) $data['heroId'] : null;

        if (!$heroId) {
            return $this->json(['message' => 'heroId est requis'], Response::HTTP_BAD_REQUEST);
        }

        $hero = $this->heroRepository->find($heroId);
        if (!$hero) {
            return $this->json(['message' => 'Heros introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->createUserHero($hero);

        $newQty = $entry->getQuantity() - 1;
        if ($newQty <= 0) {
            $this->inventoryRepository->remove($entry);
        } else {
            $entry->setQuantity($newQty);
        }
        $this->em->flush();

        return $this->json(['type' => 'scroll', 'hero' => $this->serializeHero($hero)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createUserHero(\App\Entity\Hero $hero): void
    {
        $userHero = new UserHero();
        $userHero->setUser($this->getUser())->setHero($hero);

        for ($moduleSlot = 1; $moduleSlot <= 3; $moduleSlot++) {
            $module = new HeroModule();
            $module->setSlotIndex($moduleSlot)->setLevel(1);
            for ($extSlot = 1; $extSlot <= 2; $extSlot++) {
                $slot = new EquippedExtension();
                $slot->setSlotIndex($extSlot);
                $module->addSlot($slot);
                $this->em->persist($slot);
            }
            $userHero->addModule($module);
            $this->em->persist($module);
        }
        $this->em->persist($userHero);
    }

    private function serializeHero(\App\Entity\Hero $h): array
    {
        return [
            'id'          => $h->getId(),
            'name'        => $h->getName(),
            'description' => $h->getDescription(),
            'rarity'      => $h->getRarity(),
            'type'        => $h->getType(),
            'attack'      => $h->getAttack(),
            'defense'     => $h->getDefense(),
            'hp'          => $h->getHp(),
            'speed'       => $h->getSpeed(),
            'critRate'    => $h->getCritRate(),
            'critDamage'  => $h->getCritDamage(),
            'accuracy'    => $h->getAccuracy(),
            'resistance'  => $h->getResistance(),
            'faction'     => $h->getFaction() ? ['id' => $h->getFaction()->getId(), 'name' => $h->getFaction()->getName()] : null,
            'origine'     => $h->getOrigine() ? ['id' => $h->getOrigine()->getId(), 'name' => $h->getOrigine()->getName()] : null,
        ];
    }
}