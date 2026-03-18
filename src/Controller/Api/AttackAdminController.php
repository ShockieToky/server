<?php

namespace App\Controller\Api;

use App\Entity\Attack;
use App\Entity\AttackEffect;
use App\Entity\Effect;
use App\Repository\AttackEffectRepository;
use App\Repository\AttackRepository;
use App\Repository\EffectRepository;
use App\Repository\HeroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD Effect + Attack (admin).
 *
 * GET    /api/admin/effects                → catalogue complet
 * GET    /api/heroes/{heroId}/attacks      → attaques d'un héros (public, auth)
 * GET    /api/admin/attacks/{heroId}       → attaques d'un héros (admin)
 * POST   /api/admin/attacks/{heroId}       → créer une attaque
 * PUT    /api/admin/attacks/{attackId}     → modifier une attaque
 * DELETE /api/admin/attacks/{attackId}     → supprimer une attaque
 */
class AttackAdminController extends AbstractController
{
    public function __construct(
        private readonly EffectRepository       $effectRepository,
        private readonly AttackRepository       $attackRepository,
        private readonly AttackEffectRepository $attackEffectRepository,
        private readonly HeroRepository         $heroRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    // ── Effets ───────────────────────────────────────────────────────────────

    #[Route('/api/admin/effects', name: 'api_admin_effects_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listEffects(): JsonResponse
    {
        return $this->json(array_map($this->serializeEffect(...), $this->effectRepository->findAll()));
    }

    #[Route('/api/admin/effects/seed', name: 'api_admin_effects_seed', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function seedEffects(): JsonResponse
    {
        $created = 0;
        foreach (Effect::CATALOGUE as $name => $meta) {
            if ($this->effectRepository->findByName($name)) continue;
            $e = new Effect();
            $e->setName($name);
            $e->setLabel($meta[0]);
            $e->setDurationType($meta[1]);
            $e->setPolarity($meta[2]);
            $e->setDescription($meta[3]);
            $e->setDefaultValue($meta[4] ?? null);
            $this->em->persist($e);
            $created++;
        }
        $this->em->flush();
        return $this->json(['created' => $created]);
    }

    #[Route('/api/admin/effects', name: 'api_admin_effects_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createEffect(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name']) || empty($data['label'])) {
            return $this->json(['message' => 'name et label sont requis'], Response::HTTP_BAD_REQUEST);
        }
        if ($this->effectRepository->findByName((string) $data['name'])) {
            return $this->json(['message' => 'Un effet avec ce nom existe déjà'], Response::HTTP_CONFLICT);
        }
        $e = new Effect();
        $e->setName((string) $data['name']);
        $e->setLabel((string) $data['label']);
        $e->setDurationType((string) ($data['durationType'] ?? 'duration'));
        $e->setPolarity((string) ($data['polarity'] ?? 'negative'));
        $e->setDescription((string) ($data['description'] ?? ''));
        $e->setDefaultValue(isset($data['defaultValue']) && $data['defaultValue'] !== '' ? (float) $data['defaultValue'] : null);
        $this->em->persist($e);
        $this->em->flush();
        return $this->json($this->serializeEffect($e), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/effect/{id}', name: 'api_admin_effect_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateEffect(int $id, Request $request): JsonResponse
    {
        $e = $this->effectRepository->find($id);
        if (!$e) return $this->json(['message' => 'Effet introuvable'], Response::HTTP_NOT_FOUND);
        $data = json_decode($request->getContent(), true) ?? [];
        if (isset($data['label']))        $e->setLabel((string) $data['label']);
        if (isset($data['durationType'])) $e->setDurationType((string) $data['durationType']);
        if (isset($data['polarity']))     $e->setPolarity((string) $data['polarity']);
        if (isset($data['description']))  $e->setDescription((string) $data['description']);
        if (array_key_exists('defaultValue', $data)) {
            $e->setDefaultValue($data['defaultValue'] !== '' && $data['defaultValue'] !== null ? (float) $data['defaultValue'] : null);
        }
        $this->em->flush();
        return $this->json($this->serializeEffect($e));
    }

    #[Route('/api/admin/effect/{id}', name: 'api_admin_effect_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteEffect(int $id): JsonResponse
    {
        $e = $this->effectRepository->find($id);
        if (!$e) return $this->json(['message' => 'Effet introuvable'], Response::HTTP_NOT_FOUND);
        $this->em->remove($e);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Attaques d'un héros (lecture, utilisateurs authentifiés) ─────────────

    #[Route('/api/heroes/{heroId}/attacks', name: 'api_hero_attacks_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function listByHero(int $heroId): JsonResponse
    {
        $hero = $this->heroRepository->find($heroId);
        if (!$hero) {
            return $this->json(['message' => 'Héros introuvable'], Response::HTTP_NOT_FOUND);
        }
        return $this->json(array_map($this->serializeAttack(...), $this->attackRepository->findByHero($hero)));
    }

    // ── CRUD admin Attaques ──────────────────────────────────────────────────

    #[Route('/api/admin/attacks/{heroId}', name: 'api_admin_attacks_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminListByHero(int $heroId): JsonResponse
    {
        $hero = $this->heroRepository->find($heroId);
        if (!$hero) {
            return $this->json(['message' => 'Héros introuvable'], Response::HTTP_NOT_FOUND);
        }
        return $this->json(array_map($this->serializeAttack(...), $this->attackRepository->findByHero($hero)));
    }

    #[Route('/api/admin/attacks/{heroId}', name: 'api_admin_attacks_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(int $heroId, Request $request): JsonResponse
    {
        $hero = $this->heroRepository->find($heroId);
        if (!$hero) {
            return $this->json(['message' => 'Héros introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $attack = new Attack();
        $attack->setHero($hero);
        $this->hydrateAttack($attack, $data);

        $this->em->persist($attack);
        $this->em->flush();

        return $this->json($this->serializeAttack($attack), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/attack/{attackId}', name: 'api_admin_attack_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $attackId, Request $request): JsonResponse
    {
        $attack = $this->attackRepository->find($attackId);
        if (!$attack) {
            return $this->json(['message' => 'Attaque introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Supprimer les effets actuels avant de les recréer
        foreach ($attack->getAttackEffects() as $ae) {
            $this->em->remove($ae);
        }
        $this->em->flush();

        $this->hydrateAttack($attack, $data);
        $this->em->flush();

        return $this->json($this->serializeAttack($attack));
    }

    #[Route('/api/admin/attack/{attackId}', name: 'api_admin_attack_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $attackId): JsonResponse
    {
        $attack = $this->attackRepository->find($attackId);
        if (!$attack) {
            return $this->json(['message' => 'Attaque introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($attack);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Hydratation ──────────────────────────────────────────────────────────

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
        if (array_key_exists('specialCode', $data)) $attack->setSpecialCode($data['specialCode']);

        // Effets attachés
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

    // ── Sérialisation ────────────────────────────────────────────────────────

    private function serializeEffect(Effect $e): array
    {
        return [
            'id'           => $e->getId(),
            'name'         => $e->getName(),
            'label'        => $e->getLabel(),
            'durationType' => $e->getDurationType(),
            'polarity'     => $e->getPolarity(),
            'description'  => $e->getDescription(),
            'defaultValue' => $e->getDefaultValue(),
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
            'specialCode' => $a->getSpecialCode(),
            'effects'     => array_map(fn(AttackEffect $ae) => [
                'id'            => $ae->getId(),
                'effect'        => $this->serializeEffect($ae->getEffect()),
                'chance'        => $ae->getChance(),
                'duration'      => $ae->getDuration(),
                'value'         => $ae->getValue(),
                'effectiveValue'=> $ae->getEffectiveValue(),
                'effectTarget'  => $ae->getEffectTarget(),
                'perHit'        => $ae->isPerHit(),
            ], $a->getAttackEffects()->toArray()),
        ];
    }
}
