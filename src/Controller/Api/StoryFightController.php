<?php

namespace App\Controller\Api;

use App\Battle\ActiveEffect;
use App\Battle\Combatant;
use App\Battle\BattleResult;
use App\Entity\User;
use App\Entity\UserStoryProgress;
use App\Passive\CombatContext;
use App\Repository\AttackRepository;
use App\Repository\StoryStageRepository;
use App\Repository\UserHeroRepository;
use App\Repository\UserStoryProgressRepository;
use App\Service\BattleService;
use App\Service\BonusResolverService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/story/stage/{id}/fight
 *
 * Body JSON : { "heroIds": [1, 2, 3] }   (IDs de UserHero)
 *
 * Simule le combat, retourne le BattleResult.
 * Si victoire : marque l'étape comme complétée (si pas déjà fait).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class StoryFightController extends AbstractController
{
    public function __construct(
        private readonly StoryStageRepository        $stageRepository,
        private readonly UserHeroRepository          $userHeroRepository,
        private readonly UserStoryProgressRepository $progressRepository,
        private readonly AttackRepository            $attackRepository,
        private readonly BattleService               $battleService,
        private readonly BonusResolverService        $bonusResolver,
        private readonly EntityManagerInterface      $em,
    ) {}

    #[Route('/api/story/stage/{id}/fight', name: 'api_story_stage_fight', methods: ['POST'])]
    public function fight(int $id, Request $request): JsonResponse
    {
        // ── Chargement du stage ───────────────────────────────────────────────
        $stage = $this->stageRepository->findWithWaves($id);
        if (!$stage || !$stage->isActive()) {
            return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());

        // ── Validation du body ────────────────────────────────────────────────
        $body    = json_decode($request->getContent(), true);
        $heroIds = $body['heroIds'] ?? [];

        if (!is_array($heroIds) || empty($heroIds) || count($heroIds) > 4) {
            return $this->json(['message' => 'heroIds doit contenir 1 à 4 IDs'], Response::HTTP_BAD_REQUEST);
        }

        $bonusFactionId   = (int) ($body['bonusFactionId']   ?? 0);
        $bonusOrigineId   = (int) ($body['bonusOrigineId']   ?? 0);
        $enclaveDirection = is_string($body['enclaveDirection'] ?? null) ? $body['enclaveDirection'] : 'nord';

        // ── Chargement des UserHero du joueur ─────────────────────────────────
        $allUserHeroes = $this->userHeroRepository->findByUser($user);
        $heroMap       = [];
        foreach ($allUserHeroes as $uh) {
            $heroMap[$uh->getId()] = $uh;
        }

        $selectedUserHeroes = [];
        foreach ($heroIds as $uhId) {
            if (!isset($heroMap[(int) $uhId])) {
                return $this->json(['message' => "Héros introuvable : $uhId"], Response::HTTP_BAD_REQUEST);
            }
            $selectedUserHeroes[] = $heroMap[(int) $uhId];
        }

        // ── Construction des Combatant héros ──────────────────────────────────
        $teamSize       = count($selectedUserHeroes);
        $heroCombatants = [];
        $maxFoeAccDebuff = 0; // collecte du max foesAccuracyDebuffPct de l'équipe

        foreach ($selectedUserHeroes as $i => $userHero) {
            $hero    = $userHero->getHero();
            $attacks = $this->attackRepository->findByHero($hero);

            // Passifs faction + origine
            $ctx = new CombatContext();
            if ($hero->getFaction() !== null || $hero->getOrigine() !== null) {
                // Compte des alliés de même faction/origine dans l'équipe
                foreach ($selectedUserHeroes as $j => $other) {
                    if ($j === $i) continue;
                    $otherHero = $other->getHero();
                    if ($hero->getFaction() && $otherHero->getFaction()?->getId() === $hero->getFaction()->getId()) {
                        $ctx->alliedFactionCount++;
                    }
                    if ($hero->getOrigine() && $otherHero->getOrigine()?->getId() === $hero->getOrigine()->getId()) {
                        $ctx->alliedOrigineCount++;
                    }
                }
                // Bonus joueur (faction / origine sélectionnée en lobby)
                if ($bonusFactionId > 0 && $hero->getFaction()?->getId() === $bonusFactionId) {
                    $ctx->playerFactionBonus = 2;
                }
                if ($bonusOrigineId > 0 && $hero->getOrigine()?->getId() === $bonusOrigineId) {
                    $ctx->playerOrigineBonus = 1;
                }

                if ($hero->getFaction() !== null && $hero->getOrigine() !== null) {
                    $this->bonusResolver->applyAll($hero->getFaction(), $hero->getOrigine(), $ctx);
                } elseif ($hero->getFaction() !== null) {
                    $this->bonusResolver->applyFactionPassive($hero->getFaction(), $ctx);
                } elseif ($hero->getOrigine() !== null) {
                    $this->bonusResolver->applyOriginePassive($hero->getOrigine(), $ctx);
                }
                // Propagation de la direction Enclave si le passif est actif
                if (isset($ctx->passiveTraits['enclave_bonus_pct'])) {
                    $ctx->passiveTraits['enclave_direction'] = $enclaveDirection;
                }
            }

            // Stats finales (base × passifs)
            $combatant = new Combatant(
                id:                 'hero_' . $userHero->getId(),
                side:               'player',
                name:               $hero->getName(),
                maxHp:              max(1, (int) round($hero->getHp()     * 1.0)),
                baseAttack:         max(1, (int) round($hero->getAttack()  * $ctx->attackMultiplier)),
                baseDefense:        max(1, (int) round($hero->getDefense() * $ctx->defenseMultiplier)),
                baseSpeed:          max(1, (int) round($hero->getSpeed()   * $ctx->speedMultiplier) + $ctx->flatSpeedBonus),
                critRate:           min(100, $hero->getCritRate()   + (int) round($ctx->critChanceBonus * 100)),
                critDamage:         $hero->getCritDamage()          + (int) round($ctx->critDamageBonus * 100),
                accuracy:           $hero->getAccuracy(),
                resistance:         min(100, $hero->getResistance() + $ctx->resistanceBonus),
                attacks:            $attacks,
                damageReductionPct: $ctx->damageReductionPct,
                passiveTraits:      $ctx->passiveTraits,
            );

            // Bouclier initial (Kilima / Maître de l'Eau)
            if ($ctx->initialShieldPct > 0.0) {
                $shieldHp = $combatant->maxHp * $ctx->initialShieldPct / 100.0;
                $combatant->applyEffect(new ActiveEffect(
                    'bouclier', 'Bouclier initial', 'positive', 999, $ctx->initialShieldPct, $shieldHp
                ));
            }

            // Collecte du malus de précision ennemie (Aride)
            if ($ctx->foesAccuracyDebuffPct > $maxFoeAccDebuff) {
                $maxFoeAccDebuff = $ctx->foesAccuracyDebuffPct;
            }

            $heroCombatants[] = $combatant;
        }

        // ── Construction des vagues ennemies ──────────────────────────────────
        $waves = [];
        foreach ($stage->getWaves() as $wave) {
            $enemyCombatants = [];
            foreach ($wave->getWaveMonsters() as $wm) {
                $monster = $wm->getMonster();
                if ($monster === null) continue;
                $attacks = $this->attackRepository->findByMonster($monster);

                for ($q = 0; $q < $wm->getQuantity(); $q++) {
                    $enemyCombatants[] = new Combatant(
                        id:          'enemy_' . $wave->getWaveNumber() . '_' . $monster->getId() . '_' . $q,
                        side:        'enemy',
                        name:        $monster->getName(),
                        maxHp:       max(1, $monster->getHp()),
                        baseAttack:  max(1, $monster->getAttack()),
                        baseDefense: max(0, $monster->getDefense()),
                        baseSpeed:   max(1, $monster->getSpeed()),
                        critRate:    $monster->getCritRate(),
                        critDamage:  $monster->getCritDamage(),
                        accuracy:    max(0, $monster->getAccuracy() - $maxFoeAccDebuff),
                        resistance:  $monster->getResistance(),
                        attacks:     $attacks,
                    );
                }
            }
            if (!empty($enemyCombatants)) {
                $waves[] = $enemyCombatants;
            }
        }

        if (empty($waves)) {
            return $this->json(['message' => "Ce stage n'a pas de vagues configurées"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ── Simulation ────────────────────────────────────────────────────────
        $result = $this->battleService->simulateStage($heroCombatants, $waves);

        // ── Mise à jour de la progression si victoire ─────────────────────────
        if ($result->victory) {
            $progress = $this->progressRepository->findOneByUserAndStage($user, $stage);
            if ($progress === null) {
                $progress = new UserStoryProgress();
                $progress->setUser($user);
                $progress->setStage($stage);
                $this->em->persist($progress);
            }
            if (!$progress->isCompleted()) {
                $progress->setCompletedAt(new \DateTimeImmutable());
                $this->em->flush();
            }
        }

        return $this->json($result->toArray());
    }
}
