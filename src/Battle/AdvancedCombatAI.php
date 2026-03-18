<?php

namespace App\Battle;

use App\Entity\Attack;
use App\Entity\AttackEffect;

/**
 * IA de combat avancée pour les contenus PvP-like (arène, défenses de joueurs…).
 *
 * Activation : $combatant->aiMode = 'advanced'
 *
 * Algorithme : scoring de chaque paire (attaque × cible), choix du meilleur.
 *
 * Facteurs pris en compte :
 *  Kill shot              (+200)   — tuer une cible avec ce coup
 *  Menace                 (0..+80) — cibler l'unité la plus dangereuse
 *  Focus cible basse      (+50/+20)— finir les cibles blessées
 *  Profondeur dégâts      (+30)    — % PV retirés (proportionnel)
 *  CC sur cible sans CC   (+90)    — étourdissement/sommeil/silence/provoc
 *  Doublon CC             (-70)    — ne pas gaspiller un CC
 *  CC sur cible menaçante (+20)    — bonus si cible = la plus forte
 *  Debuff utile           (+40)    — red_defense, brulure…
 *  Debuff doublon         (-30)
 *  red_defense bonus      (+15)    — très précieux avant un allié offensif
 *  Buff utile             (+30)    — aug_attaque, invincibilite…
 *  Buff doublon           (-20)
 *  Buff sur allié fort    (+10)    — maximise l'impact
 *  Soin urgent            (+120)   — allié < 30 % PV
 *  Soin utile             (+40)    — allié 30–60 % PV
 *  Soin inutile           (-50)    — allié > 80 % PV
 *  Purification utile     (+60+10×n)
 *  Bouclier sur blessé    (0..+50) — proportionnel au manque de PV
 *  AoE multi-cibles       (+15/ennemi supplémentaire)
 *  AoE kill possible      (+30/kill)
 *  Préférence slot haut   (+8/niveau)
 */
class AdvancedCombatAI
{
    // ── Constante défensive (doit rester sync. avec BattleService::DEF_CONSTANT) ──
    private const DEF_CONSTANT = 1500.0;

    // ── Poids ─────────────────────────────────────────────────────────────────
    private const W_KILL_SHOT            =  200.0;
    private const W_THREAT_MAX           =   80.0;
    private const W_FOCUS_LOW_HP         =   50.0;
    private const W_FOCUS_MID_HP         =   20.0;
    private const W_DMG_DEPTH            =   30.0;
    private const W_CC_APPLY             =   90.0;
    private const W_CC_DUPLICATE         =  -70.0;
    private const W_CC_BONUS_THREAT      =   20.0;
    private const W_DEBUFF_APPLY         =   40.0;
    private const W_DEBUFF_DUPLICATE     =  -30.0;
    private const W_DEBUFF_REDDEF_BONUS  =   15.0;
    private const W_BUFF_APPLY           =   30.0;
    private const W_BUFF_DUPLICATE       =  -20.0;
    private const W_BUFF_STRONG_ALLY     =   10.0;
    private const W_HEAL_URGENT          =  120.0;
    private const W_HEAL_USEFUL          =   40.0;
    private const W_HEAL_WASTE           =  -50.0;
    private const W_PURIFY_USEFUL        =   60.0;
    private const W_PURIFY_PER_DEBUFF    =   10.0;
    private const W_PURIFY_WASTE         =  -20.0;
    private const W_SHIELD_HP_BONUS      =   50.0;
    private const W_AOE_PER_EXTRA_FOE    =   15.0;
    private const W_AOE_KILL_BONUS       =   30.0;
    private const W_SLOT_BONUS           =    8.0;

    // ── Point d'entrée ────────────────────────────────────────────────────────

    /**
     * Décide de la meilleure action pour $actor.
     *
     * @param Combatant   $actor
     * @param Combatant[] $allies  Alliés de l'acteur (acteur inclus), tous états
     * @param Combatant[] $foes    Ennemis de l'acteur, tous états
     *
     * @return array{attack: ?Attack, targetId: ?string}
     */
    public static function decide(Combatant $actor, array $allies, array $foes): array
    {
        $aliveAllies = array_values(array_filter($allies, fn(Combatant $c) => $c->isAlive()));
        $aliveFoes   = array_values(array_filter($foes,   fn(Combatant $c) => $c->isAlive()));

        $available = self::availableAttacks($actor);
        if (empty($available)) {
            return ['attack' => null, 'targetId' => null];
        }

        // Pré-calcul des menaces (réutilisé pour chaque scoring)
        $threatMap = self::computeThreatMap($aliveFoes, $actor);

        $bestScore    = PHP_INT_MIN;
        $bestAttack   = $available[0]; // fallback garanti
        $bestTargetId = null;

        foreach ($available as $attack) {
            foreach (self::getCandidates($attack, $actor, $aliveAllies, $aliveFoes) as $target) {
                $score = self::scoreDecision($actor, $attack, $target, $aliveAllies, $aliveFoes, $threatMap);
                if ($score > $bestScore) {
                    $bestScore    = $score;
                    $bestAttack   = $attack;
                    $bestTargetId = $target?->id;
                }
            }
        }

        return ['attack' => $bestAttack, 'targetId' => $bestTargetId];
    }

    // ── Sélection des attaques disponibles ────────────────────────────────────

    /** @return Attack[] */
    private static function availableAttacks(Combatant $actor): array
    {
        $silenced  = $actor->hasEffect('silence');
        $available = array_filter(
            $actor->attacks,
            fn(Attack $a) => ($actor->cooldowns[$a->getId() ?? 0] ?? 0) === 0
                && (!$silenced || $a->getSlotIndex() === 1)
        );
        if (empty($available)) {
            $available = array_filter($actor->attacks, fn(Attack $a) => $a->getSlotIndex() === 1);
        }
        return array_values($available);
    }

    // ── Candidats cibles ──────────────────────────────────────────────────────

    /**
     * Retourne les cibles à évaluer pour cette attaque.
     * AoE → [null] (pas de cible forcée, BattleService gère).
     *
     * @return array<?Combatant>
     */
    private static function getCandidates(
        Attack    $attack,
        Combatant $actor,
        array     $aliveAllies,
        array     $aliveFoes,
    ): array {
        return match ($attack->getTargetType()) {
            'all_enemies'                  => [null],
            'all_allies'                   => [null],
            'self'                         => [$actor],
            'single_enemy', 'random_enemy' => !empty($aliveFoes)    ? $aliveFoes    : [null],
            'single_ally',  'random_ally'  => !empty($aliveAllies)   ? $aliveAllies  : [null],
            default                        => !empty($aliveFoes)    ? $aliveFoes    : [null],
        };
    }

    // ── Score principal ───────────────────────────────────────────────────────

    private static function scoreDecision(
        Combatant  $actor,
        Attack     $attack,
        ?Combatant $target,
        array      $aliveAllies,
        array      $aliveFoes,
        array      $threatMap,
    ): float {
        $score = $attack->getSlotIndex() * self::W_SLOT_BONUS;

        // ── Dégâts ────────────────────────────────────────────────────────────
        if ($attack->getScalingStat() !== 'none') {
            $tt = $attack->getTargetType();
            if ($tt === 'all_enemies' || $tt === 'all_allies') {
                $score += self::scoreAoEDamage($actor, $attack, $aliveFoes);
            } elseif ($target !== null && $target->id !== $actor->id) {
                $score += self::scoreSingleTargetDamage($actor, $attack, $target, $aliveFoes, $threatMap);
            }
        }

        // ── Effets ────────────────────────────────────────────────────────────
        foreach ($attack->getAttackEffects() as $ae) {
            if ($ae->getEffect() === null) continue;
            $score += self::scoreEffect($actor, $ae, $target, $aliveAllies, $aliveFoes);
        }

        return $score;
    }

    // ── Dégâts monocible ─────────────────────────────────────────────────────

    private static function scoreSingleTargetDamage(
        Combatant $actor,
        Attack    $attack,
        Combatant $target,
        array     $aliveFoes,
        array     $threatMap,
    ): float {
        $dmg = self::estimateDamage($actor, $attack, $target);

        // Kill shot : priorité absolue, d'autant plus si cible menaçante
        if ($dmg >= $target->currentHp) {
            return self::W_KILL_SHOT + ($threatMap[$target->id] ?? 0.0) * 0.5;
        }

        $score = 0.0;

        // Cibler la menace maximale
        $maxThreat = max(1.0, ...array_values($threatMap ?: [1.0]));
        $score    += (($threatMap[$target->id] ?? 0.0) / $maxThreat) * self::W_THREAT_MAX;

        // Focus cible basse en PV (pour finir)
        $hpPct  = $target->maxHp > 0 ? $target->currentHp / $target->maxHp : 1.0;
        $score += match(true) {
            $hpPct < 0.25 => self::W_FOCUS_LOW_HP,
            $hpPct < 0.50 => self::W_FOCUS_MID_HP,
            default       => 0.0,
        };

        // Profondeur : % PV retirés
        $score += ($dmg / max(1, $target->maxHp)) * self::W_DMG_DEPTH;

        return $score;
    }

    // ── Dégâts AoE ───────────────────────────────────────────────────────────

    private static function scoreAoEDamage(
        Combatant $actor,
        Attack    $attack,
        array     $aliveFoes,
    ): float {
        $count = count($aliveFoes);
        $score = max(0, $count - 1) * self::W_AOE_PER_EXTRA_FOE;

        // Bonus si l'AoE peut tuer une cible
        foreach ($aliveFoes as $foe) {
            $estDmg = self::estimateDamage($actor, $attack, $foe) / max(1, $count);
            if ($estDmg >= $foe->currentHp) {
                $score += self::W_AOE_KILL_BONUS;
            }
        }

        return $score;
    }

    // ── Score d'un effet ──────────────────────────────────────────────────────

    private static function scoreEffect(
        Combatant    $actor,
        AttackEffect $ae,
        ?Combatant   $attackTarget,
        array        $aliveAllies,
        array        $aliveFoes,
    ): float {
        $effect     = $ae->getEffect();
        $effectName = $effect->getName();
        $polarity   = $effect->getPolarity();

        // Effets purement mécaniques, déjà gérés dans applyDamageToTargets
        if (in_array($effectName, ['ignore_defense', 'vampirisme', 'regain_tour'], true)) {
            return 0.0;
        }

        $recipient = self::resolveEffectRecipient(
            $ae->getEffectTarget(), $actor, $attackTarget, $aliveAllies, $aliveFoes
        );

        return $polarity === 'negative'
            ? self::scoreNegativeEffect($effectName, $recipient, $aliveFoes)
            : self::scorePositiveEffect($effectName, $recipient, $actor, $aliveAllies);
    }

    private static function scoreNegativeEffect(
        string     $effectName,
        ?Combatant $recipient,
        array      $aliveFoes,
    ): float {
        if ($recipient === null) return 0.0;

        $isCc = in_array($effectName, ['etourdissement', 'sommeil', 'silence', 'provocation'], true);

        if ($isCc) {
            if ($recipient->hasEffect($effectName)) return self::W_CC_DUPLICATE;
            $score = self::W_CC_APPLY;
            // Bonus si on CC la cible la plus offensive
            if (!empty($aliveFoes)) {
                $maxAtk = max(array_map(fn(Combatant $c) => $c->effectiveAttack(), $aliveFoes));
                if ($recipient->effectiveAttack() >= $maxAtk) {
                    $score += self::W_CC_BONUS_THREAT;
                }
            }
            return $score;
        }

        // Debuff standard
        if ($recipient->hasEffect($effectName)) return self::W_DEBUFF_DUPLICATE;
        $score  = self::W_DEBUFF_APPLY;
        $score += ($effectName === 'red_defense') ? self::W_DEBUFF_REDDEF_BONUS : 0.0;
        return $score;
    }

    private static function scorePositiveEffect(
        string     $effectName,
        ?Combatant $recipient,
        Combatant  $actor,
        array      $aliveAllies,
    ): float {
        if (in_array($effectName, ['soins', 'recuperation'], true)) {
            return self::scoreHeal($recipient, $aliveAllies);
        }
        if ($effectName === 'purification') {
            return self::scorePurify($recipient, $aliveAllies);
        }
        if ($effectName === 'bouclier') {
            $t = $recipient ?? self::mostInjuredAlly($aliveAllies);
            if ($t === null) return 0.0;
            $hpPct = $t->maxHp > 0 ? $t->currentHp / $t->maxHp : 1.0;
            return (1.0 - $hpPct) * self::W_SHIELD_HP_BONUS;
        }

        // Buff générique (aug_attaque, aug_defense, aug_vitesse, invincibilite…)
        if ($recipient === null) return self::W_BUFF_APPLY;
        if ($recipient->hasEffect($effectName)) return self::W_BUFF_DUPLICATE;

        $score = self::W_BUFF_APPLY;
        // Buff offensif : préférer l'allié le plus fort pour maximiser l'impact
        if (in_array($effectName, ['aug_attaque', 'aug_vitesse', 'invincibilite'], true)
            && !empty($aliveAllies)) {
            $maxAtk = max(array_map(fn(Combatant $c) => $c->effectiveAttack(), $aliveAllies));
            if ($recipient->effectiveAttack() >= $maxAtk) {
                $score += self::W_BUFF_STRONG_ALLY;
            }
        }
        return $score;
    }

    private static function scoreHeal(?Combatant $recipient, array $aliveAllies): float
    {
        $t = $recipient ?? self::mostInjuredAlly($aliveAllies);
        if ($t === null) return 0.0;
        $hpPct = $t->maxHp > 0 ? $t->currentHp / $t->maxHp : 1.0;
        return match(true) {
            $hpPct < 0.30 => self::W_HEAL_URGENT,
            $hpPct < 0.60 => self::W_HEAL_USEFUL,
            $hpPct > 0.80 => self::W_HEAL_WASTE,
            default       => 10.0,
        };
    }

    private static function scorePurify(?Combatant $recipient, array $aliveAllies): float
    {
        $t = $recipient ?? self::mostDebuffedAlly($aliveAllies);
        if ($t === null) return 0.0;
        $negCount = count(array_filter(
            $t->activeEffects,
            fn(ActiveEffect $e) => $e->polarity === 'negative'
        ));
        if ($negCount === 0) return self::W_PURIFY_WASTE;
        return self::W_PURIFY_USEFUL + $negCount * self::W_PURIFY_PER_DEBUFF;
    }

    // ── Résolution du destinataire de l'effet ─────────────────────────────────

    /**
     * Détermine quel combatant reçoit l'effet, selon effectTarget.
     * Retourne null pour les AoE (pas de destinataire unique).
     */
    private static function resolveEffectRecipient(
        string     $effectTarget,
        Combatant  $actor,
        ?Combatant $attackTarget,
        array      $aliveAllies,
        array      $aliveFoes,
    ): ?Combatant {
        return match ($effectTarget) {
            'target'       => $attackTarget,
            'self'         => $actor,
            'single_ally'  => self::mostInjuredAlly($aliveAllies),
            'random_ally'  => !empty($aliveAllies) ? $aliveAllies[0] : null,
            'single_enemy' => !empty($aliveFoes)   ? $aliveFoes[0]   : null,
            'random_enemy' => !empty($aliveFoes)   ? $aliveFoes[0]   : null,
            // AoE — pas de destinataire unique à scorer
            'all_allies', 'all_enemies' => null,
            default        => $attackTarget,
        };
    }

    // ── Estimation des dégâts ─────────────────────────────────────────────────

    private static function estimateDamage(Combatant $actor, Attack $attack, Combatant $target): float
    {
        $baseStat = match ($attack->getScalingStat()) {
            'atk'  => $actor->effectiveAttack(),
            'def'  => $actor->effectiveDefense(),
            'hp'   => $actor->maxHp,
            'spd'  => $actor->effectiveSpeed(),
            default => 0,
        };
        if ($baseStat <= 0) return 0.0;

        $raw     = $baseStat * $attack->getScalingPct() / 100.0;
        $def     = $target->effectiveDefense();
        $defMult = 1.0 - $def / ($def + self::DEF_CONSTANT);

        // Espérance mathématique du multiplicateur de crit :
        // E[mult] = (1 - critRate%) × 1.0 + critRate% × (1 + critDamage%)
        //         = 1 + critRate% × critDamage%
        $critExpected = 1.0 + ($actor->critRate / 100.0) * ($actor->critDamage / 100.0);

        return $raw * $defMult * $critExpected * max(1, $attack->getHitCount());
    }

    // ── Carte des menaces ─────────────────────────────────────────────────────

    /**
     * Score de danger de chaque ennemi vis-à-vis de $actor.
     * Menace ≈ DPS estimé que l'ennemi peut infliger à $actor.
     *
     * @param  Combatant[] $foes
     * @return array<string, float>  clé = Combatant::$id
     */
    private static function computeThreatMap(array $foes, Combatant $actor): array
    {
        $map = [];
        foreach ($foes as $foe) {
            if (!$foe->isAlive()) continue;

            $bestScaling = 100.0;
            foreach ($foe->attacks as $atk) {
                if (in_array($atk->getScalingStat(), ['atk', 'def', 'hp', 'spd'], true)
                    && $atk->getScalingPct() > $bestScaling) {
                    $bestScaling = $atk->getScalingPct();
                }
            }

            $rawDps        = $foe->effectiveAttack() * $bestScaling / 100.0;
            $def           = $actor->effectiveDefense();
            $defMult       = 1.0 - $def / ($def + self::DEF_CONSTANT);
            $map[$foe->id] = $rawDps * $defMult;
        }
        return $map;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function mostInjuredAlly(array $aliveAllies): ?Combatant
    {
        if (empty($aliveAllies)) return null;
        return array_reduce(
            $aliveAllies,
            fn(?Combatant $carry, Combatant $c) =>
                $carry === null
                || ($c->currentHp / max(1, $c->maxHp)) < ($carry->currentHp / max(1, $carry->maxHp))
                    ? $c : $carry
        );
    }

    private static function mostDebuffedAlly(array $aliveAllies): ?Combatant
    {
        if (empty($aliveAllies)) return null;
        return array_reduce(
            $aliveAllies,
            fn(?Combatant $carry, Combatant $c) =>
                $carry === null
                || count(array_filter($c->activeEffects,     fn(ActiveEffect $e) => $e->polarity === 'negative'))
                 > count(array_filter($carry->activeEffects, fn(ActiveEffect $e) => $e->polarity === 'negative'))
                    ? $c : $carry
        );
    }
}
