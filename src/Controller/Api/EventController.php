<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\GameEventRepository;
use App\Repository\ShopPurchaseRepository;
use App\Repository\UserEventCurrencyRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Événement côté joueur.
 *
 * GET /api/event/current → renvoie l'événement actif ou null
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class EventController extends AbstractController
{
    public function __construct(
        private readonly GameEventRepository          $eventRepository,
        private readonly UserInventoryRepository      $inventoryRepository,
        private readonly ShopPurchaseRepository       $purchaseRepository,
        private readonly UserEventCurrencyRepository  $userCurrencyRepository,
        private readonly EntityManagerInterface       $em,
    ) {}

    #[Route('/api/event/current', name: 'api_event_current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        $event = $this->eventRepository->findCurrent();

        if ($event === null) {
            return $this->json(null);
        }

        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());

        // ── Parchemins : quantité en inventaire ───────────────────────────────
        $scrollsData = [];
        foreach ($event->getScrolls() as $scroll) {
            $entry    = $this->inventoryRepository->findByUserAndScroll($user, $scroll);
            $scrollsData[] = [
                'id'          => $scroll->getId(),
                'name'        => $scroll->getName(),
                'description' => $scroll->getDescription(),
                'scrollType'  => $scroll->getType(),
                'inventoryId' => $entry?->getId(),
                'quantity'    => $entry?->getQuantity() ?? 0,
            ];
        }

        // ── Boutique : canBuy + compteurs ─────────────────────────────────────
        $shopItemEntities = $event->getShopItems()->toArray();
        $totalMap = $this->purchaseRepository->countMapForUser($user);

        $periodCounts = [];
        foreach ($shopItemEntities as $item) {
            if ($item->getLimitPeriod() && $item->getLimitPerPeriod() !== null) {
                $periodCounts[$item->getId()] = $this->purchaseRepository
                    ->countByUserAndItemInPeriod($user, $item->getId(), $item->getLimitPeriod());
            }
        }

        $shopData = array_map(function ($item) use ($totalMap, $periodCounts) {
            $totalBought  = $totalMap[$item->getId()] ?? 0;
            $periodBought = $periodCounts[$item->getId()] ?? 0;

            $remainingTotal  = $item->getLimitPerAccount() !== null
                ? max(0, $item->getLimitPerAccount() - $totalBought)
                : null;
            $remainingPeriod = ($item->getLimitPeriod() && $item->getLimitPerPeriod() !== null)
                ? max(0, $item->getLimitPerPeriod() - $periodBought)
                : null;

            return [
                'id'          => $item->getId(),
                'category'    => $item->getCategory(),
                'name'        => $item->getName(),
                'description' => $item->getDescription(),
                'sortOrder'   => $item->getSortOrder(),
                // Récompense
                'rewardType'       => $item->getRewardType(),
                'rewardQuantity'   => $item->getRewardQuantity(),
                'rewardItemId'     => $item->getRewardItem()?->getId(),
                'rewardItemName'   => $item->getRewardItem()?->getName(),
                'rewardScrollId'   => $item->getRewardScroll()?->getId(),
                'rewardScrollName' => $item->getRewardScroll()?->getName(),
                'rewardHeroId'     => $item->getRewardHero()?->getId(),
                'rewardHeroName'   => $item->getRewardHero()?->getName(),
                // Coût
                'costType'       => $item->getCostType(),
                'costQuantity'   => $item->getCostQuantity(),
                'costItemId'     => $item->getCostItem()?->getId(),
                'costItemName'   => $item->getCostItem()?->getName(),
                'costScrollId'   => $item->getCostScroll()?->getId(),
                'costScrollName' => $item->getCostScroll()?->getName(),
                'costEventCurrencyId'   => $item->getCostEventCurrency()?->getId(),
                'costEventCurrencyName' => $item->getCostEventCurrency()?->getName(),
                'costEventCurrencyIcon' => $item->getCostEventCurrency()?->getIcon(),
                // Limites
                'limitPerAccount' => $item->getLimitPerAccount(),
                'limitPeriod'     => $item->getLimitPeriod(),
                'limitPerPeriod'  => $item->getLimitPerPeriod(),
                // État joueur
                'totalBought'     => $totalBought,
                'periodBought'    => $periodBought,
                'remainingTotal'  => $remainingTotal,
                'remainingPeriod' => $remainingPeriod,
                'canBuy'          => $this->canBuy($item, $remainingTotal, $remainingPeriod),
            ];
        }, $shopItemEntities);

        // ── Monnaies d'événement ──────────────────────────────────────────────
        $currencyAmountMap = $this->userCurrencyRepository->amountMapForUser($user);
        $currenciesData = array_map(function ($c) use ($currencyAmountMap) {
            return [
                'id'          => $c->getId(),
                'name'        => $c->getName(),
                'description' => $c->getDescription(),
                'icon'        => $c->getIcon(),
                'sortOrder'   => $c->getSortOrder(),
                'amount'      => $currencyAmountMap[$c->getId()] ?? 0,
            ];
        }, $event->getCurrencies()->toArray());

        // ── Donjons ───────────────────────────────────────────────────────────
        $dungeonsData = array_map(fn($d) => [
            'id'          => $d->getId(),
            'name'        => $d->getName(),
            'description' => $d->getDescription(),
            'difficulty'  => $d->getDifficulty(),
            'category'    => $d->getCategory(),
        ], $event->getDungeons()->toArray());

        return $this->json([
            'id'          => $event->getId(),
            'name'        => $event->getName(),
            'description' => $event->getDescription(),
            'startAt'     => $event->getStartAt()?->format('Y-m-d'),
            'endAt'       => $event->getEndAt()?->format('Y-m-d'),
            'dungeons'    => $dungeonsData,
            'scrolls'     => $scrollsData,
            'shopItems'   => $shopData,
            'currencies'  => $currenciesData,
            'wallet'      => [
                'gold'         => $user->getGoldToken(),
                'historyToken' => $user->getHistoryToken(),
            ],
        ]);
    }

    private function canBuy(\App\Entity\ShopItem $item, ?int $remainingTotal, ?int $remainingPeriod): bool
    {
        if ($remainingTotal !== null && $remainingTotal <= 0) return false;
        if ($remainingPeriod !== null && $remainingPeriod <= 0) return false;
        return true;
    }
}
