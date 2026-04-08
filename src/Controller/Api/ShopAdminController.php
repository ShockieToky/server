<?php

namespace App\Controller\Api;

use App\Entity\ShopItem;
use App\Repository\EventCurrencyRepository;
use App\Repository\HeroRepository;
use App\Repository\ItemRepository;
use App\Repository\ScrollRepository;
use App\Repository\ShopItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD articles de la boutique (admin).
 *
 * GET    /api/admin/shop-items         → liste
 * POST   /api/admin/shop-items         → créer
 * GET    /api/admin/shop-item/{id}     → détail
 * PUT    /api/admin/shop-item/{id}     → modifier
 * DELETE /api/admin/shop-item/{id}     → supprimer
 */
#[IsGranted('ROLE_ADMIN')]
class ShopAdminController extends AbstractController
{
    public function __construct(
        private readonly ShopItemRepository      $shopItemRepository,
        private readonly ItemRepository          $itemRepository,
        private readonly ScrollRepository        $scrollRepository,
        private readonly HeroRepository          $heroRepository,
        private readonly EventCurrencyRepository $currencyRepository,
        private readonly EntityManagerInterface  $em,
    ) {}

    #[Route('/api/admin/shop-items', name: 'api_admin_shop_items_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->shopItemRepository->findBy([], ['category' => 'ASC', 'sortOrder' => 'ASC', 'name' => 'ASC']);
        return $this->json(array_map(fn($s) => $this->serialize($s), $items));
    }

    #[Route('/api/admin/shop-items', name: 'api_admin_shop_items_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }
        $shopItem = new ShopItem();
        $this->hydrate($shopItem, $data);
        $this->em->persist($shopItem);
        $this->em->flush();
        return $this->json($this->serialize($shopItem), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/shop-item/{id}', name: 'api_admin_shop_item_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $shopItem = $this->shopItemRepository->find($id);
        if (!$shopItem) return $this->json(['message' => 'Article introuvable'], Response::HTTP_NOT_FOUND);
        return $this->json($this->serialize($shopItem));
    }

    #[Route('/api/admin/shop-item/{id}', name: 'api_admin_shop_item_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $shopItem = $this->shopItemRepository->find($id);
        if (!$shopItem) return $this->json(['message' => 'Article introuvable'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($shopItem, $data);
        $this->em->flush();
        return $this->json($this->serialize($shopItem));
    }

    #[Route('/api/admin/shop-item/{id}', name: 'api_admin_shop_item_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $shopItem = $this->shopItemRepository->find($id);
        if (!$shopItem) return $this->json(['message' => 'Article introuvable'], Response::HTTP_NOT_FOUND);
        $this->em->remove($shopItem);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Hydratation ───────────────────────────────────────────────────────────

    private function hydrate(ShopItem $s, array $data): void
    {
        if (isset($data['category']))    $s->setCategory((string) $data['category']);
        if (isset($data['name']))        $s->setName((string) $data['name']);
        if (isset($data['description'])) $s->setDescription($data['description'] ?: null);
        if (isset($data['sortOrder']))   $s->setSortOrder((int) $data['sortOrder']);
        if (isset($data['active']))      $s->setActive((bool) $data['active']);

        // Récompense
        if (isset($data['rewardType']) && in_array($data['rewardType'], ShopItem::REWARD_TYPES, true)) {
            $s->setRewardType($data['rewardType']);
        }
        if (isset($data['rewardQuantity'])) $s->setRewardQuantity((int) $data['rewardQuantity']);

        $s->setRewardItem(null);
        $s->setRewardScroll(null);
        $s->setRewardHero(null);
        $s->setRewardEventCurrency(null);
        if ($s->getRewardType() === 'item' && !empty($data['rewardItemId'])) {
            $s->setRewardItem($this->itemRepository->find((int) $data['rewardItemId']));
        }
        if ($s->getRewardType() === 'scroll' && !empty($data['rewardScrollId'])) {
            $s->setRewardScroll($this->scrollRepository->find((int) $data['rewardScrollId']));
        }
        if ($s->getRewardType() === 'hero' && !empty($data['rewardHeroId'])) {
            $s->setRewardHero($this->heroRepository->find((int) $data['rewardHeroId']));
        }
        if ($s->getRewardType() === 'event_currency' && !empty($data['rewardEventCurrencyId'])) {
            $s->setRewardEventCurrency($this->currencyRepository->find((int) $data['rewardEventCurrencyId']));
        }

        // Coût
        if (isset($data['costType']) && in_array($data['costType'], ShopItem::COST_TYPES, true)) {
            $s->setCostType($data['costType']);
        }
        if (isset($data['costQuantity'])) $s->setCostQuantity((int) $data['costQuantity']);

        $s->setCostItem(null);
        $s->setCostScroll(null);
        $s->setCostEventCurrency(null);
        if ($s->getCostType() === 'item' && !empty($data['costItemId'])) {
            $s->setCostItem($this->itemRepository->find((int) $data['costItemId']));
        }
        if ($s->getCostType() === 'scroll' && !empty($data['costScrollId'])) {
            $s->setCostScroll($this->scrollRepository->find((int) $data['costScrollId']));
        }
        if ($s->getCostType() === 'event_currency' && !empty($data['costEventCurrencyId'])) {
            $s->setCostEventCurrency($this->currencyRepository->find((int) $data['costEventCurrencyId']));
        }

        // Limites
        $s->setLimitPerAccount(isset($data['limitPerAccount']) && $data['limitPerAccount'] !== null && $data['limitPerAccount'] !== ''
            ? max(1, (int) $data['limitPerAccount']) : null);

        $period = $data['limitPeriod'] ?? null;
        $s->setLimitPeriod(in_array($period, ShopItem::PERIODS, true) ? $period : null);

        $s->setLimitPerPeriod(isset($data['limitPerPeriod']) && $data['limitPerPeriod'] !== null && $data['limitPerPeriod'] !== ''
            ? max(1, (int) $data['limitPerPeriod']) : null);
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────

    private function serialize(ShopItem $s): array
    {
        return [
            'id'             => $s->getId(),
            'category'       => $s->getCategory(),
            'name'           => $s->getName(),
            'description'    => $s->getDescription(),
            'sortOrder'      => $s->getSortOrder(),
            'active'         => $s->isActive(),
            // Récompense
            'rewardType'     => $s->getRewardType(),
            'rewardQuantity' => $s->getRewardQuantity(),
            'rewardItemId'   => $s->getRewardItem()?->getId(),
            'rewardItemName' => $s->getRewardItem()?->getName(),
            'rewardScrollId' => $s->getRewardScroll()?->getId(),
            'rewardScrollName' => $s->getRewardScroll()?->getName(),
            'rewardHeroId'   => $s->getRewardHero()?->getId(),
            'rewardHeroName' => $s->getRewardHero()?->getName(),
            'rewardEventCurrencyId'   => $s->getRewardEventCurrency()?->getId(),
            'rewardEventCurrencyName' => $s->getRewardEventCurrency()?->getName(),
            'rewardEventCurrencyIcon' => $s->getRewardEventCurrency()?->getIcon(),
            // Coût
            'costType'       => $s->getCostType(),
            'costQuantity'   => $s->getCostQuantity(),
            'costItemId'     => $s->getCostItem()?->getId(),
            'costItemName'   => $s->getCostItem()?->getName(),
            'costScrollId'   => $s->getCostScroll()?->getId(),
            'costScrollName' => $s->getCostScroll()?->getName(),
            'costEventCurrencyId'   => $s->getCostEventCurrency()?->getId(),
            'costEventCurrencyName' => $s->getCostEventCurrency()?->getName(),
            'costEventCurrencyIcon' => $s->getCostEventCurrency()?->getIcon(),
            // Limites
            'limitPerAccount' => $s->getLimitPerAccount(),
            'limitPeriod'     => $s->getLimitPeriod(),
            'limitPerPeriod'  => $s->getLimitPerPeriod(),
        ];
    }
}
