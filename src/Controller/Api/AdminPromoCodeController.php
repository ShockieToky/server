<?php

namespace App\Controller\Api;

use App\Entity\PromoCode;
use App\Repository\ItemRepository;
use App\Repository\PromoCodeClaimRepository;
use App\Repository\PromoCodeRepository;
use App\Repository\ScrollRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion admin des codes promo.
 *
 * GET    /api/admin/promo-codes         → liste tous les codes (avec compteur claims)
 * POST   /api/admin/promo-codes         → créer un nouveau code
 * PATCH  /api/admin/promo-codes/{id}    → activer / désactiver un code
 * DELETE /api/admin/promo-codes/{id}    → supprimer un code
 *
 * Body POST :
 * {
 *   "code":         "BIENVENUE2026",    (requis, sera mis en majuscules)
 *   "rewards":      [                   (requis, au moins 1)
 *     {"type":"gold_token",     "quantity":100},
 *     {"type":"history_token",  "quantity":50},
 *     {"type":"item",           "itemId":3,   "quantity":2},
 *     {"type":"scroll",         "scrollId":1, "quantity":1}
 *   ],
 *   "validityDays": 30,                 (optionnel — nombre de jours avant expiration)
 *   "maxUses":      100                 (optionnel — nb total de claims autorisés)
 * }
 */
#[Route('/api/admin/promo-codes', name: 'api_admin_promo_codes_')]
#[IsGranted('ROLE_ADMIN')]
class AdminPromoCodeController extends AbstractController
{
    public function __construct(
        private readonly PromoCodeRepository      $promoCodeRepository,
        private readonly PromoCodeClaimRepository $claimRepository,
        private readonly ItemRepository           $itemRepository,
        private readonly ScrollRepository         $scrollRepository,
        private readonly EntityManagerInterface   $em,
    ) {}

    // ── Liste ─────────────────────────────────────────────────────────────────

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $codes = $this->promoCodeRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map(fn(PromoCode $c) => $this->serialize($c), $codes));
    }

    // ── Création ──────────────────────────────────────────────────────────────

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $code = trim($data['code'] ?? '');
        if ($code === '') {
            return $this->json(['message' => 'Le code est requis'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->promoCodeRepository->findByCode($code)) {
            return $this->json(['message' => 'Ce code existe déjà'], Response::HTTP_CONFLICT);
        }

        $rewards = $data['rewards'] ?? [];
        if (!is_array($rewards) || empty($rewards)) {
            return $this->json(['message' => 'Au moins une récompense est requise'], Response::HTTP_BAD_REQUEST);
        }

        $validTypes = ['gold_token', 'history_token', 'item', 'scroll'];
        foreach ($rewards as $r) {
            if (!isset($r['type']) || !in_array($r['type'], $validTypes, true)) {
                return $this->json(
                    ['message' => 'Type de récompense invalide. Types acceptés : ' . implode(', ', $validTypes)],
                    Response::HTTP_BAD_REQUEST
                );
            }
            if ($r['type'] === 'item' && empty($r['itemId'])) {
                return $this->json(['message' => 'itemId requis pour le type "item"'], Response::HTTP_BAD_REQUEST);
            }
            if ($r['type'] === 'scroll' && empty($r['scrollId'])) {
                return $this->json(['message' => 'scrollId requis pour le type "scroll"'], Response::HTTP_BAD_REQUEST);
            }
        }

        $promoCode = new PromoCode();
        $promoCode->setCode($code);
        $promoCode->setRewards($rewards);

        if (!empty($data['validityDays']) && (int) $data['validityDays'] > 0) {
            $exp = new \DateTime();
            $exp->modify('+' . (int) $data['validityDays'] . ' days');
            $promoCode->setExpiresAt($exp);
        }

        if (!empty($data['maxUses']) && (int) $data['maxUses'] > 0) {
            $promoCode->setMaxUses((int) $data['maxUses']);
        }

        $this->em->persist($promoCode);
        $this->em->flush();

        return $this->json($this->serialize($promoCode), Response::HTTP_CREATED);
    }

    // ── Activer / désactiver ──────────────────────────────────────────────────

    #[Route('/{id}', name: 'toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        $promoCode = $this->promoCodeRepository->find($id);
        if (!$promoCode) {
            return $this->json(['message' => 'Code introuvable'], Response::HTTP_NOT_FOUND);
        }

        $promoCode->setIsActive(!$promoCode->isActive());
        $this->em->flush();

        return $this->json($this->serialize($promoCode));
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $promoCode = $this->promoCodeRepository->find($id);
        if (!$promoCode) {
            return $this->json(['message' => 'Code introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($promoCode);
        $this->em->flush();

        return $this->json(['message' => 'Code supprimé']);
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────

    private function serialize(PromoCode $c): array
    {
        // Enrichir les libellés des récompenses pour l'affichage
        $rewardsDisplay = array_map(function (array $r) {
            $label = match ($r['type']) {
                'gold_token'    => ($r['quantity'] ?? 1) . ' Gold Token',
                'history_token' => ($r['quantity'] ?? 1) . ' History Token',
                'item'   => ($r['quantity'] ?? 1) . 'x Item #' . ($r['itemId'] ?? '?'),
                'scroll' => ($r['quantity'] ?? 1) . 'x Parchemin #' . ($r['scrollId'] ?? '?'),
                default  => $r['type'],
            };
            return array_merge($r, ['label' => $label]);
        }, $c->getRewards());

        return [
            'id'         => $c->getId(),
            'code'       => $c->getCode(),
            'rewards'    => $rewardsDisplay,
            'expiresAt'  => $c->getExpiresAt()?->format('Y-m-d H:i:s'),
            'maxUses'    => $c->getMaxUses(),
            'isActive'   => $c->isActive(),
            'createdAt'  => $c->getCreatedAt()->format('Y-m-d H:i:s'),
            'claimCount' => $this->claimRepository->countByPromoCode($c),
        ];
    }
}
