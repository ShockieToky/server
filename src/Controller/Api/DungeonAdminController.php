<?php

namespace App\Controller\Api;

use App\Entity\Dungeon;
use App\Entity\DungeonReward;
use App\Entity\DungeonWave;
use App\Entity\DungeonWaveMonster;
use App\Repository\DungeonRepository;
use App\Repository\EventCurrencyRepository;
use App\Repository\ItemRepository;
use App\Repository\MonsterRepository;
use App\Repository\ScrollRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD donjons (admin).
 *
 * GET    /api/admin/dungeons         → liste
 * POST   /api/admin/dungeons         → créer
 * GET    /api/admin/dungeon/{id}     → détail (waves + rewards)
 * PUT    /api/admin/dungeon/{id}     → modifier
 * DELETE /api/admin/dungeon/{id}     → supprimer
 */
#[IsGranted('ROLE_ADMIN')]
class DungeonAdminController extends AbstractController
{
    public function __construct(
        private readonly DungeonRepository       $dungeonRepository,
        private readonly MonsterRepository       $monsterRepository,
        private readonly ItemRepository          $itemRepository,
        private readonly ScrollRepository        $scrollRepository,
        private readonly EventCurrencyRepository $currencyRepository,
        private readonly EntityManagerInterface  $em,
    ) {}

    #[Route('/api/admin/dungeons', name: 'api_admin_dungeons_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $dungeons = $this->dungeonRepository->findBy([], ['difficulty' => 'ASC', 'name' => 'ASC']);
        return $this->json(array_map(fn($d) => $this->serialize($d, false), $dungeons));
    }

    #[Route('/api/admin/dungeons', name: 'api_admin_dungeons_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }
        $dungeon = new Dungeon();
        $this->hydrate($dungeon, $data);
        $this->em->persist($dungeon);
        $this->em->flush();
        return $this->json($this->serialize($dungeon, true), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/dungeon/{id}', name: 'api_admin_dungeon_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $dungeon = $this->dungeonRepository->findWithWaves($id);
        if (!$dungeon) return $this->json(['message' => 'Donjon introuvable'], Response::HTTP_NOT_FOUND);
        return $this->json($this->serialize($dungeon, true));
    }

    #[Route('/api/admin/dungeon/{id}', name: 'api_admin_dungeon_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $dungeon = $this->dungeonRepository->findWithWaves($id);
        if (!$dungeon) return $this->json(['message' => 'Donjon introuvable'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true) ?? [];

        foreach ($dungeon->getWaves()   as $wave)   { $this->em->remove($wave); }
        foreach ($dungeon->getRewards() as $reward) { $this->em->remove($reward); }
        $this->em->flush();

        $this->hydrate($dungeon, $data);
        $this->em->flush();
        return $this->json($this->serialize($this->dungeonRepository->findWithWaves($id), true));
    }

    #[Route('/api/admin/dungeon/{id}', name: 'api_admin_dungeon_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $dungeon = $this->dungeonRepository->find($id);
        if (!$dungeon) return $this->json(['message' => 'Donjon introuvable'], Response::HTTP_NOT_FOUND);
        $this->em->remove($dungeon);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Hydratation ───────────────────────────────────────────────────────────

    private function hydrate(Dungeon $dungeon, array $data): void
    {
        if (isset($data['name']))        $dungeon->setName((string) $data['name']);
        if (isset($data['description'])) $dungeon->setDescription($data['description'] ?: null);
        if (isset($data['category']))    $dungeon->setCategory((string) $data['category']);
        if (isset($data['active']))      $dungeon->setActive((bool) $data['active']);
        if (isset($data['difficulty']) && in_array($data['difficulty'], Dungeon::DIFFICULTIES, true)) {
            $dungeon->setDifficulty($data['difficulty']);
        }

        foreach ($data['waves'] ?? [] as $waveData) {
            $wave = new DungeonWave();
            $wave->setWaveNumber((int) ($waveData['waveNumber'] ?? 1));
            $dungeon->addWave($wave);
            $this->em->persist($wave);

            foreach ($waveData['monsters'] ?? [] as $wmData) {
                $monster = $this->monsterRepository->find((int) ($wmData['monsterId'] ?? 0));
                if (!$monster) continue;
                $wm = new DungeonWaveMonster();
                $wm->setMonster($monster);
                $wm->setQuantity((int) ($wmData['quantity'] ?? 1));
                $wave->addWaveMonster($wm);
                $this->em->persist($wm);
            }
        }

        foreach ($data['rewards'] ?? [] as $rData) {
            $reward = new DungeonReward();
            $reward->setRewardType((string) ($rData['rewardType'] ?? 'gold'));
            $reward->setQuantityMin((int) max(1, $rData['quantityMin'] ?? $rData['quantity'] ?? 1));
            $reward->setQuantityMax((int) max(1, $rData['quantityMax'] ?? $rData['quantity'] ?? 1));
            $reward->setDropChance((int) ($rData['dropChance'] ?? 100));
            if ($reward->getQuantityMax() < $reward->getQuantityMin()) {
                $reward->setQuantityMax($reward->getQuantityMin());
            }
            if ($rData['rewardType'] === 'item' && !empty($rData['itemId'])) {
                $reward->setItem($this->itemRepository->find((int) $rData['itemId']));
            }
            if ($rData['rewardType'] === 'scroll' && !empty($rData['scrollId'])) {
                $reward->setScroll($this->scrollRepository->find((int) $rData['scrollId']));
            }
            if ($rData['rewardType'] === 'event_currency' && !empty($rData['eventCurrencyId'])) {
                $reward->setEventCurrency($this->currencyRepository->find((int) $rData['eventCurrencyId']));
            }
            $dungeon->addReward($reward);
            $this->em->persist($reward);
        }
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────

    private function serialize(Dungeon $d, bool $full): array
    {
        $data = [
            'id'          => $d->getId(),
            'name'        => $d->getName(),
            'description' => $d->getDescription(),
            'category'    => $d->getCategory(),
            'difficulty'  => $d->getDifficulty(),
            'active'      => $d->isActive(),
        ];

        if ($full) {
            $data['waves'] = array_map(function (DungeonWave $wave) {
                return [
                    'id'         => $wave->getId(),
                    'waveNumber' => $wave->getWaveNumber(),
                    'monsters'   => array_map(fn(DungeonWaveMonster $wm) => [
                        'monsterId'   => $wm->getMonster()?->getId(),
                        'monsterName' => $wm->getMonster()?->getName(),
                        'quantity'    => $wm->getQuantity(),
                    ], $wave->getWaveMonsters()->toArray()),
                ];
            }, $d->getWaves()->toArray());

            $data['rewards'] = array_map(fn(DungeonReward $r) => [
                'id'          => $r->getId(),
                'rewardType'  => $r->getRewardType(),
                'quantityMin' => $r->getQuantityMin(),
                'quantityMax' => $r->getQuantityMax(),
                'dropChance'  => $r->getDropChance(),
                'itemId'            => $r->getItem()?->getId(),
                'itemName'          => $r->getItem()?->getName(),
                'scrollId'          => $r->getScroll()?->getId(),
                'scrollName'        => $r->getScroll()?->getName(),
                'eventCurrencyId'   => $r->getEventCurrency()?->getId(),
                'eventCurrencyName' => $r->getEventCurrency()?->getName(),
                'eventCurrencyIcon' => $r->getEventCurrency()?->getIcon(),
            ], $d->getRewards()->toArray());
        }

        return $data;
    }
}
