<?php

namespace App\Controller\Api;

use App\Entity\EventCurrency;
use App\Entity\GameEvent;
use App\Repository\DungeonRepository;
use App\Repository\EventCurrencyRepository;
use App\Repository\GameEventRepository;
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
 * CRUD événements (admin).
 *
 * GET    /api/admin/events        → liste
 * POST   /api/admin/events        → créer
 * GET    /api/admin/events/{id}   → détail
 * PUT    /api/admin/events/{id}   → modifier
 * DELETE /api/admin/events/{id}   → supprimer
 */
#[IsGranted('ROLE_ADMIN')]
class AdminEventController extends AbstractController
{
    public function __construct(
        private readonly GameEventRepository    $eventRepository,
        private readonly DungeonRepository      $dungeonRepository,
        private readonly ScrollRepository       $scrollRepository,
        private readonly ShopItemRepository     $shopItemRepository,
        private readonly EventCurrencyRepository $currencyRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/admin/events', name: 'api_admin_events_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $events = $this->eventRepository->findBy([], ['createdAt' => 'DESC']);
        return $this->json(array_map(fn($e) => $this->serialize($e), $events));
    }

    #[Route('/api/admin/events', name: 'api_admin_events_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }
        $event = new GameEvent();
        $this->hydrate($event, $data);
        $this->em->persist($event);
        $this->em->flush();
        return $this->json($this->serialize($event), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/events/{id}', name: 'api_admin_events_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            return $this->json(['message' => 'Événement introuvable'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serialize($event));
    }

    #[Route('/api/admin/events/{id}', name: 'api_admin_events_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            return $this->json(['message' => 'Événement introuvable'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($event, $data);
        $this->em->flush();
        return $this->json($this->serialize($event));
    }

    #[Route('/api/admin/events/{id}', name: 'api_admin_events_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            return $this->json(['message' => 'Événement introuvable'], Response::HTTP_NOT_FOUND);
        }
        $this->em->remove($event);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Hydratation ───────────────────────────────────────────────────────────

    private function hydrate(GameEvent $event, array $data): void
    {
        if (array_key_exists('name', $data))        $event->setName((string) $data['name']);
        if (array_key_exists('description', $data)) $event->setDescription($data['description'] ?: null);
        if (array_key_exists('isActive', $data))    $event->setIsActive((bool) $data['isActive']);

        if (array_key_exists('startAt', $data)) {
            $event->setStartAt(
                !empty($data['startAt']) ? new \DateTimeImmutable($data['startAt']) : null
            );
        }
        if (array_key_exists('endAt', $data)) {
            $event->setEndAt(
                !empty($data['endAt']) ? new \DateTimeImmutable($data['endAt']) : null
            );
        }

        // ── Monnaies d'événement ──────────────────────────────────────────────
        if (array_key_exists('currencies', $data)) {
            // Index existing currencies by id for update detection
            $existingById = [];
            foreach ($event->getCurrencies() as $c) {
                $existingById[$c->getId()] = $c;
            }

            $seenIds = [];
            foreach ((array) $data['currencies'] as $i => $cData) {
                $existingId = isset($cData['id']) ? (int) $cData['id'] : null;

                if ($existingId && isset($existingById[$existingId])) {
                    $currency = $existingById[$existingId];
                    $seenIds[] = $existingId;
                } else {
                    $currency = new EventCurrency();
                    $event->addCurrency($currency);
                    $this->em->persist($currency);
                }

                if (isset($cData['name']))        $currency->setName((string) $cData['name']);
                if (isset($cData['description'])) $currency->setDescription($cData['description'] ?: null);
                if (isset($cData['icon']))        $currency->setIcon((string) $cData['icon']);
                $currency->setSortOrder($i);
            }

            // Remove currencies that were not included in the payload
            foreach ($existingById as $id => $currency) {
                if (!in_array($id, $seenIds, true)) {
                    $event->removeCurrency($currency);
                    $this->em->remove($currency);
                }
            }
        }

        // ── Relations dungeons / scrolls / shopItems ──────────────────────────
        if (array_key_exists('dungeonIds', $data)) {
            foreach ($event->getDungeons()->toArray() as $d) {
                $event->getDungeons()->removeElement($d);
            }
            foreach ((array) $data['dungeonIds'] as $dId) {
                $dungeon = $this->dungeonRepository->find((int) $dId);
                if ($dungeon) $event->getDungeons()->add($dungeon);
            }
        }

        if (array_key_exists('scrollIds', $data)) {
            foreach ($event->getScrolls()->toArray() as $s) {
                $event->getScrolls()->removeElement($s);
            }
            foreach ((array) $data['scrollIds'] as $sId) {
                $scroll = $this->scrollRepository->find((int) $sId);
                if ($scroll) $event->getScrolls()->add($scroll);
            }
        }

        if (array_key_exists('shopItemIds', $data)) {
            foreach ($event->getShopItems()->toArray() as $si) {
                $event->getShopItems()->removeElement($si);
            }
            foreach ((array) $data['shopItemIds'] as $siId) {
                $shopItem = $this->shopItemRepository->find((int) $siId);
                if ($shopItem) $event->getShopItems()->add($shopItem);
            }
        }
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────

    private function serialize(GameEvent $event): array
    {
        return [
            'id'          => $event->getId(),
            'name'        => $event->getName(),
            'description' => $event->getDescription(),
            'isActive'    => $event->isActive(),
            'isLive'      => $event->isLive(),
            'startAt'     => $event->getStartAt()?->format('Y-m-d'),
            'endAt'       => $event->getEndAt()?->format('Y-m-d'),
            'createdAt'   => $event->getCreatedAt()->format('Y-m-d H:i'),
            'currencies'  => array_map(fn(EventCurrency $c) => [
                'id'          => $c->getId(),
                'name'        => $c->getName(),
                'description' => $c->getDescription(),
                'icon'        => $c->getIcon(),
                'sortOrder'   => $c->getSortOrder(),
            ], $event->getCurrencies()->toArray()),
            'dungeons'    => array_map(fn($d) => [
                'id'   => $d->getId(),
                'name' => $d->getName(),
            ], $event->getDungeons()->toArray()),
            'scrolls'     => array_map(fn($s) => [
                'id'   => $s->getId(),
                'name' => $s->getName(),
            ], $event->getScrolls()->toArray()),
            'shopItems'   => array_map(fn($si) => [
                'id'   => $si->getId(),
                'name' => $si->getName(),
            ], $event->getShopItems()->toArray()),
        ];
    }
}