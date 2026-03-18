<?php

namespace App\Controller\Api;

use App\Entity\StoryReward;
use App\Entity\StoryStage;
use App\Entity\StoryWave;
use App\Entity\StoryWaveMonster;
use App\Repository\ItemRepository;
use App\Repository\MonsterRepository;
use App\Repository\ScrollRepository;
use App\Repository\StoryStageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD mode histoire (admin).
 *
 * GET    /api/admin/story/stages         → liste
 * POST   /api/admin/story/stages         → créer étape
 * GET    /api/admin/story/stage/{id}     → détail (waves + rewards)
 * PUT    /api/admin/story/stage/{id}     → modifier étape (infos + waves + rewards)
 * DELETE /api/admin/story/stage/{id}     → supprimer
 */
#[IsGranted('ROLE_ADMIN')]
class StoryAdminController extends AbstractController
{
    public function __construct(
        private readonly StoryStageRepository  $stageRepository,
        private readonly MonsterRepository     $monsterRepository,
        private readonly ItemRepository        $itemRepository,
        private readonly ScrollRepository      $scrollRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/admin/story/stages', name: 'api_admin_story_stages_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $stages = $this->stageRepository->findBy([], ['stageNumber' => 'ASC']);
        return $this->json(array_map(fn($s) => $this->serializeStage($s, false), $stages));
    }

    #[Route('/api/admin/story/stages', name: 'api_admin_story_stages_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }
        $stage = new StoryStage();
        $this->hydrateStage($stage, $data);
        $this->em->persist($stage);
        $this->em->flush();
        return $this->json($this->serializeStage($stage, true), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/story/stage/{id}', name: 'api_admin_story_stage_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $stage = $this->stageRepository->findWithWaves($id);
        if (!$stage) return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);
        return $this->json($this->serializeStage($stage, true));
    }

    #[Route('/api/admin/story/stage/{id}', name: 'api_admin_story_stage_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $stage = $this->stageRepository->findWithWaves($id);
        if (!$stage) return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true) ?? [];

        // Supprimer waves + rewards existants avant de recréer
        foreach ($stage->getWaves() as $wave) { $this->em->remove($wave); }
        foreach ($stage->getRewards() as $reward) { $this->em->remove($reward); }
        $this->em->flush();

        $this->hydrateStage($stage, $data);
        $this->em->flush();
        return $this->json($this->serializeStage($this->stageRepository->findWithWaves($id), true));
    }

    #[Route('/api/admin/story/stage/{id}', name: 'api_admin_story_stage_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        if (!$stage) return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);
        $this->em->remove($stage);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Hydratation ───────────────────────────────────────────────────────────

    private function hydrateStage(StoryStage $stage, array $data): void
    {
        if (isset($data['stageNumber'])) $stage->setStageNumber((int) $data['stageNumber']);
        if (isset($data['name']))        $stage->setName((string) $data['name']);
        if (isset($data['description'])) $stage->setDescription($data['description'] ?: null);
        if (isset($data['active']))      $stage->setActive((bool) $data['active']);

        // Vagues (3 max)
        foreach ($data['waves'] ?? [] as $waveData) {
            $wave = new StoryWave();
            $wave->setWaveNumber((int) ($waveData['waveNumber'] ?? 1));
            $stage->addWave($wave);
            $this->em->persist($wave);

            foreach ($waveData['monsters'] ?? [] as $wmData) {
                $monster = $this->monsterRepository->find((int) ($wmData['monsterId'] ?? 0));
                if (!$monster) continue;
                $wm = new StoryWaveMonster();
                $wm->setMonster($monster);
                $wm->setQuantity((int) ($wmData['quantity'] ?? 1));
                $wave->addWaveMonster($wm);
                $this->em->persist($wm);
            }
        }

        // Récompenses
        foreach ($data['rewards'] ?? [] as $rData) {
            $reward = new StoryReward();
            $reward->setRewardType((string) ($rData['rewardType'] ?? 'history_token'));
            $reward->setQuantity((int) ($rData['quantity'] ?? 1));
            if ($rData['rewardType'] === 'item' && !empty($rData['itemId'])) {
                $reward->setItem($this->itemRepository->find((int) $rData['itemId']));
            }
            if ($rData['rewardType'] === 'scroll' && !empty($rData['scrollId'])) {
                $reward->setScroll($this->scrollRepository->find((int) $rData['scrollId']));
            }
            $stage->addReward($reward);
            $this->em->persist($reward);
        }
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────

    private function serializeStage(StoryStage $s, bool $full): array
    {
        $base = [
            'id'          => $s->getId(),
            'stageNumber' => $s->getStageNumber(),
            'name'        => $s->getName(),
            'description' => $s->getDescription(),
            'active'      => $s->isActive(),
        ];
        if (!$full) return $base;

        return array_merge($base, [
            'waves' => array_map(fn(StoryWave $w) => [
                'id'         => $w->getId(),
                'waveNumber' => $w->getWaveNumber(),
                'monsters'   => array_map(fn(StoryWaveMonster $wm) => [
                    'id'       => $wm->getId(),
                    'monsterId'=> $wm->getMonster()?->getId(),
                    'name'     => $wm->getMonster()?->getName(),
                    'level'    => $wm->getMonster()?->getLevel(),
                    'quantity' => $wm->getQuantity(),
                ], $w->getWaveMonsters()->toArray()),
            ], $s->getWaves()->toArray()),
            'rewards' => array_map(fn(StoryReward $r) => [
                'id'         => $r->getId(),
                'rewardType' => $r->getRewardType(),
                'quantity'   => $r->getQuantity(),
                'item'       => $r->getItem() ? ['id' => $r->getItem()->getId(), 'name' => $r->getItem()->getName()] : null,
                'scroll'     => $r->getScroll() ? ['id' => $r->getScroll()->getId(), 'name' => $r->getScroll()->getName()] : null,
            ], $s->getRewards()->toArray()),
        ]);
    }
}
