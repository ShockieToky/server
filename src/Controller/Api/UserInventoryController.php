<?php
namespace App\Controller\Api;

use App\Entity\UserInventory;
use App\Repository\ItemRepository;
use App\Repository\ScrollRepository;
use App\Repository\UserInventoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de l'inventaire de l'utilisateur connecte.
 *
 * GET    /api/me/inventory              → liste tout (optionnel ?type=item|scroll)
 * POST   /api/me/inventory              → ajouter/incrementer un item ou scroll
 * PATCH  /api/me/inventory/{id}         → modifier la quantite
 * DELETE /api/me/inventory/{id}         → retirer une entree
 *
 * Routes admin :
 * GET    /api/users/{userId}/inventory  → inventaire d'un utilisateur specifique
 */
#[Route('/api', name: 'api_inventory_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserInventoryController extends AbstractController
{
    public function __construct(
        private readonly UserInventoryRepository $inventoryRepository,
        private readonly ItemRepository          $itemRepository,
        private readonly ScrollRepository        $scrollRepository,
    ) {
    }

    // ── Inventaire personnel ─────────────────────────────────────────────────

    #[Route('/me/inventory', name: 'me_list', methods: ['GET'])]
    public function myInventory(Request $request): JsonResponse
    {
        $user    = $this->getUser();
        $entries = $this->inventoryRepository->findByUser($user);

        $type = $request->query->get('type');
        if ($type === 'item') {
            $entries = array_filter($entries, fn($e) => $e->getItem() !== null);
        } elseif ($type === 'scroll') {
            $entries = array_filter($entries, fn($e) => $e->getScroll() !== null);
        }

        return $this->json(array_values(array_map($this->serialize(...), $entries)));
    }

    #[Route('/me/inventory', name: 'me_add', methods: ['POST'])]
    public function addToMyInventory(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $user = $this->getUser();

        [$entry, $created] = $this->resolveEntry($user, $data);
        if ($entry === null) {
            return $this->json(['message' => 'Fournir itemId ou scrollId (pas les deux)'], Response::HTTP_BAD_REQUEST);
        }

        $qty = max(1, (int) ($data['quantity'] ?? 1));
        $entry->setQuantity($entry->getQuantity() + $qty);

        $this->inventoryRepository->save($entry, true);

        return $this->json($this->serialize($entry), $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/me/inventory/{id}', name: 'me_update', methods: ['PATCH'])]
    public function updateMyInventory(int $id, Request $request): JsonResponse
    {
        $entry = $this->inventoryRepository->find($id);
        if (!$entry || $entry->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Entree introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        if (isset($data['quantity'])) {
            $entry->setQuantity((int) $data['quantity']);
        }

        if ($entry->getQuantity() <= 0) {
            $this->inventoryRepository->remove($entry, true);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        }

        $this->inventoryRepository->save($entry, true);
        return $this->json($this->serialize($entry));
    }

    #[Route('/me/inventory/{id}', name: 'me_delete', methods: ['DELETE'])]
    public function removeFromMyInventory(int $id): JsonResponse
    {
        $entry = $this->inventoryRepository->find($id);
        if (!$entry || $entry->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Entree introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->inventoryRepository->remove($entry, true);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Route admin ──────────────────────────────────────────────────────────

    #[Route('/users/{userId}/inventory', name: 'admin_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function userInventory(int $userId): JsonResponse
    {
        $user = $this->getUser();
        // On recupere l'utilisateur cible via le repository injecte si besoin
        // Pour l'instant on fait une requete directe via DQL
        $entries = $this->inventoryRepository->createQueryBuilder('ui')
            ->andWhere('IDENTITY(ui.user) = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();

        return $this->json(array_map($this->serialize(...), $entries));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Trouve ou cree l'entree d'inventaire correspondante.
     * Retourne [UserInventory|null, bool $created].
     */
    private function resolveEntry($user, array $data): array
    {
        $hasItem   = isset($data['itemId']);
        $hasScroll = isset($data['scrollId']);

        if ($hasItem === $hasScroll) { // les deux ou aucun
            return [null, false];
        }

        if ($hasItem) {
            $item = $this->itemRepository->find((int) $data['itemId']);
            if (!$item) return [null, false];

            $entry   = $this->inventoryRepository->findByUserAndItem($user, $item);
            $created = false;
            if (!$entry) {
                $entry = (new UserInventory())->setUser($user)->setItem($item)->setQuantity(0);
                $created = true;
            }
            return [$entry, $created];
        }

        $scroll = $this->scrollRepository->find((int) $data['scrollId']);
        if (!$scroll) return [null, false];

        $entry   = $this->inventoryRepository->findByUserAndScroll($user, $scroll);
        $created = false;
        if (!$entry) {
            $entry = (new UserInventory())->setUser($user)->setScroll($scroll)->setQuantity(0);
            $created = true;
        }
        return [$entry, $created];
    }

    private function serialize(UserInventory $entry): array
    {
        $item   = $entry->getItem();
        $scroll = $entry->getScroll();

        return [
            'id'       => $entry->getId(),
            'type'     => $entry->getType(),
            'quantity' => $entry->getQuantity(),
            'item'     => $item   ? ['id' => $item->getId(),   'name' => $item->getName(),   'description' => $item->getDescription(),   'effectType' => $item->getEffectType()]   : null,
            'scroll'   => $scroll ? ['id' => $scroll->getId(), 'name' => $scroll->getName(), 'description' => $scroll->getDescription(), 'scrollType' => $scroll->getType()] : null,
        ];
    }
}