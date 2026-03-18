<?php

namespace App\Passive\Faction;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Maître de l'Eau — paliers 2 / 4 / 6.
 *
 * Palier 2 : Bouclier initial de 5 % des PV max.
 * Palier 4 : Soin de 5 % des PV max au début de chaque tour.
 * Palier 6 : 20 % de chance de bloquer (réduire de 25 %) chaque coup entrant.
 */
class WaterMasterPassive implements PassiveInterface
{
    public function getSlug(): string { return 'water_master'; }
    public function thresholds(): array { return [2, 4, 6]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveFactionCount();
        if ($count < 2) return;

        $effects = [];

        if ($count >= 2) {
            $context->initialShieldPct = max($context->initialShieldPct, 5.0);
            $effects[] = 'Bouclier initial +5 % PV';
        }
        if ($count >= 4) {
            $context->passiveTraits['water_turn_heal_pct'] = 5.0;
            $effects[] = 'Soin +5 % PV/tour';
        }
        if ($count >= 6) {
            $context->passiveTraits['water_block_chance'] = 20;
            $effects[] = 'Blocage 20 %';
        }

        $context->activeEffects[] = 'Maître de l\'Eau : ' . implode(', ', $effects);
    }
}
