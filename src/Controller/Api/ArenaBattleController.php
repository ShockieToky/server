<?php

namespace App\Controller\Api;

use App\Battle\ActiveEffect;
use App\Battle\Combatant;
use App\Battle\TurnEntry;
use App\Entity\ArenaBattle;
use App\Entity\ArenaDefense;
use App\Entity\User;
use App\Passive\CombatContext;
use App\Repository\ArenaAdminTeamRepository;
use App\Repository\ArenaDefenseRepository;
use App\Repository\ArenaBattleRepository;
use App\Repository\ArenaSeasonPlayerRepository;
use App\Repository\ArenaSeasonRepository;
use App\Repository\AttackRepository;
use App\Repository\HeroRepository;
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
 * Combat d'arène manuel pas à pas (PvP).
 *
 * POST /api/arena/battle/init    → démarre le combat (consomme 1 attaque journalière)
 * POST /api/arena/battle/step    → exécute 1 action (stateless)
 * POST /api/arena/battle/finish  → enregistre le résultat final en base
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ArenaBattleController extends AbstractController
{
    private const DAILY_LIMIT = 10;

    public function __construct(
        private readonly ArenaSeasonRepository       $seasonRepository,
        private readonly ArenaDefenseRepository      $defenseRepository,
        private readonly ArenaAdminTeamRepository    $adminTeamRepository,
        private readonly ArenaSeasonPlayerRepository $playerRepository,
        private readonly ArenaBattleRepository       $battleRepository,
        private readonly UserHeroRepository          $userHeroRepository,
        private readonly HeroRepository              $heroRepository,
        private readonly AttackRepository            $attackRepository,
        private readonly BattleService               $battleService,
        private readonly BonusResolverService        $bonusResolver,
        private readonly EntityManagerInterface      $em,
    ) {}

    // ── Init ──────────────────────────────────────────────────────────────────

    #[Route('/api/arena/battle/init', name: 'api_arena_battle_init', methods: ['POST'])]
    public function init(Request $request): JsonResponse
    {
        $season = $this->seasonRepository->findActive();
        if (!$season) {
            return $this->json(['message' => "Aucune saison d'arène en cours"], Response::HTTP_CONFLICT);
        }

        /** @var User $user */
        $user = $this->em->find(User::class, $this->getUser()->getId());
        $body = json_decode($request->getContent(), true) ?? [];

        $defenseId      = isset($body['defenseId'])   ? (int) $body['defenseId']   : null;
        $adminTeamId    = isset($body['adminTeamId'])  ? (int) $body['adminTeamId'] : null;
        $heroIds        = (array) ($body['heroIds']        ?? []);
        $bonusFactionId = (int)   ($body['leadFactionId']  ?? 0);
        $bonusOrigineId = (int)   ($body['leadOrigineId']  ?? 0);

        if (!$defenseId && !$adminTeamId) {
            return $this->json(['message' => 'defenseId ou adminTeamId est requis'], Response::HTTP_BAD_REQUEST);
        }

        // ── Vérifier les attaques restantes (communes PvP et bot) ──────────────
        $playerEntry = $this->playerRepository->findOrCreate($user, $season, $this->em);
        if (!$playerEntry->tryConsumeAttack(self::DAILY_LIMIT)) {
            return $this->json(['message' => "Plus d'attaques disponibles aujourd'hui"], Response::HTTP_CONFLICT);
        }
        $this->em->flush();

        // ── Valider les héros attaquants ───────────────────────────────────────
        if (empty($heroIds) || count($heroIds) > 4) {
            return $this->json(['message' => 'heroIds doit contenir 1 à 4 héros'], Response::HTTP_BAD_REQUEST);
        }

        $allUserHeroes = $this->userHeroRepository->findByUser($user);
        $heroMap       = [];
        foreach ($allUserHeroes as $uh) { $heroMap[$uh->getId()] = $uh; }

        $attackerHeroes = [];
        foreach ($heroIds as $uhId) {
            if (!isset($heroMap[(int) $uhId])) {
                return $this->json(['message' => "Héros introuvable : $uhId"], Response::HTTP_BAD_REQUEST);
            }
            $attackerHeroes[] = $heroMap[(int) $uhId];
        }

        [$attackerCombatants] = $this->buildTeamCombatants(
            $attackerHeroes, $bonusFactionId, $bonusOrigineId, 'player', 'atk'
        );

        // ── Chemin 1 : équipe bot admin ────────────────────────────────────────
        if ($adminTeamId) {
            $adminTeam = $this->adminTeamRepository->find($adminTeamId);
            if (!$adminTeam || !$adminTeam->isActive() || $adminTeam->isEmpty()) {
                return $this->json(['message' => 'Équipe bot introuvable ou vide'], Response::HTTP_NOT_FOUND);
            }

            [$defenderCombatants] = $this->buildAdminTeamCombatants(
                $adminTeam->getHeroes(),
                (int) $adminTeam->getLeadFactionId(),
                (int) $adminTeam->getLeadOrigineId(),
            );

            foreach ($defenderCombatants as $c) { $c->aiMode = 'advanced'; }

            $all     = array_merge($attackerCombatants, $defenderCombatants);
            usort($all, fn(Combatant $a, Combatant $b) => $b->effectiveSpeed() <=> $a->effectiveSpeed());
            $pending = array_map(fn(Combatant $c) => $c->id, $all);
            $state   = $this->buildState($attackerCombatants, $defenderCombatants, $pending, 0, 0, [], 'choose_attack', null);

            return $this->json([
                'state'     => $state,
                'arenaMeta' => [
                    'isAdminTeam'    => true,
                    'adminTeamId'    => $adminTeam->getId(),
                    'adminTeamName'  => $adminTeam->getName(),
                    'victory'        => false,
                    'defeat'         => false,
                ],
            ]);
        }

        // ── Chemin 2 : défense d'un joueur ────────────────────────────────────
        $defense = $this->defenseRepository->find($defenseId);
        if (!$defense || $defense->isEmpty()) {
            return $this->json(['message' => 'Défense introuvable ou vide'], Response::HTTP_NOT_FOUND);
        }

        $defenderHeroes = $defense->getHeroes();
        [$defenderCombatants] = $this->buildTeamCombatants(
            $defenderHeroes,
            (int) $defense->getLeadFactionId(),
            (int) $defense->getLeadOrigineId(),
            'enemy',
            'def'
        );

        foreach ($defenderCombatants as $c) { $c->aiMode = 'advanced'; }

        $all     = array_merge($attackerCombatants, $defenderCombatants);
        usort($all, fn(Combatant $a, Combatant $b) => $b->effectiveSpeed() <=> $a->effectiveSpeed());
        $pending = array_map(fn(Combatant $c) => $c->id, $all);
        $state   = $this->buildState($attackerCombatants, $defenderCombatants, $pending, 0, 0, [], 'choose_attack', null);

        $defenseSnapshot = [
            'defenseId' => $defense->getId(),
            'slot'      => $defense->getSlotIndex(),
            'heroes'    => array_map(fn($uh) => [
                'name'   => $uh->getHero()->getName(),
                'rarity' => $uh->getHero()->getRarity(),
            ], $defenderHeroes),
            'leadFactionId' => $defense->getLeadFactionId(),
            'leadOrigineId' => $defense->getLeadOrigineId(),
        ];

        return $this->json([
            'state'     => $state,
            'arenaMeta' => [
                'isAdminTeam'     => false,
                'defenseId'       => $defense->getId(),
                'defenderId'      => $defense->getUser()?->getId(),
                'defenderPseudo'  => $defense->getUser()?->getPseudo(),
                'defenseSnapshot' => $defenseSnapshot,
                'victory'         => false,
                'defeat'          => false,
            ],
        ]);
    }

    // ── Step ─────────────────────────────────────────────────────────────────

    #[Route('/api/arena/battle/step', name: 'api_arena_battle_step', methods: ['POST'])]
    public function step(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!isset($body['state']) || !is_array($body['state'])) {
            return $this->json(['message' => 'État manquant'], Response::HTTP_BAD_REQUEST);
        }

        $arenaMeta = is_array($body['arenaMeta'] ?? null) ? $body['arenaMeta'] : [];
        $state     = $body['state'];
        $attackId  = isset($body['attackId']) ? (int) $body['attackId'] : null;
        $targetId  = isset($body['targetId']) ? (string) $body['targetId'] : null;

        // Combat déjà terminé
        if (($state['phase'] ?? '') === 'finished') {
            return $this->json(['state' => $state, 'arenaMeta' => $arenaMeta]);
        }

        // ── Reconstruction des Combatants ──────────────────────────────────────
        $combatantMap = [];
        foreach ($state['combatants'] ?? [] as $cs) {
            $c = $this->buildArenaCombatantFromState($cs);
            if ($c !== null) { $combatantMap[$cs['id']] = $c; }
        }

        if (empty($combatantMap)) {
            return $this->json(['message' => 'Combatants invalides'], Response::HTTP_BAD_REQUEST);
        }

        $heroIds  = $state['heroes']  ?? [];
        $enemyIds = $state['enemies'] ?? [];
        $heroes   = array_values(array_filter(array_map(fn($id) => $combatantMap[$id] ?? null, $heroIds)));
        $enemies  = array_values(array_filter(array_map(fn($id) => $combatantMap[$id] ?? null, $enemyIds)));
        $pending  = $state['pending'] ?? [];

        $moonPhase       = (int)  ($state['moonPhase']      ?? 0);
        $actionCnt       = (int)  ($state['actionCount']    ?? 0);
        $skipStartOfTurn = (bool) ($state['startOfTurnDone'] ?? false);

        $log = array_map(
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

        foreach ($enemies as $c) { $c->aiMode = 'advanced'; }

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

        // ── Fin de combat ──────────────────────────────────────────────────────
        if ($result['battleOver']) {
            $winner   = $result['heroesWin'] ? 'player' : 'enemy';
            $newState = $this->buildState($heroes, $enemies, [], $moonPhase, $actionCnt, $log, 'finished', $winner);
            return $this->json([
                'state'     => $newState,
                'arenaMeta' => array_merge($arenaMeta, [
                    'victory' => $result['heroesWin'],
                    'defeat'  => !$result['heroesWin'],
                ]),
            ]);
        }

        // ── Combat en cours ─────────────────────────────────────────────────────
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

        $nextActorId     = $pending[0] ?? null;
        $startOfTurnDone = false;

        if ($nextActorId !== null) {
            $nextActor = $combatantMap[$nextActorId] ?? null;
            if ($nextActor !== null && $nextActor->isAlive()) {
                $nextAllies = $nextActor->side === 'player' ? $heroes : $enemies;
                $nextFoes   = $nextActor->side === 'player' ? $enemies : $heroes;
                $survived   = $this->battleService->processActorStartOfTurn($nextActor, $nextAllies, $nextFoes, $moonPhase, $log);

                if (!$survived) {
                    $pending     = array_values(array_filter($pending, fn($pid) => ($combatantMap[$pid] ?? null)?->isAlive()));
                    $aliveH      = !empty(array_filter($heroes,  fn(Combatant $c) => $c->isAlive()));
                    $aliveE      = !empty(array_filter($enemies, fn(Combatant $c) => $c->isAlive()));
                    if (!$aliveH || !$aliveE) {
                        $winner   = $aliveH ? 'player' : 'enemy';
                        $newState = $this->buildState($heroes, $enemies, [], $moonPhase, $actionCnt, $log, 'finished', $winner);
                        return $this->json([
                            'state'     => $newState,
                            'arenaMeta' => array_merge($arenaMeta, [
                                'victory' => $aliveH,
                                'defeat'  => !$aliveH,
                            ]),
                        ]);
                    }
                    $nextActorId = $pending[0] ?? null;
                } elseif (!$nextActor->canAct()) {
                    $log[] = new TurnEntry('skip', $nextActor->id, $nextActor->name, data: [
                        'reason' => $nextActor->hasEffect('etourdissement') ? 'etourdissement' : 'sommeil',
                    ]);
                    $nextActor->tickCooldowns();
                    $nextActor->tickEffects();
                    $pending     = array_values(array_filter($pending, fn($pid) => $pid !== $nextActor->id));
                    $nextActorId = $pending[0] ?? null;
                } else {
                    $startOfTurnDone = true;
                }
            }
        }

        $newState                    = $this->buildState($heroes, $enemies, $pending, $moonPhase, $actionCnt, $log, 'choose_attack', null, $nextActorId);
        $newState['startOfTurnDone'] = $startOfTurnDone;

        return $this->json([
            'state'     => $newState,
            'arenaMeta' => array_merge($arenaMeta, ['victory' => false, 'defeat' => false]),
        ]);
    }

    // ── Finish ────────────────────────────────────────────────────────────────

    #[Route('/api/arena/battle/finish', name: 'api_arena_battle_finish', methods: ['POST'])]
    public function finish(Request $request): JsonResponse
    {
        $season = $this->seasonRepository->findActive();
        if (!$season) {
            return $this->json(['message' => "Aucune saison d'arène en cours"], Response::HTTP_CONFLICT);
        }

        /** @var User $user */
        $user      = $this->em->find(User::class, $this->getUser()->getId());
        $body      = json_decode($request->getContent(), true) ?? [];
        $arenaMeta = is_array($body['arenaMeta'] ?? null) ? $body['arenaMeta'] : [];

        $victory = (bool) ($arenaMeta['victory'] ?? false);

        // ── Combat contre une équipe bot : aucune stat modifiée ───────────────
        if (!empty($arenaMeta['isAdminTeam'])) {
            $playerEntry = $this->playerRepository->findOrCreate($user, $season, $this->em);
            return $this->json([
                'recorded'         => false,
                'isAdminTeam'      => true,
                'victory'          => $victory,
                'attacksRemaining' => $playerEntry->getAttacksRemaining(self::DAILY_LIMIT),
            ]);
        }

        // ── Combat PvP : enregistrement complet ────────────────────────────────
        $defenseId       = (int) ($arenaMeta['defenseId'] ?? 0);
        $defenderId      = (int) ($arenaMeta['defenderId'] ?? 0);
        $defenseSnapshot = (array) ($arenaMeta['defenseSnapshot'] ?? []);

        if (!$defenseId || !$defenderId) {
            return $this->json(['message' => 'arenaMeta invalide'], Response::HTTP_BAD_REQUEST);
        }

        $defense  = $this->defenseRepository->find($defenseId);
        $defender = $this->em->find(User::class, $defenderId);

        if (!$defender) {
            return $this->json(['message' => 'Défenseur introuvable'], Response::HTTP_NOT_FOUND);
        }

        // ── Enregistrement du combat ───────────────────────────────────────────
        $battle = (new ArenaBattle())
            ->setSeason($season)
            ->setAttacker($user)
            ->setDefender($defender)
            ->setArenaDefense($defense)
            ->setDefenseSnapshot($defenseSnapshot)
            ->setVictory($victory);

        $this->em->persist($battle);

        // ── Mise à jour des stats attaquant ────────────────────────────────────
        $attackerEntry = $this->playerRepository->findOrCreate($user, $season, $this->em);
        $victory ? $attackerEntry->addWin() : $attackerEntry->addLoss();

        // ── Mise à jour des stats défenseur ───────────────────────────────────
        $defenderEntry = $this->playerRepository->findOrCreate($defender, $season, $this->em);
        $victory ? $defenderEntry->addLoss() : $defenderEntry->addWin();

        $this->em->flush();

        return $this->json([
            'recorded'         => true,
            'isAdminTeam'      => false,
            'victory'          => $victory,
            'attackerWins'     => $attackerEntry->getWins(),
            'attackerLosses'   => $attackerEntry->getLosses(),
            'attacksRemaining' => $attackerEntry->getAttacksRemaining(self::DAILY_LIMIT),
        ]);
    }

    // ── Build hero team ───────────────────────────────────────────────────────

    /**
     * Construit les Combatants depuis une liste de UserHero.
     * Fonctionne pour attaquants (side='player', idPrefix='atk')
     * et défenseurs (side='enemy', idPrefix='def').
     *
     * @param \App\Entity\UserHero[] $userHeroes
     * @return array{list<Combatant>, int}
     */
    private function buildTeamCombatants(
        array  $userHeroes,
        int    $bonusFactionId,
        int    $bonusOrigineId,
        string $side,
        string $idPrefix,
    ): array {
        $combatants      = [];
        $maxFoeAccDebuff = 0;

        // Pré-calcul des passifs d'origine applicables à toute l'équipe
        $teamOrigineCtx = new CombatContext();
        $seenOrigineIds = [];
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

        foreach ($userHeroes as $i => $userHero) {
            $hero    = $userHero->getHero();
            $attacks = $this->attackRepository->findByHero($hero);

            $ctx           = new CombatContext();
            $ctx->heroIndex = $i;
            $ctx->teamSize  = count($userHeroes);

            if ($hero->getFaction() !== null) {
                foreach ($userHeroes as $other) {
                    if ($other->getHero()->getFaction()?->getId() === $hero->getFaction()->getId()) {
                        $ctx->alliedFactionCount++;
                    }
                }
                if ($bonusFactionId > 0 && $hero->getFaction()?->getId() === $bonusFactionId) {
                    $ctx->playerFactionBonus = 2;
                }
                $this->bonusResolver->applyFactionPassive($hero->getFaction(), $ctx);
            }
            $ctx->applyFrom($teamOrigineCtx);

            // Extensions équipées
            $extHpPct = $extAtkPct = $extDefPct = 0.0;
            $extTccFlat = $extDcFlat = $extVitFlat = $extPrecFlat = $extResFlat = 0;
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

            // ID format: {prefix}_{userHeroId}_{heroCatalogId}
            $combatant = new Combatant(
                id:                 "{$idPrefix}_{$userHero->getId()}_{$hero->getId()}",
                side:               $side,
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
                passiveTraits:      array_merge($ctx->passiveTraits, [
                    '_heroId'      => $hero->getId(),
                    '_factionName' => $hero->getFaction()?->getName(),
                    '_origineName' => $hero->getOrigine()?->getName(),
                ]),
            );

            if ($ctx->initialShieldPct > 0.0) {
                $combatant->applyEffect(new ActiveEffect(
                    'bouclier', 'Bouclier initial', 'positive', 999, $ctx->initialShieldPct,
                    shieldHp: $combatant->maxHp * $ctx->initialShieldPct / 100.0,
                ));
            }

            if ($ctx->foesAccuracyDebuffPct > $maxFoeAccDebuff) {
                $maxFoeAccDebuff = $ctx->foesAccuracyDebuffPct;
            }

            $combatants[] = $combatant;
        }

        return [$combatants, $maxFoeAccDebuff];
    }

    // ── Build équipe bot (Hero catalogue, pas de UserHero ni d'extensions) ────

    /**
     * Construit les Combatants pour une équipe bot admin.
     * Utilise directement des Hero du catalogue (pas de UserHero).
     * ID format : bot_{index}_{heroCatalogId}
     *
     * @param \App\Entity\Hero[] $heroes
     * @return array{list<Combatant>, int}
     */
    private function buildAdminTeamCombatants(
        array $heroes,
        int   $bonusFactionId,
        int   $bonusOrigineId,
    ): array {
        $combatants      = [];
        $maxFoeAccDebuff = 0;

        $teamOrigineCtx = new CombatContext();
        $seenOrigineIds = [];
        foreach ($heroes as $hero) {
            $orig = $hero->getOrigine();
            if ($orig === null || in_array($orig->getId(), $seenOrigineIds, true)) continue;
            $seenOrigineIds[] = $orig->getId();
            $origCount = count(array_filter($heroes, fn($h) => $h->getOrigine()?->getId() === $orig->getId()));
            $tmpCtx = new CombatContext();
            $tmpCtx->alliedOrigineCount = $origCount;
            if ($bonusOrigineId > 0 && $orig->getId() === $bonusOrigineId) {
                $tmpCtx->playerOrigineBonus = 1;
            }
            $this->bonusResolver->applyOriginePassive($orig, $tmpCtx);
            $teamOrigineCtx->applyFrom($tmpCtx);
        }

        foreach ($heroes as $i => $hero) {
            $attacks = $this->attackRepository->findByHero($hero);

            $ctx           = new CombatContext();
            $ctx->heroIndex = $i;
            $ctx->teamSize  = count($heroes);

            if ($hero->getFaction() !== null) {
                foreach ($heroes as $other) {
                    if ($other->getFaction()?->getId() === $hero->getFaction()->getId()) {
                        $ctx->alliedFactionCount++;
                    }
                }
                if ($bonusFactionId > 0 && $hero->getFaction()?->getId() === $bonusFactionId) {
                    $ctx->playerFactionBonus = 2;
                }
                $this->bonusResolver->applyFactionPassive($hero->getFaction(), $ctx);
            }
            $ctx->applyFrom($teamOrigineCtx);

            $combatant = new Combatant(
                id:                 "bot_{$i}_{$hero->getId()}",
                side:               'enemy',
                name:               $hero->getName(),
                maxHp:              max(1, (int) round($hero->getHp()      * 1.0)),
                baseAttack:         max(1, (int) round($hero->getAttack()  * $ctx->attackMultiplier)),
                baseDefense:        max(1, (int) round($hero->getDefense() * $ctx->defenseMultiplier)),
                baseSpeed:          max(1, (int) round($hero->getSpeed()   * $ctx->speedMultiplier) + $ctx->flatSpeedBonus),
                critRate:           min(100, $hero->getCritRate()   + (int) round($ctx->critChanceBonus * 100)),
                critDamage:         $hero->getCritDamage()          + (int) round($ctx->critDamageBonus * 100),
                accuracy:           $hero->getAccuracy(),
                resistance:         min(100, $hero->getResistance() + $ctx->resistanceBonus),
                attacks:            $attacks,
                damageReductionPct: $ctx->damageReductionPct,
                passiveTraits:      array_merge($ctx->passiveTraits, [
                    '_heroId'      => $hero->getId(),
                    '_factionName' => $hero->getFaction()?->getName(),
                    '_origineName' => $hero->getOrigine()?->getName(),
                ]),
            );

            if ($ctx->initialShieldPct > 0.0) {
                $combatant->applyEffect(new ActiveEffect(
                    'bouclier', 'Bouclier initial', 'positive', 999, $ctx->initialShieldPct,
                    shieldHp: $combatant->maxHp * $ctx->initialShieldPct / 100.0,
                ));
            }

            if ($ctx->foesAccuracyDebuffPct > $maxFoeAccDebuff) {
                $maxFoeAccDebuff = $ctx->foesAccuracyDebuffPct;
            }

            $combatants[] = $combatant;
        }

        return [$combatants, $maxFoeAccDebuff];
    }

    // ── Reconstruit un Combatant desde le snapshot état client ─────────────────

    private function buildArenaCombatantFromState(array $cs): ?Combatant
    {
        $parts   = explode('_', (string) ($cs['id'] ?? ''));
        // ID format: atk/def_{userHeroId}_{heroCatalogId}
        // parts[2] = heroCatalogId always
        $heroId  = (int) ($cs['passiveTraits']['_heroId'] ?? (isset($parts[2]) ? (int) $parts[2] : 0));
        $side    = (string) ($cs['side'] ?? 'enemy');
        $attacks = [];

        if ($heroId > 0) {
            $hero    = $this->heroRepository->find($heroId);
            $attacks = $hero ? $this->attackRepository->findByHero($hero) : [];
        }

        $combatant = new Combatant(
            id:                 (string) $cs['id'],
            side:               $side,
            name:               (string) $cs['name'],
            maxHp:              (int)    $cs['maxHp'],
            baseAttack:         (int)    $cs['baseAttack'],
            baseDefense:        (int)    $cs['baseDefense'],
            baseSpeed:          (int)    $cs['baseSpeed'],
            critRate:           (int)    $cs['critRate'],
            critDamage:         (int)    $cs['critDamage'],
            accuracy:           (int)    $cs['accuracy'],
            resistance:         (int)    $cs['resistance'],
            attacks:            $attacks,
            damageReductionPct: (float)  ($cs['damageReductionPct'] ?? 0.0),
            passiveTraits:      (array)  ($cs['passiveTraits']      ?? []),
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

    // ── Build state ────────────────────────────────────────────────────────────

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
        $combatants = array_map([$this, 'serializeArenaCombatant'], $all);
        $attacks    = [];

        foreach ($all as $c) {
            $attacks[$c->id] = array_map(fn($a) => [
                'id'           => $a->getId(),
                'name'         => $a->getName(),
                'description'  => $a->getDescription(),
                'scalingStat'  => $a->getScalingStat(),
                'scalingPct'   => $a->getScalingPct(),
                'hitCount'     => $a->getHitCount(),
                'targetType'   => $a->getTargetType(),
                'cooldown'     => $a->getCooldown(),
                'slotIndex'    => $a->getSlotIndex(),
                'onCooldown'   => ($c->cooldowns[$a->getId() ?? 0] ?? 0) > 0,
                'cooldownLeft' => (int) ($c->cooldowns[$a->getId() ?? 0] ?? 0),
            ], $c->attacks);
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

    private function serializeArenaCombatant(Combatant $c): array
    {
        // Pour les combattants arena (atk/def), parts[2] est toujours le heroCatalogId.
        // On stocke heroId pour les deux sides pour simplifier la reconstruction.
        $parts  = explode('_', $c->id);
        $heroId = isset($parts[2]) ? (int) $parts[2] : 0;

        return [
            'id'                 => $c->id,
            'heroId'             => $heroId,
            'monsterId'          => 0,
            'side'               => $c->side,
            'name'               => $c->name,
            'maxHp'              => $c->maxHp,
            'currentHp'          => $c->currentHp,
            'baseAttack'         => $c->baseAttack,
            'baseDefense'        => $c->baseDefense,
            'baseSpeed'          => $c->baseSpeed,
            'critRate'           => $c->critRate,
            'critDamage'         => $c->critDamage,
            'accuracy'           => $c->accuracy,
            'resistance'         => $c->resistance,
            'damageReductionPct' => $c->damageReductionPct,
            'passiveTraits'      => $c->passiveTraits,
            'effects'            => array_map(fn(ActiveEffect $e) => [
                'name'           => $e->name,
                'label'          => $e->label,
                'polarity'       => $e->polarity,
                'remainingTurns' => $e->remainingTurns,
                'value'          => $e->value,
                'sourceId'       => $e->sourceId,
                'shieldHp'       => $e->shieldHp,
            ], $c->activeEffects),
            'cooldowns'          => $c->cooldowns,
            'isDead'             => !$c->isAlive(),
        ];
    }
}
