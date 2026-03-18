<?php

namespace App\Controller\Api;

use App\Battle\ActiveEffect;
use App\Battle\Combatant;
use App\Battle\TurnEntry;
use App\Entity\Hero;
use App\Passive\CombatContext;
use App\Repository\AttackRepository;
use App\Repository\HeroRepository;
use App\Service\BattleService;
use App\Service\BonusResolverService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Combat manuel pas à pas — le joueur choisit chaque attaque.
 *
 * POST /api/test-combat/manual      — Initialise un combat, retourne l'état initial
 * POST /api/test-combat/manual/step — Exécute une action et retourne l'état mis à jour
 *
 * Le client maintient l'état complet et le renvoie à chaque step (protocole sans session).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ManualBattleController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository       $heroRepository,
        private readonly AttackRepository     $attackRepository,
        private readonly BattleService        $battleService,
        private readonly BonusResolverService $bonusResolver,
    ) {}

    // ── Initialisation ────────────────────────────────────────────────────────

    #[Route('/api/test-combat/manual', name: 'api_manual_battle_init', methods: ['POST'])]
    public function init(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        $teamAConfigs = array_values(array_filter($body['teamA'] ?? [], fn($c) => is_array($c) && !empty($c['heroId'])));
        $teamBConfigs = array_values(array_filter($body['teamB'] ?? [], fn($c) => is_array($c) && !empty($c['heroId'])));

        if (empty($teamAConfigs) || empty($teamBConfigs)) {
            return $this->json(['message' => 'Chaque équipe doit contenir au moins 1 héros'], Response::HTTP_BAD_REQUEST);
        }
        if (count($teamAConfigs) > 4 || count($teamBConfigs) > 4) {
            return $this->json(['message' => 'Maximum 4 héros par équipe'], Response::HTTP_BAD_REQUEST);
        }

        $heroesCombatants = $this->buildCombatants(
            $teamAConfigs, 'player',
            (int) ($body['teamALeaderFactionId'] ?? 0),
            (int) ($body['teamALeaderOrigineId'] ?? 0),
            is_string($body['teamAEnclaveDirection'] ?? null) ? $body['teamAEnclaveDirection'] : 'nord',
        );

        $enemiesCombatants = $this->buildCombatants(
            $teamBConfigs, 'enemy',
            (int) ($body['teamBLeaderFactionId'] ?? 0),
            (int) ($body['teamBLeaderOrigineId'] ?? 0),
            is_string($body['teamBEnclaveDirection'] ?? null) ? $body['teamBEnclaveDirection'] : 'nord',
        );

        if (empty($heroesCombatants) || empty($enemiesCombatants)) {
            return $this->json(['message' => 'Héros introuvables'], Response::HTTP_NOT_FOUND);
        }

        // Zone de test : équipe B contrôlée par l'IA avancée
        foreach ($enemiesCombatants as $c) {
            $c->aiMode = 'advanced';
        }

        $all = array_merge($heroesCombatants, $enemiesCombatants);

        // Ordre initial du premier round (vitesse décroissante)
        usort($all, fn(Combatant $a, Combatant $b) => $b->effectiveSpeed() <=> $a->effectiveSpeed());
        $pending = array_map(fn(Combatant $c) => $c->id, $all);

        $state = $this->buildState(
            $heroesCombatants, $enemiesCombatants,
            $pending,
            moonPhase: 0, actionCount: 0, log: [],
            phase: 'choose_attack',
            winner: null,
        );

        return $this->json($state);
    }

    // ── Step ──────────────────────────────────────────────────────────────────

    #[Route('/api/test-combat/manual/step', name: 'api_manual_battle_step', methods: ['POST'])]
    public function step(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!isset($body['state']) || !is_array($body['state'])) {
            return $this->json(['message' => 'État manquant'], Response::HTTP_BAD_REQUEST);
        }

        $state    = $body['state'];
        $attackId = isset($body['attackId']) ? (int) $body['attackId'] : null;
        $targetId = isset($body['targetId']) ? (string) $body['targetId'] : null;

        // ── Reconstruire les Combatants depuis l'état ──────────────────────────
        $combatantMap = [];
        foreach ($state['combatants'] ?? [] as $cs) {
            $heroId  = (int) ($cs['heroId'] ?? 0);
            $combatant = $this->buildCombatantFromState($cs, $heroId);
            if ($combatant !== null) {
                $combatantMap[$cs['id']] = $combatant;
            }
        }

        if (empty($combatantMap)) {
            return $this->json(['message' => 'Combatants invalides'], Response::HTTP_BAD_REQUEST);
        }

        $heroIds   = $state['heroes']  ?? [];
        $enemyIds  = $state['enemies'] ?? [];
        $heroes    = array_values(array_filter(array_map(fn($id) => $combatantMap[$id] ?? null, $heroIds)));
        $enemies   = array_values(array_filter(array_map(fn($id) => $combatantMap[$id] ?? null, $enemyIds)));
        $pending   = $state['pending']     ?? [];
        $moonPhase = (int) ($state['moonPhase']    ?? 0);
        $actionCnt = (int) ($state['actionCount']  ?? 0);
        $log       = array_map(fn($e) => new TurnEntry($e['type'], $e['actorId'], $e['actorName'], $e['targetId'] ?? null, $e['targetName'] ?? null, $e['data'] ?? []), $state['log'] ?? []);

        $currentActorId = $state['currentActorId'] ?? ($pending[0] ?? null);
        if ($currentActorId === null) {
            return $this->json(['message' => 'Aucun acteur en cours'], Response::HTTP_BAD_REQUEST);
        }

        // ── Exécuter le step ───────────────────────────────────────────────────
        $result = $this->battleService->executeManualStep(
            $heroes, $enemies, $currentActorId, $attackId, $log, $moonPhase, $actionCnt, $targetId,
        );

        // ── Mettre à jour le pending ───────────────────────────────────────────
        // Retirer l'acteur courant du pending
        $pending = array_values(array_filter($pending, fn($id) => $id !== $currentActorId));
        // Retirer les morts
        $pending = array_values(array_filter($pending, fn($id) => ($combatantMap[$id] ?? null)?->isAlive()));
        // Extra tour : réinsérer l'acteur en tête
        if ($result['extraTurn'] && ($combatantMap[$currentActorId] ?? null)?->isAlive()) {
            array_unshift($pending, $currentActorId);
        }

        // ── Fin de combat ? ────────────────────────────────────────────────────
        if ($result['battleOver']) {
            $winner = $result['heroesWin'] ? 'player' : 'enemy';
            $newState = $this->buildState($heroes, $enemies, [], $moonPhase, $actionCnt, $log, 'finished', $winner);
            return $this->json($newState);
        }

        // ── Nouveau round si pending vide ──────────────────────────────────────
        if (empty($pending)) {
            $aliveAll = array_values(array_filter(array_merge($heroes, $enemies), fn(Combatant $c) => $c->isAlive()));
            usort($aliveAll, fn(Combatant $a, Combatant $b) => $b->effectiveSpeed() <=> $a->effectiveSpeed());
            $pending = array_map(fn(Combatant $c) => $c->id, $aliveAll);
        }

        // Re-trier pending par vitesse effective
        usort($pending, function ($a, $b) use ($combatantMap) {
            $ca = $combatantMap[$a] ?? null;
            $cb = $combatantMap[$b] ?? null;
            if (!$ca || !$cb) return 0;
            return $cb->effectiveSpeed() <=> $ca->effectiveSpeed();
        });

        $nextActorId = $pending[0] ?? null;
        $newState    = $this->buildState($heroes, $enemies, $pending, $moonPhase, $actionCnt, $log, 'choose_attack', null, $nextActorId);

        return $this->json($newState);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Construit une liste de Combatants depuis les configs de l'équipe.
     * Logique identique à TestCombatController::buildTeam.
     *
     * @return Combatant[]
     */
    private function buildCombatants(
        array  $configs,
        string $side,
        int    $leaderFactionId,
        int    $leaderOrigineId,
        string $enclaveDirection,
    ): array {
        $combatants = [];

        /** @var array<int, array{Hero, array}> $heroEntities */
        $heroEntities = [];
        foreach ($configs as $cfg) {
            $hero = $this->heroRepository->find((int) ($cfg['heroId'] ?? 0));
            if ($hero !== null) $heroEntities[] = [$hero, $cfg];
        }

        foreach ($heroEntities as $i => [$hero, $cfg]) {
            /** @var Hero $hero */
            $attacks = $this->attackRepository->findByHero($hero);
            $ext     = $this->computeExtBonuses($cfg['extensions'] ?? []);

            $ctx = new CombatContext();
            foreach ($heroEntities as $j => [$other, $_]) {
                if ($j === $i) continue;
                /** @var Hero $other */
                if ($hero->getFaction() && $other->getFaction()?->getId() === $hero->getFaction()->getId()) $ctx->alliedFactionCount++;
                if ($hero->getOrigine() && $other->getOrigine()?->getId() === $hero->getOrigine()->getId()) $ctx->alliedOrigineCount++;
            }
            if ($leaderFactionId > 0 && $hero->getFaction()?->getId() === $leaderFactionId) $ctx->playerFactionBonus = 2;
            if ($leaderOrigineId > 0 && $hero->getOrigine()?->getId() === $leaderOrigineId) $ctx->playerOrigineBonus = 1;
            if ($hero->getFaction() !== null) $this->bonusResolver->applyFactionPassive($hero->getFaction(), $ctx);
            if ($hero->getOrigine()  !== null) $this->bonusResolver->applyOriginePassive($hero->getOrigine(), $ctx);
            if (isset($ctx->passiveTraits['enclave_bonus_pct'])) $ctx->passiveTraits['enclave_direction'] = $enclaveDirection;

            $hp         = max(1, (int) round($hero->getHp()      * (1 + $ext['hpPct']  / 100.0)));
            $attack     = max(1, (int) round($hero->getAttack()   * (1 + $ext['atkPct'] / 100.0) * $ctx->attackMultiplier));
            $defense    = max(1, (int) round($hero->getDefense()  * (1 + $ext['defPct'] / 100.0) * $ctx->defenseMultiplier));
            $speed      = max(1, (int) round($hero->getSpeed()    * $ctx->speedMultiplier) + $ext['vitFlat'] + $ctx->flatSpeedBonus);
            $critRate   = min(100, $hero->getCritRate()    + $ext['tccPct']   + (int) round($ctx->critChanceBonus  * 100));
            $critDamage = $hero->getCritDamage()           + $ext['dcPct']    + (int) round($ctx->critDamageBonus * 100);
            $accuracy   = $hero->getAccuracy()             + $ext['precFlat'];
            $resistance = min(100, $hero->getResistance()  + $ext['resFlat']  + $ctx->resistanceBonus);

            $unitId    = $side . '_hero_' . $hero->getId() . '_' . $i;
            $combatant = new Combatant(
                id:                 $unitId,
                side:               $side,
                name:               $hero->getName(),
                maxHp:              $hp,
                baseAttack:         $attack,
                baseDefense:        $defense,
                baseSpeed:          $speed,
                critRate:           $critRate,
                critDamage:         $critDamage,
                accuracy:           $accuracy,
                resistance:         $resistance,
                attacks:            $attacks,
                damageReductionPct: $ctx->damageReductionPct,
                passiveTraits:      $ctx->passiveTraits,
            );

            if ($ctx->initialShieldPct > 0.0) {
                $shieldHp = $hp * $ctx->initialShieldPct / 100.0;
                $combatant->applyEffect(new ActiveEffect('bouclier', 'Bouclier initial', 'positive', 999, $ctx->initialShieldPct, $shieldHp));
            }

            $combatants[] = $combatant;
        }

        return $combatants;
    }

    /**
     * Construit un Combatant à partir du snapshot d'état (step endpoint).
     * Charge les attaques fraîches depuis la DB.
     */
    private function buildCombatantFromState(array $cs, int $heroId): ?Combatant
    {
        $hero = $heroId > 0 ? $this->heroRepository->find($heroId) : null;
        $attacks = $hero ? $this->attackRepository->findByHero($hero) : [];

        $combatant = new Combatant(
            id:                 (string) $cs['id'],
            side:               (string) $cs['side'],
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
            passiveTraits:      (array) ($cs['passiveTraits'] ?? []),
        );

        $combatant->currentHp = (int) ($cs['currentHp'] ?? $cs['maxHp']);

        foreach ($cs['effects'] ?? [] as $eff) {
            $combatant->activeEffects[] = new ActiveEffect(
                name:           (string) $eff['name'],
                label:          (string) $eff['label'],
                polarity:       (string) $eff['polarity'],
                remainingTurns: (int) $eff['remainingTurns'],
                value:          (float) $eff['value'],
                shieldHp:       (float) ($eff['shieldHp'] ?? 0.0),
            );
        }

        foreach ($cs['cooldowns'] ?? [] as $attackId => $turns) {
            $combatant->cooldowns[(int) $attackId] = (int) $turns;
        }

        return $combatant;
    }

    /**
     * Sérialise un ensemble de Combatants en tableau d'état JSON.
     *
     * @param Combatant[] $heroes
     * @param Combatant[] $enemies
     * @param string[]    $pending
     * @param TurnEntry[] $log
     */
    private function buildState(
        array    $heroes,
        array    $enemies,
        array    $pending,
        int      $moonPhase,
        int      $actionCount,
        array    $log,
        string   $phase,
        ?string  $winner,
        ?string  $currentActorId = null,
    ): array {
        $all          = array_merge($heroes, $enemies);
        $combatants   = array_map([$this, 'serializeCombatant'], $all);
        $attacks      = [];
        foreach ($all as $c) {
            $attacks[$c->id] = array_map(function ($a) use ($c) {
                return [
                    'id'         => $a->getId(),
                    'name'       => $a->getName(),
                    'scalingStat'=> $a->getScalingStat(),
                    'scalingPct' => $a->getScalingPct(),
                    'hitCount'   => $a->getHitCount(),
                    'targetType' => $a->getTargetType(),
                    'cooldown'   => $a->getCooldown(),
                    'slotIndex'  => $a->getSlotIndex(),
                    'onCooldown' => ($c->cooldowns[$a->getId() ?? 0] ?? 0) > 0,
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
        // heroId extrait du format "{side}_hero_{heroId}_{index}"
        $parts  = explode('_', $c->id);
        $heroId = isset($parts[2]) ? (int) $parts[2] : 0;

        return [
            'id'               => $c->id,
            'heroId'           => $heroId,
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
                'shieldHp'       => $e->shieldHp,
            ], $c->activeEffects),
            'cooldowns'        => $c->cooldowns,
            'isDead'           => !$c->isAlive(),
        ];
    }

    /**
     * @param list<array{stat:string,value:float}|null> $extensions
     * @return array{atkPct:float,defPct:float,hpPct:float,tccPct:int,dcPct:int,vitFlat:int,precFlat:int,resFlat:int}
     */
    private function computeExtBonuses(array $extensions): array
    {
        $b = ['atkPct' => 0.0, 'defPct' => 0.0, 'hpPct' => 0.0, 'tccPct' => 0, 'dcPct' => 0, 'vitFlat' => 0, 'precFlat' => 0, 'resFlat' => 0];
        foreach ($extensions as $ext) {
            if (!is_array($ext) || !isset($ext['stat'], $ext['value'])) continue;
            $v = (float) $ext['value'];
            match ($ext['stat']) {
                'ATK%'  => $b['atkPct']  += $v,
                'DEF%'  => $b['defPct']  += $v,
                'HP%'   => $b['hpPct']   += $v,
                'TCC%'  => $b['tccPct']  += (int) $v,
                'DC%'   => $b['dcPct']   += (int) $v,
                'VIT+'  => $b['vitFlat'] += (int) $v,
                'PREC+' => $b['precFlat']+= (int) $v,
                'RES+'  => $b['resFlat'] += (int) $v,
                default => null,
            };
        }
        return $b;
    }
}
