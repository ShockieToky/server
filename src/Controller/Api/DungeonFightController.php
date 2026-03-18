<?php

namespace App\Controller\Api;

use App\Battle\ActiveEffect;
use App\Battle\Combatant;
use App\Entity\User;
use App\Entity\UserDungeonProgress;
use App\Passive\CombatContext;
use App\Repository\AttackRepository;
use App\Repository\DungeonRepository;
use App\Repository\UserDungeonProgressRepository;
use App\Repository\UserHeroRepository;
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
 * POST /api/dungeon/{id}/fight
 *
 * Body JSON : { "heroIds": [1, 2, 3] }
 *
 * Simule le combat de donjon. Les ennemis utilisent l'IA avancée.
 * En cas de victoire, runCount est incrémenté (rejoué autant de fois que voulu).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DungeonFightController extends AbstractController
{
    public function __construct(
        private readonly DungeonRepository              $dungeonRepository,
        private readonly UserHeroRepository             $userHeroRepository,
        private readonly UserDungeonProgressRepository  $progressRepository,
        private readonly AttackRepository               $attackRepository,
        private readonly BattleService                  $battleService,
        private readonly BonusResolverService           $bonusResolver,
        private readonly EntityManagerInterface         $em,
    ) {}

    #[Route('/api/dungeon/{id}/fight', name: 'api_dungeon_fight', methods: ['POST'])]
    public function fight(int $id, Request $request): JsonResponse
    {
        // ── Chargement du donjon ──────────────────────────────────────────────
        $dungeon = $this->dungeonRepository->findWithWaves($id);
        if (!$dungeon || !$dungeon->isActive()) {
            return $this->json(['message' => 'Donjon introuvable'], Response::HTTP_NOT_FOUND);
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
        $heroCombatants  = [];
        $maxFoeAccDebuff = 0;

        foreach ($selectedUserHeroes as $i => $userHero) {
            $hero    = $userHero->getHero();
            $attacks = $this->attackRepository->findByHero($hero);

            $ctx = new CombatContext();
            if ($hero->getFaction() !== null || $hero->getOrigine() !== null) {
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
                if (isset($ctx->passiveTraits['enclave_bonus_pct'])) {
                    $ctx->passiveTraits['enclave_direction'] = $enclaveDirection;
                }
            }

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

            if ($ctx->initialShieldPct > 0.0) {
                $shieldHp = $combatant->maxHp * $ctx->initialShieldPct / 100.0;
                $combatant->applyEffect(new ActiveEffect(
                    'bouclier', 'Bouclier initial', 'positive', 999, $ctx->initialShieldPct, $shieldHp
                ));
            }

            if ($ctx->foesAccuracyDebuffPct > $maxFoeAccDebuff) {
                $maxFoeAccDebuff = $ctx->foesAccuracyDebuffPct;
            }

            $heroCombatants[] = $combatant;
        }

        // ── Construction des vagues ennemies (IA avancée) ─────────────────────
        $waves = [];
        foreach ($dungeon->getWaves() as $wave) {
            $enemyCombatants = [];
            foreach ($wave->getWaveMonsters() as $wm) {
                $monster = $wm->getMonster();
                if ($monster === null) continue;
                $attacks = $this->attackRepository->findByMonster($monster);

                for ($q = 0; $q < $wm->getQuantity(); $q++) {
                    $c = new Combatant(
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
                    // Les ennemis d'un donjon utilisent l'IA avancée
                    $c->aiMode = 'advanced';
                    $enemyCombatants[] = $c;
                }
            }
            if (!empty($enemyCombatants)) {
                $waves[] = $enemyCombatants;
            }
        }

        if (empty($waves)) {
            return $this->json(['message' => "Ce donjon n'a pas de vagues configurées"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ── Simulation ────────────────────────────────────────────────────────
        $result = $this->battleService->simulateStage($heroCombatants, $waves);

        // ── Mise à jour de la progression si victoire ─────────────────────────
        if ($result->victory) {
            $progress = $this->progressRepository->findOneByUserAndDungeon($user, $dungeon);
            if ($progress === null) {
                $progress = new UserDungeonProgress();
                $progress->setUser($user);
                $progress->setDungeon($dungeon);
                $this->em->persist($progress);
            }
            $progress->incrementRunCount();
            $progress->setLastCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        // ── Calcul des récompenses du run ─────────────────────────────────────
        $earnedRewards = [];
        if ($result->victory) {
            foreach ($dungeon->getRewards() as $reward) {
                $qty = $reward->rollQuantity();
                $earnedRewards[] = [
                    'rewardType'  => $reward->getRewardType(),
                    'quantity'    => $qty,
                    'quantityMin' => $reward->getQuantityMin(),
                    'quantityMax' => $reward->getQuantityMax(),
                    'item'        => $reward->getItem()   ? ['id' => $reward->getItem()->getId(),   'name' => $reward->getItem()->getName()]   : null,
                    'scroll'      => $reward->getScroll() ? ['id' => $reward->getScroll()->getId(), 'name' => $reward->getScroll()->getName()] : null,
                ];
            }
        }

        $payload = $result->toArray();
        $payload['rewards'] = $earnedRewards;
        return $this->json($payload);
    }
}
