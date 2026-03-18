<?php

namespace App\Controller\Api;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\ShopPurchase;
use App\Entity\User;
use App\Entity\UserHero;
use App\Entity\UserInventory;
use App\Repository\HeroRepository;
use App\Repository\ItemRepository;
use App\Repository\ScrollRepository;
use App\Repository\ShopItemRepository;
use App\Repository\ShopPurchaseRepository;
use App\Repository\UserHeroRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Boutique — côté joueur.
 *
 * GET  /api/shop         → liste des articles actifs + solde du joueur + compteurs d'achats
 * POST /api/shop/buy/{id} → acheter un article
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ShopController extends AbstractController
{
    public function __construct(
        private readonly ShopItemRepository     $shopItemRepository,
        private readonly ShopPurchaseRepository $purchaseRepository,
        private readonly ItemRepository         $itemRepository,
        private readonly ScrollRepository       $scrollRepository,
        private readonly HeroRepository         $heroRepository,
        private readonly UserInventoryRepository $inventoryRepository,
        private readonly UserHeroRepository     $userHeroRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    // ── Liste ─────────────────────────────────────────────────────────────────

    #[Route('/api/shop', name: 'api_shop_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user  = $this->em->find(User::class, $this->getUser()->getId());
        $items = $this->shopItemRepository->findActive();

        // Compteur total d'achats par article pour ce joueur
        $totalMap = $this->purchaseRepository->countMapForUser($user);

        // Compteur sur la période en cours (calculé à la volée pour les articles avec une période)
        $periodCounts = [];
        foreach ($items as $item) {
            if ($item->getLimitPeriod() && $item->getLimitPerPeriod() !== null) {
                $periodCounts[$item->getId()] = $this->purchaseRepository
                    ->countByUserAndItemInPeriod($user, $item->getId(), $item->getLimitPeriod());
            }
        }

        return $this->json([
            'wallet' => [
                'gold'         => $user->getGoldToken(),
                'historyToken' => $user->getHistoryToken(),
            ],
            'items' => array_map(function ($item) use ($totalMap, $periodCounts) {
                $totalBought  = $totalMap[$item->getId()] ?? 0;
                $periodBought = $periodCounts[$item->getId()] ?? 0;

                // Calculer le stock restant
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
                    'rewardType'     => $item->getRewardType(),
                    'rewardQuantity' => $item->getRewardQuantity(),
                    'rewardItemId'   => $item->getRewardItem()?->getId(),
                    'rewardItemName' => $item->getRewardItem()?->getName(),
                    'rewardScrollId' => $item->getRewardScroll()?->getId(),
                    'rewardScrollName' => $item->getRewardScroll()?->getName(),
                    'rewardHeroId'   => $item->getRewardHero()?->getId(),
                    'rewardHeroName' => $item->getRewardHero()?->getName(),
                    // Coût
                    'costType'     => $item->getCostType(),
                    'costQuantity' => $item->getCostQuantity(),
                    'costItemId'   => $item->getCostItem()?->getId(),
                    'costItemName' => $item->getCostItem()?->getName(),
                    'costScrollId' => $item->getCostScroll()?->getId(),
                    'costScrollName' => $item->getCostScroll()?->getName(),
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
            }, $items),
        ]);
    }

    // ── Achat ─────────────────────────────────────────────────────────────────

    #[Route('/api/shop/buy/{id}', name: 'api_shop_buy', methods: ['POST'])]
    public function buy(int $id): JsonResponse
    {
        /** @var User $user */
        $user     = $this->em->find(User::class, $this->getUser()->getId());
        $shopItem = $this->shopItemRepository->find($id);

        if (!$shopItem || !$shopItem->isActive()) {
            return $this->json(['message' => 'Article introuvable ou inactif'], Response::HTTP_NOT_FOUND);
        }

        // ── Vérifier les limites ───────────────────────────────────────────────

        if ($shopItem->getLimitPerAccount() !== null) {
            $total = $this->purchaseRepository->countByUserAndItem($user, $shopItem->getId());
            if ($total >= $shopItem->getLimitPerAccount()) {
                return $this->json(['message' => 'Limite de compte atteinte pour cet article'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($shopItem->getLimitPeriod() && $shopItem->getLimitPerPeriod() !== null) {
            $periodCount = $this->purchaseRepository->countByUserAndItemInPeriod($user, $shopItem->getId(), $shopItem->getLimitPeriod());
            if ($periodCount >= $shopItem->getLimitPerPeriod()) {
                $label = $shopItem->getLimitPeriod() === 'daily' ? 'journalière' : 'hebdomadaire';
                return $this->json(['message' => "Limite $label atteinte pour cet article"], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // ── Vérifier et déduire le coût ───────────────────────────────────────

        switch ($shopItem->getCostType()) {
            case 'gold':
                if ($user->getGoldToken() < $shopItem->getCostQuantity()) {
                    return $this->json(['message' => "Pièces d'or insuffisantes"], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $user->setGoldToken($user->getGoldToken() - $shopItem->getCostQuantity());
                break;

            case 'history_token':
                if ($user->getHistoryToken() < $shopItem->getCostQuantity()) {
                    return $this->json(['message' => 'Jetons histoire insuffisants'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $user->setHistoryToken($user->getHistoryToken() - $shopItem->getCostQuantity());
                break;

            case 'item':
                $costItem = $shopItem->getCostItem();
                if (!$costItem) {
                    return $this->json(['message' => 'Item de coût introuvable'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $inv = $this->inventoryRepository->findByUserAndItem($user, $costItem);
                if (!$inv || $inv->getQuantity() < $shopItem->getCostQuantity()) {
                    return $this->json(['message' => "Vous n'avez pas assez de {$costItem->getName()}"], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $inv->setQuantity($inv->getQuantity() - $shopItem->getCostQuantity());
                break;

            case 'scroll':
                $costScroll = $shopItem->getCostScroll();
                if (!$costScroll) {
                    return $this->json(['message' => 'Parchemin de coût introuvable'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $inv = $this->inventoryRepository->findByUserAndScroll($user, $costScroll);
                if (!$inv || $inv->getQuantity() < $shopItem->getCostQuantity()) {
                    return $this->json(['message' => "Vous n'avez pas assez de {$costScroll->getName()}"], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $inv->setQuantity($inv->getQuantity() - $shopItem->getCostQuantity());
                break;
        }

        // ── Donner la récompense ──────────────────────────────────────────────

        $rewardLabel = '';

        switch ($shopItem->getRewardType()) {
            case 'gold':
                $user->setGoldToken($user->getGoldToken() + $shopItem->getRewardQuantity());
                $rewardLabel = "{$shopItem->getRewardQuantity()} pièce(s) d'or";
                break;

            case 'history_token':
                $user->setHistoryToken($user->getHistoryToken() + $shopItem->getRewardQuantity());
                $rewardLabel = "{$shopItem->getRewardQuantity()} jeton(s) histoire";
                break;

            case 'item':
                $rewardItem = $shopItem->getRewardItem();
                if (!$rewardItem) {
                    return $this->json(['message' => "Item de récompense introuvable"], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $inv = $this->inventoryRepository->findByUserAndItem($user, $rewardItem);
                if (!$inv) {
                    $inv = new UserInventory();
                    $inv->setUser($user)->setItem($rewardItem)->setQuantity(0);
                    $this->em->persist($inv);
                }
                $inv->setQuantity($inv->getQuantity() + $shopItem->getRewardQuantity());
                $rewardLabel = "{$shopItem->getRewardQuantity()}× {$rewardItem->getName()}";
                break;

            case 'scroll':
                $rewardScroll = $shopItem->getRewardScroll();
                if (!$rewardScroll) {
                    return $this->json(['message' => "Parchemin de récompense introuvable"], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                $inv = $this->inventoryRepository->findByUserAndScroll($user, $rewardScroll);
                if (!$inv) {
                    $inv = new UserInventory();
                    $inv->setUser($user)->setScroll($rewardScroll)->setQuantity(0);
                    $this->em->persist($inv);
                }
                $inv->setQuantity($inv->getQuantity() + $shopItem->getRewardQuantity());
                $rewardLabel = "{$shopItem->getRewardQuantity()}× {$rewardScroll->getName()}";
                break;

            case 'hero':
                $rewardHero = $shopItem->getRewardHero();
                if (!$rewardHero) {
                    return $this->json(['message' => "Héros de récompense introuvable"], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                // Vérifier si le joueur possède déjà ce héros
                $existing = $this->userHeroRepository->findOneBy(['user' => $user, 'hero' => $rewardHero]);
                if ($existing) {
                    return $this->json(['message' => "Vous possédez déjà ce héros"], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $userHero = new UserHero();
                $userHero->setUser($user)->setHero($rewardHero);
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
                $rewardLabel = $rewardHero->getName();
                break;
        }

        // ── Enregistrer l'achat ───────────────────────────────────────────────

        $purchase = new ShopPurchase();
        $purchase->setUser($user)->setShopItemId($shopItem->getId());
        $this->em->persist($purchase);
        $this->em->flush();

        return $this->json([
            'message' => "Achat réussi : {$rewardLabel}",
            'wallet'  => [
                'gold'         => $user->getGoldToken(),
                'historyToken' => $user->getHistoryToken(),
            ],
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function canBuy(\App\Entity\ShopItem $item, ?int $remainingTotal, ?int $remainingPeriod): bool
    {
        if ($remainingTotal !== null && $remainingTotal <= 0) return false;
        if ($remainingPeriod !== null && $remainingPeriod <= 0) return false;
        return true;
    }
}
