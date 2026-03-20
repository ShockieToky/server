<?php

namespace App\Controller\Api;

use App\Battle\ActiveEffect;
use App\Battle\Combatant;
use App\Battle\TurnEntry;
use App\Entity\User;
use App\Entity\UserStoryProgress;
use App\Passive\CombatContext;
use App\Repository\AttackRepository;
use App\Repository\HeroRepository;
use App\Repository\MonsterRepository;
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
 * Combat manuel pas à pas pour le mode Histoire.
 *
 * POST /api/story/stage/{id}/manual/init  — Initialise le combat (retourne état + storyMeta)
 * POST /api/story/stage/{id}/manual/step  — Exécute une action (retourne nouvel état + storyMeta)
 *
 * Protocole sans session : le client maintient l'état complet et le renvoie à chaque step.
 * Quand toutes les vagues sont battues, la progression est sauvegardée automatiquement.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class StoryManualBattleController extends AbstractController
{
    public function __construct(
        private readonly StoryStageRepository        $stageRepository,
        private readonly UserHeroRepository          $userHeroRepository,
        private readonly UserStoryProgressRepository $progressRepository,
        private readonly AttackRepository            $attackRepository,
        private readonly HeroRepository              $heroRepository,
        private readonly MonsterRepository           $monsterRepository,
        private readonly BattleService               $battleService,
        private readonly BonusResolverService        $bonusResolver,
        private readonly EntityManagerInterface      $em,
    ) {}

    // ── Init ──────────────────────────────────────────────────────────────────

    #[Route('/api/story/stage/{id}/manual/init', name: 'api_story_manual_init', methods: ['POST'])]
    public function init(int $id, Request $request): JsonResponse
    {
        $stage = $this->stageRepository->findWithWaves($id);
        if (!$stage || !$stage->isActive()) {
            return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());

        $body    = json_decode($request->getContent(), true);
        $heroIds = $body['heroIds'] ?? [];

        if (!is_array($heroIds) || empty($heroIds) || count($heroIds) > 4) {
            return $this->json(['message' => 'heroIds doit contenir 1 à 4 IDs'], Response::HTTP_BAD_REQUEST);
        }

        $bonusFactionId   = (int) ($body['bonusFactionId']   ?? 0);
        $bonusOrigineId   = (int) ($body['bonusOrigineId']   ?? 0);
        $enclaveDirection = is_string($body['enclaveDirection'] ?? null) ? $body['enclaveDirection'] : 'nord';

        // ── Chargement et validation des UserHero ─────────────────────────────
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

        // ── Construction des combattants ──────────────────────────────────────
        [$heroCombatants, $maxFoeAccDebuff] = $this->buildHeroCombatants(
            $selectedUserHeroes, $bonusFactionId, $bonusOrigineId, $enclaveDirection,
        );

        $waveCombatants = $this->buildAllWaves($stage, $maxFoeAccDebuff);
        if (empty($waveCombatants)) {
            return $this->json(['message' => "Ce stage n'a pas de vagues configurées"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $firstWaveEnemies = $waveCombatants[0];
        $totalWaves       = count($waveCombatants);

        foreach ($firstWaveEnemies as $c) {
            $c->aiMode = 'story';
        }

        // ── Ordre initial (vitesse décroissante) ──────────────────────────────
        $all = array_merge($heroCombatants, $firstWaveEnemies);
        usort($all, fn(Combatant $a, Combatant $b) => $b->effectiveSpeed() <=> $a->effectiveSpeed());
        $pending = array_map(fn(Combatant $c) => $c->id, $all);

        $state = $this->buildState($heroCombatants, $firstWaveEnemies, $pending, 0, 0, [], 'choose_attack', null);

        return $this->json([
            'state'     => $state,
            'storyMeta' => [
                'stageId'          => $id,
                'waveIndex'        => 0,
                'totalWaves'       => $totalWaves,
                'userHeroIds'      => array_map(fn($uh) => $uh->getId(), $selectedUserHeroes),
                'bonusFactionId'   => $bonusFactionId,
                'bonusOrigineId'   => $bonusOrigineId,
                'enclaveDirection' => $enclaveDirection,
                'maxFoeAccDebuff'  => $maxFoeAccDebuff,
                'waveCleared'      => false,
                'victory'          => false,
                'defeat'           => false,
            ],
        ]);
    }

    // ── Step ──────────────────────────────────────────────────────────────────

    #[Route('/api/story/stage/{id}/manual/step', name: 'api_story_manual_step', methods: ['POST'])]
    public function step(int $id, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!isset($body['state']) || !is_array($body['state'])) {
            return $this->json(['message' => 'État manquant'], Response::HTTP_BAD_REQUEST);
        }

        $storyMeta  = is_array($body['storyMeta'] ?? null) ? $body['storyMeta'] : [];
        $stageId    = (int) ($storyMeta['stageId']    ?? $id);
        $waveIndex  = (int) ($storyMeta['waveIndex']  ?? 0);
        $totalWaves = (int) ($storyMeta['totalWaves'] ?? 1);

        $state    = $body['state'];
        $attackId = isset($body['attackId']) ? (int) $body['attackId'] : null;
        $targetId = isset($body['targetId']) ? (string) $body['targetId'] : null;

        // ── Reconstruction des Combatants ──────────────────────────────────────
        $combatantMap = [];
        foreach ($state['combatants'] ?? [] as $cs) {
            $combatant = $this->buildCombatantFromState($cs);
            if ($combatant !== null) {
                $combatantMap[$cs['id']] = $combatant;
            }
        }

        if (empty($combatantMap)) {
            return $this->json(['message' => 'Combatants invalides'], Response::HTTP_BAD_REQUEST);
        }

        $heroIds   = $state['heroes']  ?? [];
        $enemyIds  = $state['enemies'] ?? [];
        $heroes    = array_values(array_filter(array_map(fn($pid) => $combatantMap[$pid] ?? null, $heroIds)));
        $enemies   = array_values(array_filter(array_map(fn($pid) => $combatantMap[$pid] ?? null, $enemyIds)));
        $pending   = $state['pending'] ?? [];
        $moonPhase = (int) ($state['moonPhase']   ?? 0);
        $actionCnt = (int) ($state['actionCount'] ?? 0);
        $skipStartOfTurn = (bool) ($state['startOfTurnDone'] ?? false);
        $log       = array_map(
            fn($e) => new TurnEntry(
                $e['type'], $e['actorId'], $e['actorName'],
                $e['targetId'] ?? null, $e['targetName'] ?? null, $e['data'] ?? [],
            ),
            $state['log'] ?? [],
        );

        $currentActorId = $state['currentActorId'] ?? ($pending[0] ?? null);
        if ($currentActorId === null) {
            return $this->json(['message' => 'Aucun acteur en cours'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($enemies as $c) {
            $c->aiMode = 'story';
        }

        // ── Exécution du step ──────────────────────────────────────────────────
        $result = $this->battleService->executeManualStep(
            $heroes, $enemies, $currentActorId, $attackId, $log, $moonPhase, $actionCnt, $targetId, $skipStartOfTurn,
        );

        // ── Mise à jour du pending ─────────────────────────────────────────────
        $pending = array_values(array_filter($pending, fn($pid) => $pid !== $currentActorId));
        $pending = array_values(array_filter($pending, fn($pid) => ($combatantMap[$pid] ?? null)?->isAlive()));
        if ($result['extraTurn'] && ($combatantMap[$currentActorId] ?? null)?->isAlive()) {
            array_unshift($pending, $currentActorId);
        }

        // ── Fin de combat ? ────────────────────────────────────────────────────
        if ($result['battleOver']) {
            if (!$result['heroesWin']) {
                // Défaite
                $newState = $this->buildState($heroes, $enemies, [], $moonPhase, $actionCnt, $log, 'finished', 'enemy');
                return $this->json([
                    'state'     => $newState,
                    'storyMeta' => array_merge($storyMeta, ['waveCleared' => false, 'victory' => false, 'defeat' => true]),
                ]);
            }

            // Héros gagnent cette vague
            $nextWaveIndex = $waveIndex + 1;

            if ($nextWaveIndex >= $totalWaves) {
                // Dernière vague → victoire !
                $newState = $this->buildState($heroes, $enemies, [], $moonPhase, $actionCnt, $log, 'finished', 'player');

                // Sauvegarde de la progression
                $stage = $this->stageRepository->find($stageId);
                /** @var User $user */
                $user = $this->em->find(User::class, $this->getUser()->getId());
                if ($stage !== null) {
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

                return $this->json([
                    'state'     => $newState,
                    'storyMeta' => array_merge($storyMeta, [
                        'waveIndex'   => $waveIndex,
                        'waveCleared' => true,
                        'victory'     => true,
                        'defeat'      => false,
                    ]),
                ]);
            }

            // Vagues suivantes — les héros survivants sont conservés
            $maxFoeAccDebuff = (int) ($storyMeta['maxFoeAccDebuff'] ?? 0);
            $stage = $this->stageRepository->findWithWaves($stageId);
            if ($stage === null) {
                return $this->json(['message' => 'Étape introuvable'], Response::HTTP_NOT_FOUND);
            }

            $waveCombatants = $this->buildAllWaves($stage, $maxFoeAccDebuff);
            $nextEnemies    = $waveCombatants[$nextWaveIndex] ?? [];
            if (empty($nextEnemies)) {
                return $this->json(['message' => 'Données de vague manquantes'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            foreach ($nextEnemies as $c) {
                $c->aiMode = 'story';
            }

            $aliveHeroes = array_values(array_filter($heroes, fn(Combatant $c) => $c->isAlive()));
            $all = array_merge($aliveHeroes, $nextEnemies);
            usort($all, fn(Combatant $a, Combatant $b) => $b->effectiveSpeed() <=> $a->effectiveSpeed());
            $newPending = array_map(fn(Combatant $c) => $c->id, $all);

            // Nouveau round : log effacé, compteur réinitialisé
            $newState = $this->buildState($aliveHeroes, $nextEnemies, $newPending, 0, 0, [], 'choose_attack', null);

            return $this->json([
                'state'     => $newState,
                'storyMeta' => array_merge($storyMeta, [
                    'waveIndex'   => $nextWaveIndex,
                    'waveCleared' => true,
                    'victory'     => false,
                    'defeat'      => false,
                ]),
            ]);
        }

        // ── Combat en cours — nouveau round si pending vide ────────────────────
        if (empty($pending)) {
            $aliveAll = array_values(array_filter(array_merge($heroes, $enemies), fn(Combatant $c) => $c->isAlive()));
            usort($aliveAll, fn(Combatant $a, Combatant $b) => $b->effectiveSpeed() <=> $a->effectiveSpeed());
            $pending = array_map(fn(Combatant $c) => $c->id, $aliveAll);
        }

        usort($pending, function ($a, $b) use ($combatantMap) {
            $ca = $combatantMap[$a] ?? null;
            $cb = $combatantMap[$b] ?? null;
            if (!$ca || !$cb) return 0;
            return $cb->effectiveSpeed() <=> $ca->effectiveSpeed();
        });

        $nextActorId = $pending[0] ?? null;

        // ── Pré-traitement du début de tour du prochain acteur ──────────────────
        $startOfTurnDone = false;
        if ($nextActorId !== null) {
            $nextActor = $combatantMap[$nextActorId] ?? null;
            if ($nextActor !== null && $nextActor->isAlive()) {
                $nextAllies = $nextActor->side === 'player' ? $heroes : $enemies;
                $nextFoes   = $nextActor->side === 'player' ? $enemies : $heroes;
                $survived = $this->battleService->processActorStartOfTurn($nextActor, $nextAllies, $nextFoes, $moonPhase, $log);
                if (!$survived) {
                    $pending = array_values(array_filter($pending, fn($pid) => ($combatantMap[$pid] ?? null)?->isAlive()));
                    $aliveH  = !empty(array_filter($heroes,  fn(Combatant $c) => $c->isAlive()));
                    $aliveE  = !empty(array_filter($enemies, fn(Combatant $c) => $c->isAlive()));
                    if (!$aliveH || !$aliveE) {
                        $winner   = $aliveH ? 'player' : 'enemy';
                        $newState = $this->buildState($heroes, $enemies, [], $moonPhase, $actionCnt, $log, 'finished', $winner);
                        return $this->json(['state' => $newState, 'storyMeta' => array_merge($storyMeta, ['waveCleared' => false, 'victory' => $aliveH, 'defeat' => !$aliveH])]);
                    }
                    $nextActorId = $pending[0] ?? null;
                } else {
                    // Acteur vivant mais étourdi/endormi → skip immédiat, sans attendre la réponse client
                    if (!$nextActor->canAct()) {
                        $log[] = new TurnEntry('skip', $nextActor->id, $nextActor->name, data: [
                            'reason' => $nextActor->hasEffect('etourdissement') ? 'etourdissement' : 'sommeil',
                        ]);
                        $nextActor->tickCooldowns();
                        $nextActor->tickEffects();
                        $pending = array_values(array_filter($pending, fn($pid) => $pid !== $nextActor->id));
                        $nextActorId = $pending[0] ?? null;
                    } else {
                        $startOfTurnDone = true;
                    }
                }
            }
        }

        $newState = $this->buildState($heroes, $enemies, $pending, $moonPhase, $actionCnt, $log, 'choose_attack', null, $nextActorId);
        $newState['startOfTurnDone'] = $startOfTurnDone;

        return $this->json([
            'state'     => $newState,
            'storyMeta' => array_merge($storyMeta, ['waveCleared' => false, 'victory' => false, 'defeat' => false]),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Construit les Combatants héros depuis des UserHero (même logique que StoryFightController).
     * Retourne [heroCombatants[], maxFoeAccDebuff].
     *
     * @param \App\Entity\UserHero[] $userHeroes
     * @return array{list<Combatant>, int}
     */
    private function buildHeroCombatants(array $userHeroes, int $bonusFactionId, int $bonusOrigineId, string $enclaveDirection): array
    {
        $heroCombatants  = [];
        $maxFoeAccDebuff = 0;

        // Pré-calcul des passifs d'origine : chaque origine active bénéficie à toute l'équipe
        $teamOrigineCtx = new CombatContext();
        $seenOrigineIds  = [];
        foreach ($userHeroes as $uh) {
            $orig = $uh->getHero()->getOrigine();
            if ($orig === null || in_array($orig->getId(), $seenOrigineIds, true)) continue;
            $seenOrigineIds[] = $orig->getId();
            $origCount = count(array_filter($userHeroes, fn($u) => $u->getHero()->getOrigine()?->getId() === $orig->getId()));
            $tmpCtx = new CombatContext();
            $tmpCtx->alliedOrigineCount = $origCount;
            if ($bonusOrigineId > 0 && $orig->getId() === $bonusOrigineId) {
                $tmpCtx->playerOrigineBonus = 1;
            }
            $this->bonusResolver->applyOriginePassive($orig, $tmpCtx);
            $teamOrigineCtx->applyFrom($tmpCtx);
        }
        if (isset($teamOrigineCtx->passiveTraits['enclave_bonus_pct'])) {
            $teamOrigineCtx->passiveTraits['enclave_direction'] = $enclaveDirection;
        }

        foreach ($userHeroes as $i => $userHero) {
            $hero    = $userHero->getHero();
            $attacks = $this->attackRepository->findByHero($hero);

            $ctx = new CombatContext();
            $ctx->heroIndex = $i;
            $ctx->teamSize  = count($userHeroes);
            if ($hero->getFaction() !== null) {
                foreach ($userHeroes as $other) {
                    $otherHero = $other->getHero();
                    if ($otherHero->getFaction()?->getId() === $hero->getFaction()->getId()) {
                        $ctx->alliedFactionCount++;
                    }
                }
                if ($bonusFactionId > 0 && $hero->getFaction()?->getId() === $bonusFactionId) {
                    $ctx->playerFactionBonus = 2;
                }
                $this->bonusResolver->applyFactionPassive($hero->getFaction(), $ctx);
            }
            // Passifs d'origine : s'appliquent à tous les héros (pré-calculés)
            $ctx->applyFrom($teamOrigineCtx);

            // Bonus des extensions équipées sur les modules du héros
            $extHpPct    = 0.0;
            $extAtkPct   = 0.0;
            $extDefPct   = 0.0;
            $extTccFlat  = 0;
            $extDcFlat   = 0;
            $extVitFlat  = 0;
            $extPrecFlat = 0;
            $extResFlat  = 0;
            foreach ($userHero->getModules() as $module) {
                foreach ($module->getSlots() as $slot) {
                    $ue = $slot->getUserExtension();
                    if ($ue === null) continue;
                    $stat = $ue->getExtension()->getStat();
                    $val  = $ue->getRolledValue();
                    match ($stat) {
                        'HP%'   => $extHpPct    += $val,
                        'ATK%'  => $extAtkPct   += $val,
                        'DEF%'  => $extDefPct   += $val,
                        'TCC%'  => $extTccFlat  += $val,
                        'DC%'   => $extDcFlat   += $val,
                        'VIT+'  => $extVitFlat  += $val,
                        'PREC+' => $extPrecFlat += $val,
                        'RES+'  => $extResFlat  += $val,
                        default => null,
                    };
                }
            }

            // ID format: hero_{userHeroId}_{heroCatalogId}  (permet au step de retrouver les attaques)
            $combatant = new Combatant(
                id:                 'hero_' . $userHero->getId() . '_' . $hero->getId(),
                side:               'player',
                name:               $hero->getName(),
                maxHp:              max(1, (int) round($hero->getHp()     * (1.0 + $extHpPct  / 100.0))),
                baseAttack:         max(1, (int) round($hero->getAttack()  * $ctx->attackMultiplier  * (1.0 + $extAtkPct / 100.0))),
                baseDefense:        max(1, (int) round($hero->getDefense() * $ctx->defenseMultiplier * (1.0 + $extDefPct / 100.0))),
                baseSpeed:          max(1, (int) round($hero->getSpeed()   * $ctx->speedMultiplier) + $ctx->flatSpeedBonus + $extVitFlat),
                critRate:           min(100, $hero->getCritRate()   + (int) round($ctx->critChanceBonus * 100) + $extTccFlat),
                critDamage:         $hero->getCritDamage()          + (int) round($ctx->critDamageBonus * 100) + $extDcFlat,
                accuracy:           $hero->getAccuracy()            + $extPrecFlat,
                resistance:         min(100, $hero->getResistance() + $ctx->resistanceBonus + $extResFlat),
                attacks:            $attacks,
                damageReductionPct: $ctx->damageReductionPct,
                passiveTraits:      $ctx->passiveTraits,
            );

            if ($ctx->initialShieldPct > 0.0) {
                $shieldHp = $combatant->maxHp * $ctx->initialShieldPct / 100.0;
                $combatant->applyEffect(new ActiveEffect(
                    'bouclier', 'Bouclier initial', 'positive', 999, $ctx->initialShieldPct, shieldHp: $shieldHp,
                ));
            }

            if ($ctx->foesAccuracyDebuffPct > $maxFoeAccDebuff) {
                $maxFoeAccDebuff = $ctx->foesAccuracyDebuffPct;
            }

            // Infos faction/origine pour l'affichage côté client
            $combatant->passiveTraits['_factionName'] = $hero->getFaction()?->getName();
            $combatant->passiveTraits['_origineName'] = $hero->getOrigine()?->getName();

            $heroCombatants[] = $combatant;
        }

        $this->bonusResolver->redistributeDinoTrait($heroCombatants);

        return [$heroCombatants, $maxFoeAccDebuff];
    }

    /**
     * @return list<list<Combatant>>
     */
    private function buildAllWaves($stage, int $maxFoeAccDebuff): array
    {
        $waves = [];
        foreach ($stage->getWaves() as $wave) {
            $enemyCombatants = [];
            foreach ($wave->getWaveMonsters() as $wm) {
                $monster = $wm->getMonster();
                if ($monster === null) continue;
                $attacks = $this->attackRepository->findByMonster($monster);

                for ($q = 0; $q < $wm->getQuantity(); $q++) {
                    // ID format: enemy_{waveNum}_{monsterId}_{q}
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
        return $waves;
    }

    /**
     * Reconstruit un Combatant depuis le snapshot d'état côté client.
     * Pour les héros (side='player'), heroId (index 2 de l'ID) est l'ID du catalogue Hero.
     * Pour les ennemis (side='enemy'), l'index 2 de l'ID est l'ID du Monster.
     */
    private function buildCombatantFromState(array $cs): ?Combatant
    {
        $parts     = explode('_', (string) ($cs['id'] ?? ''));
        $idPart2   = isset($parts[2]) ? (int) $parts[2] : 0;
        $side      = (string) ($cs['side'] ?? 'enemy');
        $attacks   = [];

        if ($side === 'player') {
            $heroId = (int) ($cs['heroId'] ?? $idPart2);
            if ($heroId > 0) {
                $hero    = $this->heroRepository->find($heroId);
                $attacks = $hero ? $this->attackRepository->findByHero($hero) : [];
            }
        } else {
            $monsterId = (int) ($cs['monsterId'] ?? $idPart2);
            if ($monsterId > 0) {
                $monster = $this->monsterRepository->find($monsterId);
                $attacks = $monster ? $this->attackRepository->findByMonster($monster) : [];
            }
        }

        $combatant = new Combatant(
            id:                 (string) $cs['id'],
            side:               $side,
            name:               (string) $cs['name'],
            maxHp:              (int) $cs['maxHp'],
            baseAttack:         (int) $cs['baseAttack'],
            baseDefense:        (int) $cs['baseDefense'],
            baseSpeed:          (int) $cs['baseSpeed'],
            critRate:           (int) $cs['critRate'],
            critDamage:         (int) $cs['critDamage'],
            accuracy:           (int) $cs['accuracy'],
            resistance:         (int) $cs['resistance'],
            attacks:            $attacks,
            damageReductionPct: (float) ($cs['damageReductionPct'] ?? 0.0),
            passiveTraits:      (array) ($cs['passiveTraits']      ?? []),
        );

        $combatant->currentHp = (int) ($cs['currentHp'] ?? $cs['maxHp']);

        foreach ($cs['effects'] ?? [] as $eff) {
            $combatant->activeEffects[] = new ActiveEffect(
                name:           (string) $eff['name'],
                label:          (string) $eff['label'],
                polarity:       (string) $eff['polarity'],
                remainingTurns: (int)    $eff['remainingTurns'],
                value:          (float)  $eff['value'],
                sourceId:       (string) ($eff['sourceId'] ?? ''),
                shieldHp:       (float)  ($eff['shieldHp'] ?? 0.0),
                fresh:          false,
            );
        }

        foreach ($cs['cooldowns'] ?? [] as $atkId => $turns) {
            $combatant->cooldowns[(int) $atkId] = (int) $turns;
        }

        return $combatant;
    }

    /**
     * Sérialise l'état complet du combat en tableau JSON.
     *
     * @param Combatant[] $heroes
     * @param Combatant[] $enemies
     * @param string[]    $pending
     * @param TurnEntry[] $log
     */
    private function buildState(
        array   $heroes,
        array   $enemies,
        array   $pending,
        int     $moonPhase,
        int     $actionCount,
        array   $log,
        string  $phase,
        ?string $winner,
        ?string $currentActorId = null,
    ): array {
        $all        = array_merge($heroes, $enemies);
        $combatants = array_map([$this, 'serializeCombatant'], $all);
        $attacks    = [];

        foreach ($all as $c) {
            $attacks[$c->id] = array_map(function ($a) use ($c) {
                return [
                    'id'          => $a->getId(),
                    'name'        => $a->getName(),
                    'description' => $a->getDescription(),
                    'scalingStat' => $a->getScalingStat(),
                    'scalingPct'  => $a->getScalingPct(),
                    'hitCount'    => $a->getHitCount(),
                    'targetType'  => $a->getTargetType(),
                    'cooldown'    => $a->getCooldown(),
                    'slotIndex'   => $a->getSlotIndex(),
                    'onCooldown'  => ($c->cooldowns[$a->getId() ?? 0] ?? 0) > 0,
                    'cooldownLeft'=> (int) ($c->cooldowns[$a->getId() ?? 0] ?? 0),
                ];
            }, $c->attacks);
        }

        return [
            'phase'          => $phase,
            'winner'         => $winner,
            'currentActorId' => $currentActorId ?? ($pending[0] ?? null),
            'pending'        => array_values($pending),
            'heroes'         => array_map(fn(Combatant $c) => $c->id, $heroes),
            'enemies'        => array_map(fn(Combatant $c) => $c->id, $enemies),
            'moonPhase'      => $moonPhase,
            'actionCount'    => $actionCount,
            'log'            => array_map(fn(TurnEntry $e) => $e->toArray(), $log),
            'combatants'     => $combatants,
            'attacks'        => $attacks,
        ];
    }

    private function serializeCombatant(Combatant $c): array
    {
        // hero_{userHeroId}_{heroCatalogId} → parts[2] = heroCatalogId
        // enemy_{waveNum}_{monsterId}_{q}   → parts[2] = monsterId
        $parts   = explode('_', $c->id);
        $part2   = isset($parts[2]) ? (int) $parts[2] : 0;

        return [
            'id'               => $c->id,
            'heroId'           => $c->side === 'player' ? $part2 : 0,
            'monsterId'        => $c->side === 'enemy'  ? $part2 : 0,
            'side'             => $c->side,
            'name'             => $c->name,
            'maxHp'            => $c->maxHp,
            'currentHp'        => $c->currentHp,
            'baseAttack'       => $c->baseAttack,
            'baseDefense'      => $c->baseDefense,
            'baseSpeed'        => $c->baseSpeed,
            'critRate'         => $c->critRate,
            'critDamage'       => $c->critDamage,
            'accuracy'         => $c->accuracy,
            'resistance'       => $c->resistance,
            'damageReductionPct' => $c->damageReductionPct,
            'passiveTraits'    => $c->passiveTraits,
            'effects'          => array_map(fn(ActiveEffect $e) => [
                'name'           => $e->name,
                'label'          => $e->label,
                'polarity'       => $e->polarity,
                'remainingTurns' => $e->remainingTurns,
                'value'          => $e->value,
                'sourceId'       => $e->sourceId,
                'shieldHp'       => $e->shieldHp,
            ], $c->activeEffects),
            'cooldowns'        => $c->cooldowns,
            'isDead'           => !$c->isAlive(),
        ];
    }
}
