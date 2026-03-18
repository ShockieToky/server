<?php

namespace App\Controller\Api;

use App\Entity\Attack;
use App\Entity\AttackEffect;
use App\Entity\Monster;
use App\Repository\AttackEffectRepository;
use App\Repository\AttackRepository;
use App\Repository\EffectRepository;
use App\Repository\MonsterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD Monstres + leurs attaques (admin).
 *
 * GET    /api/admin/monsters              → liste
 * POST   /api/admin/monsters              → créer
 * PUT    /api/admin/monster/{id}          → modifier
 * DELETE /api/admin/monster/{id}          → supprimer
 * GET    /api/admin/monster/{id}/attacks  → attaques du monstre
 * POST   /api/admin/monster/{id}/attacks  → ajouter une attaque
 * PUT    /api/admin/monster-attack/{id}   → modifier une attaque
 * DELETE /api/admin/monster-attack/{id}   → supprimer une attaque
 */
#[IsGranted('ROLE_ADMIN')]
class MonsterAdminController extends AbstractController
{
    public function __construct(
        private readonly MonsterRepository      $monsterRepository,
        private readonly AttackRepository       $attackRepository,
        private readonly AttackEffectRepository $attackEffectRepository,
        private readonly EffectRepository       $effectRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    // ── Monstres ──────────────────────────────────────────────────────────────

    #[Route('/api/admin/monsters', name: 'api_admin_monsters_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map($this->serializeMonster(...), $this->monsterRepository->findBy([], ['name' => 'ASC'])));
    }

    #[Route('/api/admin/monsters', name: 'api_admin_monsters_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }
        $monster = new Monster();
        $this->hydrateMonster($monster, $data);
        $this->em->persist($monster);
        $this->em->flush();
        return $this->json($this->serializeMonster($monster), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/monster/{id}', name: 'api_admin_monster_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $monster = $this->monsterRepository->find($id);
        if (!$monster) return $this->json(['message' => 'Monstre introuvable'], Response::HTTP_NOT_FOUND);
        $this->hydrateMonster($monster, json_decode($request->getContent(), true) ?? []);
        $this->em->flush();
        return $this->json($this->serializeMonster($monster));
    }

    #[Route('/api/admin/monster/{id}', name: 'api_admin_monster_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $monster = $this->monsterRepository->find($id);
        if (!$monster) return $this->json(['message' => 'Monstre introuvable'], Response::HTTP_NOT_FOUND);
        $this->em->remove($monster);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Attaques d'un monstre ─────────────────────────────────────────────────

    #[Route('/api/admin/monster/{id}/attacks', name: 'api_admin_monster_attacks_list', methods: ['GET'])]
    public function listAttacks(int $id): JsonResponse
    {
        $monster = $this->monsterRepository->find($id);
        if (!$monster) return $this->json(['message' => 'Monstre introuvable'], Response::HTTP_NOT_FOUND);
        return $this->json(array_map($this->serializeAttack(...), $this->attackRepository->findByMonster($monster)));
    }

    #[Route('/api/admin/monster/{id}/attacks', name: 'api_admin_monster_attacks_create', methods: ['POST'])]
    public function createAttack(int $id, Request $request): JsonResponse
    {
        $monster = $this->monsterRepository->find($id);
        if (!$monster) return $this->json(['message' => 'Monstre introuvable'], Response::HTTP_NOT_FOUND);
        $data   = json_decode($request->getContent(), true) ?? [];
        $attack = new Attack();
        $attack->setMonster($monster);
        $this->hydrateAttack($attack, $data);
        $this->em->persist($attack);
        $this->em->flush();
        return $this->json($this->serializeAttack($attack), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/monster-attack/{id}', name: 'api_admin_monster_attack_update', methods: ['PUT'])]
    public function updateAttack(int $id, Request $request): JsonResponse
    {
        $attack = $this->attackRepository->find($id);
        if (!$attack) return $this->json(['message' => 'Attaque introuvable'], Response::HTTP_NOT_FOUND);
        foreach ($attack->getAttackEffects() as $ae) { $this->em->remove($ae); }
        $this->em->flush();
        $this->hydrateAttack($attack, json_decode($request->getContent(), true) ?? []);
        $this->em->flush();
        return $this->json($this->serializeAttack($attack));
    }

    #[Route('/api/admin/monster-attack/{id}', name: 'api_admin_monster_attack_delete', methods: ['DELETE'])]
    public function deleteAttack(int $id): JsonResponse
    {
        $attack = $this->attackRepository->find($id);
        if (!$attack) return $this->json(['message' => 'Attaque introuvable'], Response::HTTP_NOT_FOUND);
        $this->em->remove($attack);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function hydrateMonster(Monster $m, array $data): void
    {
        if (isset($data['name']))        $m->setName((string) $data['name']);
        if (isset($data['description'])) $m->setDescription($data['description'] ?: null);
        if (isset($data['level']))       $m->setLevel((int) $data['level']);
        if (isset($data['type']))        $m->setType((string) $data['type']);
        if (isset($data['attack']))      $m->setAttack((int) $data['attack']);
        if (isset($data['defense']))     $m->setDefense((int) $data['defense']);
        if (isset($data['hp']))          $m->setHp((int) $data['hp']);
        if (isset($data['speed']))       $m->setSpeed((int) $data['speed']);
        if (isset($data['critRate']))    $m->setCritRate((int) $data['critRate']);
        if (isset($data['critDamage']))  $m->setCritDamage((int) $data['critDamage']);
        if (isset($data['accuracy']))    $m->setAccuracy((int) $data['accuracy']);
        if (isset($data['resistance']))  $m->setResistance((int) $data['resistance']);
    }

    private function hydrateAttack(Attack $attack, array $data): void
    {
        if (isset($data['name']))        $attack->setName((string) $data['name']);
        if (isset($data['description'])) $attack->setDescription($data['description'] ?: null);
        if (isset($data['slotIndex']))   $attack->setSlotIndex((int) $data['slotIndex']);
        if (isset($data['hitCount']))    $attack->setHitCount((int) $data['hitCount']);
        if (isset($data['scalingStat'])) $attack->setScalingStat((string) $data['scalingStat']);
        if (isset($data['scalingPct']))  $attack->setScalingPct((int) $data['scalingPct']);
        if (isset($data['targetType']))  $attack->setTargetType((string) $data['targetType']);
        if (isset($data['cooldown']))    $attack->setCooldown((int) $data['cooldown']);

        foreach ($data['effects'] ?? [] as $item) {
            $effect = $this->effectRepository->find((int) ($item['effectId'] ?? 0));
            if (!$effect) continue;
            $ae = new AttackEffect();
            $ae->setEffect($effect);
            $ae->setChance((int) ($item['chance'] ?? 100));
            $ae->setDuration(isset($item['duration']) ? (int) $item['duration'] : null);
            $ae->setValue(isset($item['value']) ? (float) $item['value'] : null);
            $ae->setEffectTarget((string) ($item['effectTarget'] ?? 'target'));
            $ae->setPerHit((bool) ($item['perHit'] ?? false));
            $attack->addAttackEffect($ae);
            $this->em->persist($ae);
        }
    }

    private function serializeMonster(Monster $m): array
    {
        return [
            'id'         => $m->getId(),
            'name'       => $m->getName(),
            'description'=> $m->getDescription(),
            'level'      => $m->getLevel(),
            'type'       => $m->getType(),
            'attack'     => $m->getAttack(),
            'defense'    => $m->getDefense(),
            'hp'         => $m->getHp(),
            'speed'      => $m->getSpeed(),
            'critRate'   => $m->getCritRate(),
            'critDamage' => $m->getCritDamage(),
            'accuracy'   => $m->getAccuracy(),
            'resistance' => $m->getResistance(),
        ];
    }

    private function serializeAttack(Attack $a): array
    {
        return [
            'id'          => $a->getId(),
            'slotIndex'   => $a->getSlotIndex(),
            'name'        => $a->getName(),
            'description' => $a->getDescription(),
            'hitCount'    => $a->getHitCount(),
            'scalingStat' => $a->getScalingStat(),
            'scalingPct'  => $a->getScalingPct(),
            'targetType'  => $a->getTargetType(),
            'cooldown'    => $a->getCooldown(),
            'effects'     => array_map(fn(AttackEffect $ae) => [
                'id'            => $ae->getId(),
                'effect'        => ['id' => $ae->getEffect()->getId(), 'name' => $ae->getEffect()->getName(), 'label' => $ae->getEffect()->getLabel(), 'durationType' => $ae->getEffect()->getDurationType(), 'polarity' => $ae->getEffect()->getPolarity(), 'defaultValue' => $ae->getEffect()->getDefaultValue()],
                'chance'        => $ae->getChance(),
                'duration'      => $ae->getDuration(),
                'value'         => $ae->getValue(),
                'effectTarget'  => $ae->getEffectTarget(),
                'perHit'        => $ae->isPerHit(),
            ], $a->getAttackEffects()->toArray()),
        ];
    }
}
