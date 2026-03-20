<?php

namespace App\Controller\Api;

use App\Battle\ActiveEffect;
use App\Battle\Combatant;
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
 * POST /api/test-combat
 *
 * Zone de test : simule un combat libre entre deux équipes de héros.
 * Aucune progression ni récompense — résultat brut uniquement.
 *
 * Body JSON :
 * {
 *   "teamA": [ { "heroId": int, "extensions": [{stat, value}|null, ...×12] } × 1-4 ],
 *   "teamB": [ ... ],
 *   "teamALeaderFactionId": int,
 *   "teamALeaderOrigineId": int,
 *   "teamAEnclaveDirection": "nord"|"sud",
 *   "teamBLeaderFactionId": int,
 *   "teamBLeaderOrigineId": int,
 *   "teamBEnclaveDirection": "nord"|"sud"
 * }
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TestCombatController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository       $heroRepository,
        private readonly AttackRepository     $attackRepository,
        private readonly BattleService        $battleService,
        private readonly BonusResolverService $bonusResolver,
    ) {}

    #[Route('/api/test-combat', name: 'api_test_combat', methods: ['POST'])]
    public function fight(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        $teamAConfigs = array_values(array_filter(
            $body['teamA'] ?? [],
            fn($c) => is_array($c) && !empty($c['heroId']),
        ));
        $teamBConfigs = array_values(array_filter(
            $body['teamB'] ?? [],
            fn($c) => is_array($c) && !empty($c['heroId']),
        ));

        if (empty($teamAConfigs) || empty($teamBConfigs)) {
            return $this->json(
                ['message' => 'Chaque équipe doit contenir au moins 1 héros'],
                Response::HTTP_BAD_REQUEST,
            );
        }
        if (count($teamAConfigs) > 4 || count($teamBConfigs) > 4) {
            return $this->json(
                ['message' => 'Maximum 4 héros par équipe'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        [$teamACombatants, $teamAStats] = $this->buildTeam(
            $teamAConfigs, 'player',
            (int) ($body['teamALeaderFactionId'] ?? 0),
            (int) ($body['teamALeaderOrigineId'] ?? 0),
            is_string($body['teamAEnclaveDirection'] ?? null) ? $body['teamAEnclaveDirection'] : 'nord',
        );
        [$teamBCombatants, $teamBStats] = $this->buildTeam(
            $teamBConfigs, 'enemy',
            (int) ($body['teamBLeaderFactionId'] ?? 0),
            (int) ($body['teamBLeaderOrigineId'] ?? 0),
            is_string($body['teamBEnclaveDirection'] ?? null) ? $body['teamBEnclaveDirection'] : 'nord',
        );

        if (empty($teamACombatants) || empty($teamBCombatants)) {
            return $this->json(['message' => 'Héros introuvables'], Response::HTTP_NOT_FOUND);
        }

        // Zone de test : équipe B contrôlée par l'IA avancée
        foreach ($teamBCombatants as $c) {
            $c->aiMode = 'advanced';
        }

        $result = $this->battleService->simulateStage($teamACombatants, [$teamBCombatants]);

        return $this->json(array_merge(
            $result->toArray(),
            ['combatants' => array_merge($teamAStats, $teamBStats)],
        ));
    }

    /**
     * @param array<int, array{heroId: int, extensions: list<array{stat:string,value:float}|null>}> $configs
     * @return array{Combatant[], list<array<string,mixed>>}
     */
    private function buildTeam(
        array  $configs,
        string $side,
        int    $leaderFactionId,
        int    $leaderOrigineId,
        string $enclaveDirection,
    ): array {
        /** @var array<int, array{Hero, array}> $heroEntities */
        $heroEntities = [];
        foreach ($configs as $cfg) {
            $hero = $this->heroRepository->find((int) ($cfg['heroId'] ?? 0));
            if ($hero !== null) {
                $heroEntities[] = [$hero, $cfg];
            }
        }

        $combatants = [];
        $initStats  = [];

        // Pré-calcul des passifs d'origine : chaque origine active bénéficie à toute l'équipe
        $teamOrigineCtx = new CombatContext();
        $seenOrigineIds  = [];
        foreach ($heroEntities as [$hero2, $_]) {
            /** @var Hero $hero2 */
            $orig = $hero2->getOrigine();
            if ($orig === null || in_array($orig->getId(), $seenOrigineIds, true)) continue;
            $seenOrigineIds[] = $orig->getId();
            $origCount = count(array_filter($heroEntities, fn($pair) => $pair[0]->getOrigine()?->getId() === $orig->getId()));
            $tmpCtx = new CombatContext();
            $tmpCtx->alliedOrigineCount = $origCount;
            if ($leaderOrigineId > 0 && $orig->getId() === $leaderOrigineId) {
                $tmpCtx->playerOrigineBonus = 1;
            }
            $this->bonusResolver->applyOriginePassive($orig, $tmpCtx);
            $teamOrigineCtx->applyFrom($tmpCtx);
        }
        if (isset($teamOrigineCtx->passiveTraits['enclave_bonus_pct'])) {
            $teamOrigineCtx->passiveTraits['enclave_direction'] = $enclaveDirection;
        }

        foreach ($heroEntities as $i => [$hero, $cfg]) {
            /** @var Hero $hero */
            $attacks = $this->attackRepository->findByHero($hero);
            $ext     = $this->computeExtBonuses($cfg['extensions'] ?? []);

            // ── CombatContext & passifs ───────────────────────────────────────
            $ctx = new CombatContext();            $ctx->heroIndex = $i;
            $ctx->teamSize  = count($heroEntities);            foreach ($heroEntities as [$other, $_]) {
                /** @var Hero $other */
                if ($hero->getFaction() && $other->getFaction()?->getId() === $hero->getFaction()->getId()) {
                    $ctx->alliedFactionCount++;
                }
            }
            if ($leaderFactionId > 0 && $hero->getFaction()?->getId() === $leaderFactionId) {
                $ctx->playerFactionBonus = 2;
            }
            if ($hero->getFaction() !== null) {
                $this->bonusResolver->applyFactionPassive($hero->getFaction(), $ctx);
            }
            // Passifs d'origine : s'appliquent à tous les héros (pré-calculés)
            $ctx->applyFrom($teamOrigineCtx);

            // ── Stats finales ─────────────────────────────────────────────────
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
                $combatant->applyEffect(new ActiveEffect(
                    'bouclier', 'Bouclier initial', 'positive', 999, $ctx->initialShieldPct, shieldHp: $shieldHp,
                ));
            }

            $combatants[] = $combatant;
            $initStats[]  = [
                'id'          => $unitId,
                'name'        => $hero->getName(),
                'side'        => $side,
                'maxHp'       => $hp,
                'baseAttack'  => $attack,
                'baseDefense' => $defense,
                'baseSpeed'   => $speed,
                'critRate'    => $critRate,
                'critDamage'  => $critDamage,
                'accuracy'    => $accuracy,
                'resistance'  => $resistance,
            ];
        }

        $this->bonusResolver->redistributeDinoTrait($combatants);

        return [$combatants, $initStats];
    }

    /**
     * @param list<array{stat:string,value:float}|null> $extensions
     * @return array{atkPct:float,defPct:float,hpPct:float,tccPct:int,dcPct:int,vitFlat:int,precFlat:int,resFlat:int}
     */
    private function computeExtBonuses(array $extensions): array
    {
        $b = [
            'atkPct' => 0.0, 'defPct' => 0.0, 'hpPct' => 0.0,
            'tccPct' => 0, 'dcPct' => 0, 'vitFlat' => 0, 'precFlat' => 0, 'resFlat' => 0,
        ];
        foreach ($extensions as $ext) {
            if (!is_array($ext) || !isset($ext['stat'], $ext['value'])) continue;
            $v = (float) $ext['value'];
            match ($ext['stat']) {
                'ATK%'  => $b['atkPct']   += $v,
                'DEF%'  => $b['defPct']   += $v,
                'HP%'   => $b['hpPct']    += $v,
                'TCC%'  => $b['tccPct']   += (int) $v,
                'DC%'   => $b['dcPct']    += (int) $v,
                'VIT+'  => $b['vitFlat']  += (int) $v,
                'PREC+' => $b['precFlat'] += (int) $v,
                'RES+'  => $b['resFlat']  += (int) $v,
                default => null,
            };
        }
        return $b;
    }
}
