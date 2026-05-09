<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Ancienne Fracture — paliers 1 / 3.
 *
 * À chaque coup entrant — chance de bloquer une partie des dégâts.
 *
 * Palier 1 : 15 % de chance de bloquer 20 % des dégâts du coup.
 * Palier 3 : 25 % de chance de bloquer 35 % des dégâts du coup.
 */
class AncientFracturePassive implements PassiveInterface
{
    public function getSlug(): string { return 'ancient_fracture'; }
    public function thresholds(): array { return [1, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        [$chance, $pct] = $count >= 3 ? [25, 35.0] : [15, 20.0];

        $context->passiveTraits['fracture_block_chance'] = $chance;
        $context->passiveTraits['fracture_block_pct']    = $pct;
        $context->activeEffects[] = "Ancienne Fracture : $chance % de blocage ($pct %)";
    }
}
