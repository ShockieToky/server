<?php

namespace App\Controller\Api;

use App\Entity\PromoCodeClaim;
use App\Entity\User;
use App\Entity\UserInventory;
use App\Repository\ItemRepository;
use App\Repository\PromoCodeClaimRepository;
use App\Repository\PromoCodeRepository;
use App\Repository\ScrollRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Activation d'un code promo par un joueur authentifié.
 *
 * POST /api/promo-codes/claim
 * Body : { "code": "BIENVENUE2026" }
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PromoCodeClaimController extends AbstractController
{
    public function __construct(
        private readonly PromoCodeRepository      $promoCodeRepository,
        private readonly PromoCodeClaimRepository $claimRepository,
        private readonly ItemRepository           $itemRepository,
        private readonly ScrollRepository         $scrollRepository,
        private readonly UserInventoryRepository  $inventoryRepository,
        private readonly EntityManagerInterface   $em,
    ) {}

    #[Route('/api/promo-codes/claim', name: 'api_promo_codes_claim', methods: ['POST'])]
    public function claim(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());

        $data = json_decode($request->getContent(), true) ?? [];
        $code = strtoupper(trim($data['code'] ?? ''));

        if ($code === '') {
            return $this->json(['message' => 'Code requis'], Response::HTTP_BAD_REQUEST);
        }

        $promoCode = $this->promoCodeRepository->findByCode($code);
        if (!$promoCode) {
            return $this->json(['message' => 'Code invalide'], Response::HTTP_NOT_FOUND);
        }

        if (!$promoCode->isActive()) {
            return $this->json(['message' => 'Ce code n\'est plus actif'], Response::HTTP_GONE);
        }

        if ($promoCode->getExpiresAt() && $promoCode->getExpiresAt() < new \DateTime()) {
            return $this->json(['message' => 'Ce code a expiré'], Response::HTTP_GONE);
        }

        // Vérifier le nombre max de claims global
        if ($promoCode->getMaxUses() !== null) {
            $totalClaims = $this->claimRepository->countByPromoCode($promoCode);
            if ($totalClaims >= $promoCode->getMaxUses()) {
                return $this->json(['message' => 'Ce code a atteint son nombre maximum d\'utilisations'], Response::HTTP_GONE);
            }
        }

        // Un seul claim par utilisateur
        if ($this->claimRepository->hasUserClaimed($user, $promoCode)) {
            return $this->json(['message' => 'Vous avez déjà utilisé ce code'], Response::HTTP_CONFLICT);
        }

        // Distribuer les récompenses
        $appliedRewards = [];
        foreach ($promoCode->getRewards() as $reward) {
            $type = $reward['type'];
            $qty  = max(1, (int) ($reward['quantity'] ?? 1));

            switch ($type) {
                case 'gold_token':
                    $user->setGoldToken($user->getGoldToken() + $qty);
                    $appliedRewards[] = "+{$qty} Gold Token";
                    break;

                case 'history_token':
                    $user->setHistoryToken($user->getHistoryToken() + $qty);
                    $appliedRewards[] = "+{$qty} History Token";
                    break;

                case 'item':
                    $item = $this->itemRepository->find((int) ($reward['itemId'] ?? 0));
                    if ($item) {
                        $inv = $this->inventoryRepository->findOneBy(['user' => $user, 'item' => $item]);
                        if ($inv) {
                            $inv->setQuantity($inv->getQuantity() + $qty);
                        } else {
                            $inv = new UserInventory();
                            $inv->setUser($user)->setItem($item)->setQuantity($qty);
                            $this->em->persist($inv);
                        }
                        $appliedRewards[] = "+{$qty} {$item->getName()}";
                    }
                    break;

                case 'scroll':
                    $scroll = $this->scrollRepository->find((int) ($reward['scrollId'] ?? 0));
                    if ($scroll) {
                        $inv = $this->inventoryRepository->findOneBy(['user' => $user, 'scroll' => $scroll]);
                        if ($inv) {
                            $inv->setQuantity($inv->getQuantity() + $qty);
                        } else {
                            $inv = new UserInventory();
                            $inv->setUser($user)->setScroll($scroll)->setQuantity($qty);
                            $this->em->persist($inv);
                        }
                        $appliedRewards[] = "+{$qty} {$scroll->getName()}";
                    }
                    break;
            }
        }

        // Enregistrer le claim
        $claim = new PromoCodeClaim();
        $claim->setPromoCode($promoCode)->setUser($user);
        $this->em->persist($claim);
        $this->em->flush();

        return $this->json([
            'message' => 'Code activé avec succès !',
            'rewards' => $appliedRewards,
        ]);
    }
}
