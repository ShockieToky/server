<?php

namespace App\Passive\Faction;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Ordre du Feu — paliers 1 / 3 / 6 alliés de même faction.
 *
 * Proc post-attaque : chance d'infliger brûlure 1 tour.
 * Les brûlures sont plus efficaces (+ bonus % sur les dégâts par tour).
 *   1 : 20 % chance, brûlure +1 %
 *   3 : 30 % chance, brûlure +2 %
 *   6 : 50 % chance, brûlure +3 %
 */
class FireOrderPassive implements PassiveInterface
{
    public function getSlug(): string { return 'fire_order'; }
    public function thresholds(): array { return [1, 3, 6]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveFactionCount();
        if ($count < 1) return;

        [$chance, $bonus] = match (true) {
            $count >= 6 => [50, 3.0],
            $count >= 3 => [30, 2.0],
            default     => [20, 1.0],
        };

        $context->passiveTraits['fire_proc_chance']    = $chance;
        $context->passiveTraits['fire_burn_bonus_pct'] = $bonus;
        $context->activeEffects[] = "Ordre du Feu [$count] : $chance% brûlure, brûlure +{$bonus}%";
    }
}
