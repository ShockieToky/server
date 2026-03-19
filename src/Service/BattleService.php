<?php

namespace App\Service;

use App\Battle\ActiveEffect;
use App\Battle\BattleResult;
use App\Battle\AdvancedCombatAI;
use App\Battle\Combatant;
use App\Battle\TurnEntry;
use App\Entity\Attack;
use App\Entity\AttackEffect;

/**
 * Moteur de combat PVE tour par tour.
 *
 * Usage :
 *   $result = $battleService->simulateStage($heroCombatants, $waves);
 *
 * Les héros conservent leurs PV entre les vagues.
 * Chaque vague instancie de nouveaux combattants ennemis (PV frais).
 *
 * ── Formule de dégâts ────────────────────────────────────────────────────────
 *   stat       = baseStat(scalingStat) × statBuff(attaquant)
 *   rawPerHit  = stat × (scalingPct / 100)   (plein scaling appliqué à chaque hit)
 *   critMult   = isCrit ? 1 + critDamage/100 : 1.0   (crit relancé indépendamment par hit)
 *   defPen     = ignore_defense ? 0 : effectiveDef / (effectiveDef + 1000)
 *   dmgPerHit  = max(1, floor(rawPerHit × critMult × (1 - defPen)))
 * ─────────────────────────────────────────────────────────────────────────────
 */
class BattleService
{
    private const MAX_TURNS_PER_WAVE = 200;
    private const DEF_CONSTANT       = 1500.0;

    // ── Point d'entrée ────────────────────────────────────────────────────────

    /**
     * Simule un stage complet vague par vague.
     *
     * @param Combatant[]   $heroes Combattants côté joueur (PV conservés entre vagues)
     * @param Combatant[][] $waves  Chaque élément = tableau de Combatant ennemis d'une vague
     */
    public function simulateStage(array $heroes, array $waves): BattleResult
    {
        $log           = [];
        $wavesCleared  = 0;
        $totalWaves    = count($waves);

        foreach ($waves as $waveIndex => $enemies) {
            $waveNumber = $waveIndex + 1;
            $log[]      = new TurnEntry('wave_start', 'system', 'Système', data: [
                'wave' => $waveNumber,
            ]);

            $victory = $this->simulateWave($heroes, $enemies, $log);

            if ($victory) {
                $wavesCleared++;
                $log[] = new TurnEntry('wave_clear', 'system', 'Système', data: [
                    'wave' => $waveNumber,
                ]);
            } else {
                // Toute l'équipe est tombée → défaite
                break;
            }
        }

        $heroHpLeft = [];
        foreach ($heroes as $h) {
            $heroHpLeft[$h->id] = $h->currentHp;
        }

        return new BattleResult(
            victory:      $wavesCleared === $totalWaves,
            log:          $log,
            wavesCleared: $wavesCleared,
            totalWaves:   $totalWaves,
            heroHpLeft:   $heroHpLeft,
        );
    }

    // ── Simulation d'une vague ────────────────────────────────────────────────

    /**
     * Simule un affrontement entre $heroes et $enemies.
     * Modifie directement les Combatant (PV, effets…).
     * Retourne true si les héros ont gagné.
     *
     * ── Système de tour ──────────────────────────────────────────────────────
     * Chaque "round" :
     *   1. On construit la liste $pending de toutes les unités vivantes.
     *   2. On la trie par vitesse effective DESC → le plus rapide agit en premier.
     *   3. Avant chaque action, on re-trie les unités restantes de $pending
     *      (un buff/debuff de vitesse appliqué ce round modifie immédiatement
     *      l'ordre des unités qui n'ont pas encore joué).
     *   4. Une unité ne rejoue PAS avant que toutes les autres aient agi
     *      (sauf effet spécial "regain_tour" qui la réinsère dans $pending).
     *
     * @param TurnEntry[] $log (passé par référence pour ajout en place)
     */
    private function simulateWave(array $heroes, array $enemies, array &$log): bool
    {
        $actionCount = 0;
        $moonPhase   = 0; // 0=nouvelle 1=demi 2=pleine (cycle Culte de la Lune)

        while (true) {
            $aliveHeroes  = array_values(array_filter($heroes,  fn(Combatant $c) => $c->isAlive()));
            $aliveEnemies = array_values(array_filter($enemies, fn(Combatant $c) => $c->isAlive()));

            if (empty($aliveHeroes))  return false;
            if (empty($aliveEnemies)) return true;

            // ── Construction du pending pour ce round ─────────────────────────
            $pending = array_merge($aliveHeroes, $aliveEnemies);
            usort($pending, fn(Combatant $a, Combatant $b) =>
                $b->effectiveSpeed() <=> $a->effectiveSpeed()
            );

            // ── Boucle du round ───────────────────────────────────────────────
            while (!empty($pending)) {
                if (++$actionCount > self::MAX_TURNS_PER_WAVE) return false;

                usort($pending, fn(Combatant $a, Combatant $b) =>
                    $b->effectiveSpeed() <=> $a->effectiveSpeed()
                );

                $actor = array_shift($pending);

                if (!$actor->isAlive()) continue;

                $allies = $actor->side === 'player' ? $heroes  : $enemies;
                $foes   = $actor->side === 'player' ? $enemies : $heroes;

                // ── Effets de début de tour (DoT / HoT) ──────────────────────
                $this->processTurnStartEffects($actor, $log);
                // ── Traits de début de tour (passifs) ────────────────────────
                $this->processTurnStartTraits($actor, $allies, $foes, $moonPhase, $log);

                if (!$actor->isAlive()) {
                    $pending = array_values(array_filter($pending, fn(Combatant $c) => $c->isAlive()));
                    $this->checkEndOfWave($heroes, $enemies, $aliveH, $aliveE);
                    if (empty($aliveH) || empty($aliveE)) return !empty($aliveH);
                    continue;
                }

                // ── Vérification canAct (étourdissement / sommeil / silence) ──
                if (!$actor->canAct()) {
                    $log[] = new TurnEntry('skip', $actor->id, $actor->name, data: [
                        'reason' => $actor->hasEffect('etourdissement') ? 'etourdissement' : 'sommeil',
                    ]);
                    $this->endOfTurn($actor);
                    continue;
                }

                // ── Pré-attaque du Dino (bébé dino, tier 1+) ─────────────────
                $aliveFoesNow = array_values(array_filter($foes, fn(Combatant $c) => $c->isAlive()));
                $this->processPreAttackDino($actor, $aliveFoesNow, $log);

                // ── Choix du sort ─────────────────────────────────────────────
                $aiTargetId = null;
                if ($actor->side === 'enemy' && $actor->aiMode === 'advanced') {
                    $decision   = AdvancedCombatAI::decide($actor, $allies, $foes);
                    $attack     = $decision['attack'];
                    $aiTargetId = $decision['targetId'];
                } elseif ($actor->side === 'enemy') {
                    $attack = $actor->pickAttackEnemyAI();
                } else {
                    $attack = $actor->pickAttack();
                }
                if ($attack === null) {
                    $this->endOfTurn($actor);
                    continue;
                }

                $log[] = new TurnEntry('attack', $actor->id, $actor->name, data: [
                    'attackName' => $attack->getName(),
                    'slotIndex'  => $attack->getSlotIndex(),
                ]);

                // ── Sélection des cibles ──────────────────────────────────────
                $targets = $this->selectTargets($actor, $attack, $allies, $foes);

                // ── Override cible IA avancée ─────────────────────────────────
                if ($aiTargetId !== null) {
                    $manualTargetTypes = ['single_enemy', 'random_enemy', 'single_ally', 'random_ally'];
                    if (in_array($attack->getTargetType(), $manualTargetTypes, true)) {
                        foreach (array_merge($allies, $foes) as $c) {
                            if ($c->id === $aiTargetId && $c->isAlive()) {
                                $targets = [$c];
                                break;
                            }
                        }
                    }
                }

                // ── Snapshot vivants avant dégâts (pour beast kill) ───────────
                $aliveTargetsBefore = count(array_filter($targets, fn(Combatant $c) => $c->isAlive()));

                // ── Application des dégâts ────────────────────────────────────
                if ($attack->getScalingStat() !== 'none') {
                    $this->applyDamageToTargets($actor, $attack, $targets, $log);
                }

                // ── Procs on-hit passifs (feu, sommeil, silence) ──────────────
                $this->processOnHitPassiveProcs($actor, $targets, $log);

                // ── Post-attaque Dino (tier 2 brûlure / tier 3 coup extra) ────
                $this->processDinoPostAttack($actor, $targets, $log);

                // ── Effets de l'attaque (buffs/debuffs/soins instantanés) ─────
                $extraTurn = $this->applyAttackEffects($actor, $attack, $targets, $allies, $foes, $log);

                // ── Beast Clan : tour supplémentaire sur kill ─────────────────
                $aliveTargetsAfter = count(array_filter($targets, fn(Combatant $c) => $c->isAlive()));
                $beastKill = $aliveTargetsBefore > $aliveTargetsAfter
                    && ($actor->passiveTraits['beast_kill_extra_attack'] ?? false);

                // ── Fracture des Dieux : tour supplémentaire aléatoire ─────────
                if (!$extraTurn && isset($actor->passiveTraits['passive_extra_turn_chance'])) {
                    if (random_int(0, 99) < $actor->passiveTraits['passive_extra_turn_chance']) {
                        $extraTurn = true;
                        $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
                            'effect'   => 'extra_turn',
                            'label'    => 'Tour supplémentaire (Fracture des Dieux)',
                            'polarity' => 'positive',
                        ]);
                    }
                }

                $extraTurn = $extraTurn || $beastKill;

                // ── Mise en cooldown ──────────────────────────────────────────
                if ($attack->getCooldown() > 0) {
                    $actor->cooldowns[$attack->getId()] = $attack->getCooldown();
                }

                // ── Fin de tour ───────────────────────────────────────────────
                $this->endOfTurn($actor, skipEffectTick: $extraTurn);

                // ── Regain de tour : réinsertion dans le pending ──────────────
                if ($extraTurn && $actor->isAlive()) {
                    $pending[] = $actor;
                }

                // ── Retirer les morts du pending et vérifier fin de vague ─────
                $pending = array_values(array_filter($pending, fn(Combatant $c) => $c->isAlive()));

                $aliveH = array_filter($heroes,  fn(Combatant $c) => $c->isAlive());
                $aliveE = array_filter($enemies, fn(Combatant $c) => $c->isAlive());
                if (empty($aliveH) || empty($aliveE)) {
                    return !empty($aliveH);
                }
            }
        }

        return false; // unreachable
    }

    /** Remplissage rapide des tableaux vivants sans duplication de logique. */
    private function checkEndOfWave(array $heroes, array $enemies, ?array &$aliveH, ?array &$aliveE): void
    {
        $aliveH = array_filter($heroes,  fn(Combatant $c) => $c->isAlive());
        $aliveE = array_filter($enemies, fn(Combatant $c) => $c->isAlive());
    }

    // ── Effets de début de tour ───────────────────────────────────────────────

    private function processTurnStartEffects(Combatant $actor, array &$log): void
    {
        // Brûlure : X% des PV max en dégâts (cumulable — on applique chaque instance)
        $burns = array_filter($actor->activeEffects, fn($e) => $e->name === 'brulure');
        foreach ($burns as $e) {
            $dmg = (int) ceil($actor->maxHp * $e->value / 100.0);
            $actual = $actor->takeDamage($dmg);
            $log[] = new TurnEntry('effect_tick', $actor->id, $actor->name, data: [
                'effect'  => 'brulure',
                'damage'  => $actual,
                'hpLeft'  => $actor->currentHp,
            ]);
            if (!$actor->isAlive()) {
                $log[] = new TurnEntry('death', $actor->id, $actor->name);
                return;
            }
        }

        // Récupération : X% des PV max soignés (cumulable — on applique chaque instance)
        $regens = array_filter($actor->activeEffects, fn($e) => $e->name === 'recuperation');
        foreach ($regens as $e) {
            $heal = (int) ceil($actor->maxHp * $e->value / 100.0);
            $actual = $actor->heal($heal);
            $log[] = new TurnEntry('effect_tick', $actor->id, $actor->name, data: [
                'effect' => 'recuperation',
                'heal'   => $actual,
                'hpLeft' => $actor->currentHp,
            ]);
        }
    }

    // ── Calcul et application des dégâts ─────────────────────────────────────

    /**
     * @param Combatant[] $targets
     */
    private function applyDamageToTargets(
        Combatant $actor,
        Attack    $attack,
        array     $targets,
        array     &$log,
    ): void {
        // Récupération de la stat de scaling de l'attaquant
        $baseStat = match ($attack->getScalingStat()) {
            'atk'  => $actor->effectiveAttack(),
            'def'  => $actor->effectiveDefense(),
            'hp'   => $actor->maxHp,
            'spd'  => $actor->effectiveSpeed(),
            default => 0,
        };
        if ($baseStat <= 0) return;

        $scalingPct = $attack->getScalingPct() / 100.0;
        $hitCount   = max(1, $attack->getHitCount());
        $rawPerHit  = $baseStat * $scalingPct;   // plein scaling par hit

        // Crit : relancé indépendamment à chaque hit
        // critDamage représente le bonus en % au-dessus de ×1.0 (ex. 50 → ×1.5, 100 → ×2.0)
        $effectiveCritRate = $actor->critRate;

        // ignore_defense : vérifié une seule fois (effet instantané sur l'attaque elle-même)
        $ignoreDefense = $this->attackHasEffect($attack, 'ignore_defense');

        foreach ($targets as $target) {
            if (!$target->isAlive()) continue;

            // Réduction par la défense
            $effectiveDef = $ignoreDefense ? 0 : $target->effectiveDefense();
            $defPen       = $effectiveDef / ($effectiveDef + self::DEF_CONSTANT);

            // Réveil si endormi et attaqué
            $wasAsleep = $target->hasEffect('sommeil');
            if ($wasAsleep) {
                $target->removeEffect('sommeil');
            }

            // ── Multiplicateurs passifs ──────────────────────────────────────
            // Bonus Sleeping Cult : +X % si la cible est endormie
            $bonusMult = 1.0;
            if ($wasAsleep && isset($actor->passiveTraits['sleep_dmg_bonus_pct'])) {
                $bonusMult *= 1.0 + $actor->passiveTraits['sleep_dmg_bonus_pct'] / 100.0;
            }
            // Bonus Enclave directionnel
            if (isset($actor->passiveTraits['enclave_bonus_pct'], $actor->passiveTraits['enclave_direction'])) {
                $dir = $actor->passiveTraits['enclave_direction'];
                if (isset($target->passiveTraits['direction']) && $target->passiveTraits['direction'] === $dir) {
                    $bonusMult *= 1.0 + $actor->passiveTraits['enclave_bonus_pct'] / 100.0;
                }
            }
            // Code spécial : sommeil_bonus_50 — +50% si la cible était endormie
            if ($attack->getSpecialCode() === 'sommeil_bonus_50' && $wasAsleep) {
                $bonusMult *= 1.5;
            }
            // Code spécial : sommeil_bonus_150 — +150% si la cible était endormie
            if ($attack->getSpecialCode() === 'sommeil_bonus_150' && $wasAsleep) {
                $bonusMult *= 2.5;
            }

            // Multiplicateur PvE : ×2 quand la cible est un ennemi (PvE uniquement, sans impact PvP)
            $pveMult = $target->side === 'enemy' ? 2.0 : 1.0;

            // Application des hits
            $totalDmg = 0;
            for ($hit = 0; $hit < $hitCount; $hit++) {
                // Crit relancé à chaque hit
                $isCrit   = random_int(0, 99) < $effectiveCritRate;
                $critMult = $isCrit ? 1.0 + $actor->critDamage / 100.0 : 1.0;

                $dmg    = max(1, (int) floor($rawPerHit * $critMult * $bonusMult * $pveMult * (1.0 - $defPen)));
                $dmg    = $this->computeIncomingAfterBlocks($target, $dmg, $log);
                $actual    = $target->takeDamage($dmg);
                $totalDmg += $actual;

                $log[] = new TurnEntry('damage', $actor->id, $actor->name, $target->id, $target->name, [
                    'attackName'    => $attack->getName(),
                    'hit'           => $hit + 1,
                    'hitCount'      => $hitCount,
                    'damage'        => $actual,
                    'isCrit'        => $isCrit,
                    'ignoreDefense' => $ignoreDefense,
                    'hpLeft'        => $target->currentHp,
                ]);

                if (!$target->isAlive()) {
                    $log[] = new TurnEntry('death', $target->id, $target->name);
                    // Code spécial : kill_gain_invincibility — invincibilité 1 tour sur kill
                    if ($attack->getSpecialCode() === 'kill_gain_invincibility') {
                        $actor->applyEffect(new ActiveEffect('invincibilite', 'Invincibilité', 'positive', 1, 0.0));
                        $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
                            'effect'   => 'invincibilite',
                            'label'    => 'Invincibilité (kill)',
                            'polarity' => 'positive',
                            'duration' => 1,
                            'value'    => 0,
                        ]);
                    }
                    break;
                }

                // Vampirisme (soif_sang, durée) : soin par hit
                if ($e = $actor->getEffect('soif_sang')) {
                    $lifeSteal = (int) ceil($actual * $e->value / 100.0);
                    $healed    = $actor->heal($lifeSteal);
                    if ($healed > 0) {
                        $log[] = new TurnEntry('heal', $actor->id, $actor->name, data: [
                            'source' => 'soif_sang',
                            'heal'   => $healed,
                            'hpLeft' => $actor->currentHp,
                        ]);
                    }
                }
            }

            // Vampirisme instantané ('vampirisme') : soin après les hits
            $vampEffect = $this->getAttackEffect($attack, 'vampirisme');
            if ($vampEffect !== null && $totalDmg > 0) {
                $vampValue = $vampEffect->getValue() ?? $vampEffect->getEffect()?->getDefaultValue() ?? 15.0;
                $lifeSteal = (int) ceil($totalDmg * (float) $vampValue / 100.0);
                $healed    = $actor->heal($lifeSteal);
                if ($healed > 0) {
                    $log[] = new TurnEntry('heal', $actor->id, $actor->name, data: [
                        'source' => 'vampirisme',
                        'heal'   => $healed,
                        'hpLeft' => $actor->currentHp,
                    ]);
                }
            }
        }
    }

    // ── Application des effets de l'attaque ───────────────────────────────────

    /**
     * @param Combatant[] $targets  Cibles primaires de l'attaque
     * @param Combatant[] $allies   Alliés de l'acteur (acteur inclus)
     * @param Combatant[] $foes     Ennemis de l'acteur
     * @return bool true si l'acteur obtient un tour supplémentaire immédiat
     */
    private function applyAttackEffects(
        Combatant $actor,
        Attack    $attack,
        array     $targets,
        array     $allies,
        array     $foes,
        array     &$log,
    ): bool {
        $extraTurn = false;

        // Code spécial : swap_effects — inverse les effets entre alliés et ennemis (par paire)
        if ($attack->getSpecialCode() === 'swap_effects') {
            $allyList = array_values($allies);
            $foeList  = array_values($foes);
            $maxPairs = max(count($allyList), count($foeList));
            for ($i = 0; $i < $maxPairs; $i++) {
                $ally        = $allyList[$i] ?? null;
                $foe         = $foeList[$i]  ?? null;
                $allyEffects = $ally ? $ally->activeEffects : [];
                $foeEffects  = $foe  ? $foe->activeEffects  : [];
                if ($ally) $ally->activeEffects = $foeEffects;
                if ($foe)  $foe->activeEffects  = $allyEffects;
            }
            $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
                'effect'   => 'swap_effects',
                'label'    => 'Inversion des effets',
                'polarity' => 'positive',
            ]);
            return $extraTurn;
        }

        foreach ($attack->getAttackEffects() as $ae) {
            $effect = $ae->getEffect();
            if ($effect === null) continue;

            $effectName = $effect->getName();

            // Effets instantanés gérés dans applyDamageToTargets ou ici séparément
            if (in_array($effectName, ['ignore_defense', 'vampirisme'], true)) continue;

            // ── Regain de tour : accordé à l'acteur, pas à une cible ─────────
            if ($effectName === 'regain_tour') {
                if (random_int(0, 99) < $ae->getChance()) {
                    $extraTurn = true;
                    $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
                        'effect'   => 'regain_tour',
                        'label'    => 'Regain de tour',
                        'polarity' => 'positive',
                    ]);
                }
                continue;
            }

            $chance = $ae->getChance();

            // Détermination des cibles de l'effet
            $effectTargets = $this->selectEffectTargets($ae->getEffectTarget(), $actor, $targets, $allies, $foes);

            foreach ($effectTargets as $et) {
                if (!$et->isAlive()) continue;

                // Jet de chance : précision vs résistance pour les effets négatifs.
                //
                // Formule : finalChance = sort_chance + précision_acteur - résistance_cible
                //   - La précision permet d'atteindre 100 % même si le sort ne donne que X %.
                //   - Plancher de 15 % : même 100 % de résistance ne rend pas un effet inposable.
                //   - Plafond de 100 %.
                if ($effect->getPolarity() === 'negative') {
                    $finalChance = max(15, min(100, $chance + $actor->accuracy - $et->resistance));
                } else {
                    $finalChance = $chance;
                }

                if (random_int(0, 99) >= $finalChance) continue;

                $value    = $ae->getValue() ?? $effect->getDefaultValue() ?? 0.0;
                $duration = $ae->getDuration();

                // ── Effets instantanés ────────────────────────────────────────
                if ($effect->getDurationType() === 'instant') {
                    $this->resolveInstantEffect($effectName, $actor, $et, (float) $value, $log);
                    continue;
                }

                // ── Effets de durée ───────────────────────────────────────────
                $active = new ActiveEffect(
                    name:           $effectName,
                    label:          $effect->getLabel(),
                    polarity:       $effect->getPolarity(),
                    remainingTurns: $duration ?? 1,
                    value:          (float) $value,
                    sourceId:       $effectName === 'provocation' ? $actor->id : '',
                    shieldHp:       $effectName === 'bouclier'
                        ? $et->maxHp * (float) $value / 100.0
                        : 0.0,
                );

                $applied = $et->applyEffect($active);
                if ($applied) {
                    $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $et->id, $et->name, [
                        'effect'    => $effectName,
                        'label'     => $effect->getLabel(),
                        'polarity'  => $effect->getPolarity(),
                        'duration'  => $duration,
                        'value'     => $value,
                    ]);
                }
            }
        }
        return $extraTurn;
    }

    /** Résout un effet instantané. */
    private function resolveInstantEffect(
        string    $effectName,
        Combatant $actor,
        Combatant $target,
        float     $value,
        array     &$log,
    ): void {
        switch ($effectName) {
            case 'soins':
                $heal   = (int) ceil($target->maxHp * $value / 100.0);
                $healed = $target->heal($heal);
                $log[]  = new TurnEntry('heal', $actor->id, $actor->name, $target->id, $target->name, [
                    'source' => 'soins',
                    'heal'   => $healed,
                    'hpLeft' => $target->currentHp,
                ]);
                break;

            case 'purification':
                $target->removeEffectsByPolarity('negative');
                $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $target->id, $target->name, [
                    'effect'   => 'purification',
                    'label'    => 'Purification',
                    'polarity' => 'positive',
                ]);
                break;

            case 'suppression':
                $target->removeEffectsByPolarity('positive');
                $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $target->id, $target->name, [
                    'effect'   => 'suppression',
                    'label'    => 'Suppression',
                    'polarity' => 'negative',
                ]);
                break;

            case 'activation_brulure':
                if ($e = $target->getEffect('brulure')) {
                    $dmg    = (int) ceil($target->maxHp * $e->value / 100.0);
                    $actual = $target->takeDamage($dmg);
                    $log[]  = new TurnEntry('effect_tick', $target->id, $target->name, data: [
                        'effect'  => 'brulure',
                        'damage'  => $actual,
                        'hpLeft'  => $target->currentHp,
                    ]);
                    if (!$target->isAlive()) {
                        $log[] = new TurnEntry('death', $target->id, $target->name);
                    }
                }
                break;
        }
    }

    // ── Fin de tour ───────────────────────────────────────────────────────────

    private function endOfTurn(Combatant $actor, bool $skipEffectTick = false): void
    {
        $actor->tickCooldowns();
        if (!$skipEffectTick) {
            $actor->tickEffects();
        }
    }

    // ── Sélection des cibles ──────────────────────────────────────────────────

    /**
     * @param Combatant[] $allies Alliés de l'acteur
     * @param Combatant[] $foes   Ennemis de l'acteur
     * @return Combatant[]
     */
    private function selectTargets(Combatant $actor, Attack $attack, array $allies, array $foes): array
    {
        $aliveFoes   = array_values(array_filter($foes,   fn(Combatant $c) => $c->isAlive()));
        $aliveAllies = array_values(array_filter($allies, fn(Combatant $c) => $c->isAlive()));

        if (empty($aliveFoes) && empty($aliveAllies)) return [];

        // Provocation : force l'attaque sur le lanceur de la provocation
        if ($actor->hasEffect('provocation')) {
            $provoker = $this->findProvoker($actor, $aliveFoes);
            if ($provoker !== null) return [$provoker];
        }

        return match ($attack->getTargetType()) {
            'all_enemies'   => $aliveFoes,
            'all_allies'    => $aliveAllies,
            'self'          => [$actor],
            'random_enemy'  => !empty($aliveFoes)   ? [$aliveFoes[array_rand($aliveFoes)]]     : [],
            'random_ally'   => !empty($aliveAllies)  ? [$aliveAllies[array_rand($aliveAllies)]] : [],
            // 'single_ally' : allié avec le moins de PV (hors soi-même si possible)
            'single_ally'   => !empty($aliveAllies)
                ? [array_reduce($aliveAllies, fn($carry, $c) => $carry === null || $c->currentHp < $carry->currentHp ? $c : $carry)]
                : [],
            // 'single_enemy' (default) : ennemi avec le moins de PV
            default         => !empty($aliveFoes)
                ? [array_reduce($aliveFoes, fn($carry, $c) => $carry === null || $c->currentHp < $carry->currentHp ? $c : $carry)]
                : [],
        };
    }

    /**
     * @param Combatant[] $targets
     * @param Combatant[] $allies
     * @param Combatant[] $foes
     * @return Combatant[]
     */
    private function selectEffectTargets(
        string    $effectTarget,
        Combatant $actor,
        array     $targets,
        array     $allies,
        array     $foes,
    ): array {
        $aliveFoes   = array_values(array_filter($foes,   fn(Combatant $c) => $c->isAlive()));
        $aliveAllies = array_values(array_filter($allies, fn(Combatant $c) => $c->isAlive()));

        return match ($effectTarget) {
            'target'       => array_values(array_filter($targets, fn(Combatant $c) => $c->isAlive())),
            'self'         => [$actor],
            'all_enemies'  => $aliveFoes,
            'all_allies'   => $aliveAllies,
            'random_enemy' => !empty($aliveFoes)   ? [$aliveFoes[array_rand($aliveFoes)]]     : [],
            'random_ally'  => !empty($aliveAllies)  ? [$aliveAllies[array_rand($aliveAllies)]] : [],
            'single_ally'  => !empty($aliveAllies)
                ? [array_reduce($aliveAllies, fn($carry, $c) => $carry === null || $c->currentHp < $carry->currentHp ? $c : $carry)]
                : [],
            default        => array_values(array_filter($targets, fn(Combatant $c) => $c->isAlive())),
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function attackHasEffect(Attack $attack, string $effectName): bool
    {
        foreach ($attack->getAttackEffects() as $ae) {
            if ($ae->getEffect()?->getName() === $effectName) return true;
        }
        return false;
    }

    private function getAttackEffect(Attack $attack, string $effectName): ?AttackEffect
    {
        foreach ($attack->getAttackEffects() as $ae) {
            if ($ae->getEffect()?->getName() === $effectName) return $ae;
        }
        return null;
    }

    /**
     * Cherche dans $foes la cible qui a posé une provocation sur $actor.
     * Faute d'info stockée, on retourne l'ennemi avec le moins de PV.
     *
     * @param Combatant[] $foes
     */
    private function findProvoker(Combatant $actor, array $foes): ?Combatant
    {
        $sourceId = $actor->getEffect('provocation')?->sourceId ?? '';
        if ($sourceId !== '') {
            foreach ($foes as $foe) {
                if ($foe->id === $sourceId) return $foe;
            }
        }
        // Fallback : ennemi avec le moins de PV
        return !empty($foes)
            ? array_reduce($foes, fn($c, $m) => $c === null || $m->currentHp < $c->currentHp ? $m : $c)
            : null;
    }

    // ── Passifs : début de tour ───────────────────────────────────────────────

    /**
     * Déclenche les passifs qui agissent au début du tour de $actor.
     *
     * @param Combatant[] $allies
     * @param Combatant[] $foes
     * @param TurnEntry[] $log
     */
    private function processTurnStartTraits(
        Combatant $actor,
        array     $allies,
        array     $foes,
        int       &$moonPhase,
        array     &$log,
    ): void {
        $traits = $actor->passiveTraits;

        // ── Maître de l'Eau : soin de début de tour ───────────────────────────
        if (isset($traits['water_turn_heal_pct']) && $actor->isAlive()) {
            $heal   = (int) ceil($actor->maxHp * $traits['water_turn_heal_pct'] / 100.0);
            $healed = $actor->heal($heal);
            if ($healed > 0) {
                $log[] = new TurnEntry('heal', $actor->id, $actor->name, data: [
                    'source' => 'water_master_passive',
                    'heal'   => $healed,
                    'hpLeft' => $actor->currentHp,
                ]);
            }
        }

        // ── Désert : purification + soin ──────────────────────────────────────
        if (isset($traits['desert_cleanse_chance'], $traits['desert_heal_pct']) && $actor->isAlive()) {
            if (random_int(0, 99) < $traits['desert_cleanse_chance']) {
                $actor->removeFirstNegativeEffect();
                $heal   = (int) ceil($actor->maxHp * $traits['desert_heal_pct'] / 100.0);
                $healed = $actor->heal($heal);
                $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
                    'effect'   => 'desert_passive',
                    'label'    => 'Purge Désert',
                    'polarity' => 'positive',
                    'heal'     => $healed,
                    'hpLeft'   => $actor->currentHp,
                ]);
            }
        }

        // ── Culte de la Lune : cycle lunaire ──────────────────────────────────
        if (isset($traits['moon_cult_tier']) && $actor->isAlive()) {
            $tier = (int) $traits['moon_cult_tier'];
            $phase = $moonPhase % 3; // 0=nouvelle 1=demi 2=pleine
            $moonPhase++;

            $aliveAllies = array_values(array_filter($allies, fn(Combatant $c) => $c->isAlive()));

            match ($phase) {
                0 => $this->applyMoonNouvelleEffect($actor, $aliveAllies, $tier, $log),
                1 => $this->applyMoonDemiEffect($actor, $aliveAllies, $tier, $log),
                2 => $this->applyMoonPleineEffect($actor, $tier, $log),
                default => null,
            };
        }

        // ── Clan des Bêtes : frénésie de début de tour ────────────────────────
        if (isset($traits['beast_rage_chance']) && $actor->isAlive()) {
            if (!$actor->hasEffect('enrage') && random_int(0, 99) < $traits['beast_rage_chance']) {
                foreach (['aug_attaque', 'aug_defense', 'aug_vitesse'] as $buffName) {
                    $actor->applyEffect(new ActiveEffect($buffName, ucfirst(str_replace('_', ' ', $buffName)), 'positive', 2, 10.0));
                }
                // Sentinelle anti double-enrage
                $actor->applyEffect(new ActiveEffect('enrage', 'Frénésie', 'positive', 2, 0.0));
                $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
                    'effect'   => 'beast_rage',
                    'label'    => 'Frénésie du clan',
                    'polarity' => 'positive',
                ]);
            }
        }
    }

    // ── Lune : effets par phase ───────────────────────────────────────────────

    /** Phase 0 – Lune Nouvelle : soin du héros actif. */
    private function applyMoonNouvelleEffect(Combatant $actor, array $aliveAllies, int $tier, array &$log): void
    {
        $healPct = [5.0, 7.0, 10.0][$tier - 1] ?? 5.0;
        $heal   = (int) ceil($actor->maxHp * $healPct / 100.0);
        $healed = $actor->heal($heal);
        if ($healed > 0) {
            $log[] = new TurnEntry('heal', $actor->id, $actor->name, $actor->id, $actor->name, [
                'source' => 'moon_nouvelle',
                'heal'   => $healed,
                'hpLeft' => $actor->currentHp,
            ]);
        }
    }

    /** Phase 1 – Demi-Lune : extension des effets positifs du héros actif. */
    private function applyMoonDemiEffect(Combatant $actor, array $aliveAllies, int $tier, array &$log): void
    {
        $extendBy = $tier >= 3 ? 2 : 1;
        $actor->extendPositiveEffects($extendBy);
        $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
            'effect'   => 'moon_demi',
            'label'    => "Demi-Lune (effets positifs +$extendBy tour)",
            'polarity' => 'positive',
        ]);
    }

    /** Phase 2 – Pleine Lune : augmente l'attaque de l'acteur. */
    private function applyMoonPleineEffect(Combatant $actor, int $tier, array &$log): void
    {
        $atkPct = [5.0, 10.0, 15.0][$tier - 1] ?? 5.0;
        $actor->applyEffect(new ActiveEffect('aug_attaque', 'Pleine Lune', 'positive', 2, $atkPct));
        $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
            'effect'   => 'moon_pleine',
            'label'    => "Pleine Lune (+$atkPct % ATK)",
            'polarity' => 'positive',
        ]);
    }

    // ── Passifs : pré-attaque Dino ────────────────────────────────────────────

    /**
     * Clan des Dinosaures tier 1 : le bébé dino frappe l'ennemi le plus faible
     * en début de tour (avant le sort principal).
     *
     * @param Combatant[] $aliveFoes
     * @param TurnEntry[] $log
     */
    private function processPreAttackDino(Combatant $actor, array $aliveFoes, array &$log): void
    {
        if (empty($aliveFoes)) return;
        $tier = (int) ($actor->passiveTraits['dino_tier'] ?? 0);
        if ($tier < 1) return;

        if (random_int(0, 99) >= 30) return; // 30 % de déclenchement

        $target = array_reduce($aliveFoes, fn($c, $m) => $c === null || $m->currentHp < $c->currentHp ? $m : $c);
        if ($target === null) return;

        $rawDmg = max(1, (int) floor($actor->effectiveAttack() * 0.20));
        $def    = $target->effectiveDefense();
        $dmg    = max(1, (int) floor($rawDmg * (1.0 - $def / ($def + self::DEF_CONSTANT))));
        $dmg    = $this->computeIncomingAfterBlocks($target, $dmg, $log);
        $actual = $target->takeDamage($dmg);

        $log[] = new TurnEntry('damage', $actor->id, $actor->name, $target->id, $target->name, [
            'attackName' => 'Bébé Dino',
            'hit'        => 1,
            'hitCount'   => 1,
            'damage'     => $actual,
            'isCrit'     => false,
            'hpLeft'     => $target->currentHp,
        ]);

        if (!$target->isAlive()) {
            $log[] = new TurnEntry('death', $target->id, $target->name);
            return;
        }

        // 5 % de chance d'infliger red_defense 1 tour
        if (random_int(0, 99) < 5) {
            $target->applyEffect(new ActiveEffect('red_defense', 'Réduction défense', 'negative', 1, 20.0));
            $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $target->id, $target->name, [
                'effect'   => 'red_defense',
                'label'    => 'Réduction défense (Bébé Dino)',
                'polarity' => 'negative',
            ]);
        }
    }

    // ── Passifs : procs on-hit ────────────────────────────────────────────────

    /**
     * Brûlure (Ordre du Feu), Sommeil (Occultiste du Sommeil), Silence (Île de la Lune).
     *
     * @param Combatant[] $targets
     * @param TurnEntry[] $log
     */
    private function processOnHitPassiveProcs(Combatant $actor, array $targets, array &$log): void
    {
        $traits = $actor->passiveTraits;

        foreach ($targets as $target) {
            if (!$target->isAlive() || $target->side === $actor->side) continue;

            // Ordre du Feu : brûlure
            if (isset($traits['fire_proc_chance']) && random_int(0, 99) < $traits['fire_proc_chance']) {
                $burnValue = 3.0 + ($traits['fire_burn_bonus_pct'] ?? 0.0);
                $target->applyEffect(new ActiveEffect('brulure', 'Brûlure (Feu)', 'negative', 1, $burnValue));
                $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $target->id, $target->name, [
                    'effect'   => 'brulure',
                    'label'    => 'Brûlure (Ordre du Feu)',
                    'polarity' => 'negative',
                    'value'    => $burnValue,
                ]);
            }

            // Occultiste du Sommeil : sommeil
            if (isset($traits['sleep_proc_chance'])) {
                $chance = max(15, min(100, $traits['sleep_proc_chance'] + $actor->accuracy - $target->resistance));
                if (random_int(0, 99) < $chance) {
                    $target->applyEffect(new ActiveEffect('sommeil', 'Sommeil', 'negative', 1, 0.0));
                    $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $target->id, $target->name, [
                        'effect'   => 'sommeil',
                        'label'    => 'Sommeil (Occultiste)',
                        'polarity' => 'negative',
                    ]);
                }
            }

            // Île de la Lune : silence
            if (isset($traits['silence_proc_chance'])) {
                $chance = max(15, min(100, $traits['silence_proc_chance'] + $actor->accuracy - $target->resistance));
                if (random_int(0, 99) < $chance) {
                    $target->applyEffect(new ActiveEffect('silence', 'Silence', 'negative', 1, 0.0));
                    $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $target->id, $target->name, [
                        'effect'   => 'silence',
                        'label'    => 'Silence (Île de la Lune)',
                        'polarity' => 'negative',
                    ]);
                }
            }
        }
    }

    // ── Passifs : post-attaque Dino ───────────────────────────────────────────

    /**
     * Clan des Dinosaures tier 2 (brûlure) et tier 3 (coup extra + étourdissement).
     *
     * @param Combatant[] $targets
     * @param TurnEntry[] $log
     */
    private function processDinoPostAttack(Combatant $actor, array $targets, array &$log): void
    {
        $tier = (int) ($actor->passiveTraits['dino_tier'] ?? 0);
        if ($tier < 2) return;

        $aliveTargets = array_values(array_filter($targets, fn(Combatant $c) => $c->isAlive()));
        if (empty($aliveTargets)) return;

        // Tier 2 : 40 % → brûlure 2 tours
        if ($tier >= 2 && random_int(0, 99) < 40) {
            foreach ($aliveTargets as $t) {
                $t->applyEffect(new ActiveEffect('brulure', 'Brûlure (Dino)', 'negative', 2, 3.0));
                $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $t->id, $t->name, [
                    'effect'   => 'brulure',
                    'label'    => 'Brûlure (Dino Imposant)',
                    'polarity' => 'negative',
                    'value'    => 3.0,
                ]);
            }
        }

        // Tier 3 : 35 % → coup supplémentaire (50 % ATK) + étourdissement
        if ($tier >= 3 && random_int(0, 99) < 35) {
            foreach ($aliveTargets as $t) {
                $rawDmg = max(1, (int) floor($actor->effectiveAttack() * 0.50));
                $def    = $t->effectiveDefense();
                $dmg    = max(1, (int) floor($rawDmg * (1.0 - $def / ($def + self::DEF_CONSTANT))));
                $dmg    = $this->computeIncomingAfterBlocks($t, $dmg, $log);
                $actual = $t->takeDamage($dmg);

                $log[] = new TurnEntry('damage', $actor->id, $actor->name, $t->id, $t->name, [
                    'attackName' => 'Tyrannosaure',
                    'hit'        => 1,
                    'hitCount'   => 1,
                    'damage'     => $actual,
                    'isCrit'     => false,
                    'hpLeft'     => $t->currentHp,
                ]);

                if (!$t->isAlive()) {
                    $log[] = new TurnEntry('death', $t->id, $t->name);
                    continue;
                }

                $stunChance = max(15, min(100, 35 + $actor->accuracy - $t->resistance));
                if (random_int(0, 99) < $stunChance) {
                    $t->applyEffect(new ActiveEffect('etourdissement', 'Étourdissement', 'negative', 1, 0.0));
                    $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, $t->id, $t->name, [
                        'effect'   => 'etourdissement',
                        'label'    => 'Étourdissement (Tyranno)',
                        'polarity' => 'negative',
                    ]);
                }
            }
        }
    }

    // ── Passifs : blocage entrant ─────────────────────────────────────────────

    /**
     * Ancienne Fracture et Maître de l'Eau : réduction des dégâts par hit entrant.
     *
     * @param TurnEntry[] $log
     */
    private function computeIncomingAfterBlocks(Combatant $target, int $amount, array &$log): int
    {
        $traits = $target->passiveTraits;

        // Ancienne Fracture
        if (isset($traits['fracture_block_chance'], $traits['fracture_block_pct'])) {
            if (random_int(0, 99) < $traits['fracture_block_chance']) {
                $amount = max(1, (int) floor($amount * (1.0 - $traits['fracture_block_pct'] / 100.0)));
                $log[]  = new TurnEntry('effect_apply', 'system', 'Système', $target->id, $target->name, [
                    'effect'   => 'fracture_block',
                    'label'    => 'Blocage (Ancienne Fracture)',
                    'polarity' => 'positive',
                    'reduced'  => true,
                ]);
            }
        }

        // Maître de l'Eau
        if (isset($traits['water_block_chance'])) {
            if (random_int(0, 99) < $traits['water_block_chance']) {
                $amount = max(1, (int) floor($amount * 0.75));
                $log[]  = new TurnEntry('effect_apply', 'system', 'Système', $target->id, $target->name, [
                    'effect'   => 'water_block',
                    'label'    => 'Blocage (Maître de l\'Eau)',
                    'polarity' => 'positive',
                    'reduced'  => true,
                ]);
            }
        }

        return $amount;
    }

    // ── Mode combat manuel ────────────────────────────────────────────────────

    /**
     * Traite les effets de début de tour d'un acteur (DoT/HoT + traits passifs).
     * Appelé par les controllers AVANT de retourner le state choose_attack,
     * afin que le heal/buff soit visible avant que le joueur choisisse son attaque.
     *
     * Retourne false si l'acteur est mort pendant ce traitement.
     *
     * @param Combatant[] $allies  Alliés de l'acteur
     * @param Combatant[] $foes    Ennemis de l'acteur
     * @param TurnEntry[] $log
     */
    public function processActorStartOfTurn(
        Combatant $actor,
        array     $allies,
        array     $foes,
        int       &$moonPhase,
        array     &$log,
    ): bool {
        $this->processTurnStartEffects($actor, $log);
        if ($actor->isAlive()) {
            $this->processTurnStartTraits($actor, $allies, $foes, $moonPhase, $log);
        }
        return $actor->isAlive();
    }

    /**
     * Exécute UNE action manuelle pour l'acteur indiqué.
     *
     * Appelé par ManualBattleController sur chaque step décidé par l'utilisateur.
     * Gère : DoT/HoT début de tour, passifs de début de tour, skip si étourdi/endormi,
     * exécution de l'attaque choisie (par $attackId), fin de tour.
     *
     * @param Combatant[]     $heroes   Combattants côté joueur
     * @param Combatant[]     $enemies  Combattants côté ennemi
     * @param int|null        $attackId Identifiant de l'attaque choisie (null → auto-skip)
     * @param TurnEntry[]     $log
     * @return array{extraTurn:bool, battleOver:bool, heroesWin:bool}
     */
    public function executeManualStep(
        array    $heroes,
        array    $enemies,
        string   $actorId,
        ?int     $attackId,
        array    &$log,
        int      &$moonPhase,
        int      &$actionCount,
        ?string  $forcedTargetId = null,
        bool     $skipStartOfTurn = false,
    ): array {
        // ── Trouver l'acteur ──────────────────────────────────────────────────
        $all   = array_merge($heroes, $enemies);
        $actor = null;
        foreach ($all as $c) {
            if ($c->id === $actorId) { $actor = $c; break; }
        }

        if ($actor === null || !$actor->isAlive()) {
            return ['extraTurn' => false, 'battleOver' => false, 'heroesWin' => false];
        }

        $actionCount++;
        $allies = $actor->side === 'player' ? $heroes : $enemies;
        $foes   = $actor->side === 'player' ? $enemies : $heroes;

        // ── Effets de début de tour (DoT / HoT) ───────────────────────────────
        if (!$skipStartOfTurn) {
            $this->processTurnStartEffects($actor, $log);

            // ── Traits de début de tour ───────────────────────────────────────────
            if ($actor->isAlive()) {
                $this->processTurnStartTraits($actor, $allies, $foes, $moonPhase, $log);
            }

            // ── Vérifier si mort après DoT ────────────────────────────────────────
            if (!$actor->isAlive()) {
                $aliveH = !empty(array_filter($heroes,  fn(Combatant $c) => $c->isAlive()));
                $aliveE = !empty(array_filter($enemies, fn(Combatant $c) => $c->isAlive()));
                return ['extraTurn' => false, 'battleOver' => !$aliveH || !$aliveE, 'heroesWin' => $aliveH];
            }
        }

        // ── Vérifier canAct (étourdi / endormi) ───────────────────────────────
        if (!$actor->canAct()) {
            $log[] = new TurnEntry('skip', $actor->id, $actor->name, data: [
                'reason' => $actor->hasEffect('etourdissement') ? 'etourdissement' : 'sommeil',
            ]);
            $this->endOfTurn($actor);
            return ['extraTurn' => false, 'battleOver' => false, 'heroesWin' => false];
        }

        // ── Pré-attaque Dino ──────────────────────────────────────────────────
        $aliveFoes = array_values(array_filter($foes, fn(Combatant $c) => $c->isAlive()));
        $this->processPreAttackDino($actor, $aliveFoes, $log);

        // ── Trouver l'attaque choisie ─────────────────────────────────────────
        $attack = null;
        // Provocation : force le slot 1 (la cible sera forcée par selectTargets)
        if ($actor->hasEffect('provocation')) {
            $slot1  = array_values(array_filter($actor->attacks, fn($a) => $a->getSlotIndex() === 1));
            $attack = $slot1[0] ?? $actor->pickAttack();
        } elseif ($attackId !== null) {
            $silenced = $actor->hasEffect('silence');
            foreach ($actor->attacks as $a) {
                if ($a->getId() === $attackId
                    && ($actor->cooldowns[$attackId] ?? 0) === 0
                    && (!$silenced || $a->getSlotIndex() === 1)
                ) {
                    $attack = $a;
                    break;
                }
            }
        }
        // Fallback sur auto-pick si l'attaque spécifiée est invalide
        if ($attack === null) {
            $attack = $actor->pickAttack();
        }

        if ($attack === null) {
            $this->endOfTurn($actor);
            return ['extraTurn' => false, 'battleOver' => false, 'heroesWin' => false];
        }

        $log[] = new TurnEntry('attack', $actor->id, $actor->name, data: [
            'attackName' => $attack->getName(),
            'slotIndex'  => $attack->getSlotIndex(),
        ]);

        // ── Sélection des cibles ──────────────────────────────────────────────
        $targets = $this->selectTargets($actor, $attack, $allies, $foes);

        // ── Override cible manuelle ───────────────────────────────────────────
        if ($forcedTargetId !== null && !$actor->hasEffect('provocation')) {
            $manualTargetTypes = ['single_enemy', 'random_enemy', 'single_ally', 'random_ally'];
            if (in_array($attack->getTargetType(), $manualTargetTypes, true)) {
                $allCombatants = array_merge($heroes, $enemies);
                foreach ($allCombatants as $c) {
                    if ($c->id === $forcedTargetId && $c->isAlive()) {
                        $targets = [$c];
                        break;
                    }
                }
            }
        }

        // ── Snapshot pour beast kill ──────────────────────────────────────────
        $aliveTargetsBefore = count(array_filter($targets, fn(Combatant $c) => $c->isAlive()));

        // ── Dégâts ────────────────────────────────────────────────────────────
        if ($attack->getScalingStat() !== 'none') {
            $this->applyDamageToTargets($actor, $attack, $targets, $log);
        }

        // ── On-hit passifs ────────────────────────────────────────────────────
        $this->processOnHitPassiveProcs($actor, $targets, $log);

        // ── Post-attaque Dino ─────────────────────────────────────────────────
        $this->processDinoPostAttack($actor, $targets, $log);

        // ── Effets de l'attaque ───────────────────────────────────────────────
        $extraTurn = $this->applyAttackEffects($actor, $attack, $targets, $allies, $foes, $log);

        // ── Beast kill extra tour ─────────────────────────────────────────────
        $aliveTargetsAfter = count(array_filter($targets, fn(Combatant $c) => $c->isAlive()));
        $beastKill = $aliveTargetsBefore > $aliveTargetsAfter
            && ($actor->passiveTraits['beast_kill_extra_attack'] ?? false);

        // ── Fracture des Dieux extra tour ─────────────────────────────────────
        if (!$extraTurn && isset($actor->passiveTraits['passive_extra_turn_chance'])) {
            if (random_int(0, 99) < $actor->passiveTraits['passive_extra_turn_chance']) {
                $extraTurn = true;
                $log[] = new TurnEntry('effect_apply', $actor->id, $actor->name, data: [
                    'effect'   => 'extra_turn',
                    'label'    => 'Tour supplémentaire (Fracture des Dieux)',
                    'polarity' => 'positive',
                ]);
            }
        }

        $extraTurn = $extraTurn || $beastKill;

        // ── Cooldown ──────────────────────────────────────────────────────────
        if ($attack->getCooldown() > 0) {
            $actor->cooldowns[$attack->getId()] = $attack->getCooldown();
        }

        // ── Fin de tour ───────────────────────────────────────────────────────
        $this->endOfTurn($actor, skipEffectTick: $extraTurn);

        // ── Vérifier fin de combat ────────────────────────────────────────────
        $aliveH = !empty(array_filter($heroes,  fn(Combatant $c) => $c->isAlive()));
        $aliveE = !empty(array_filter($enemies, fn(Combatant $c) => $c->isAlive()));

        return [
            'extraTurn'  => $extraTurn && $actor->isAlive(),
            'battleOver' => !$aliveH || !$aliveE,
            'heroesWin'  => $aliveH,
        ];
    }
}
