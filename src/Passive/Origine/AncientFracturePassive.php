<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Ancienne Fracture — paliers 2 / 5.
 *
 * À chaque coup entrant — chance de bloquer une partie des dégâts.
 *
 * Palier 2 : 15 % de chance de bloquer 20 % des dégâts du coup.
 * Palier 5 : 25 % de chance de bloquer 35 % des dégâts du coup.
 */
class AncientFracturePassive implements PassiveInterface
{
    public function getSlug(): string { return 'ancient_fracture'; }
    public function thresholds(): array { return [2, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 2) return;

        [$chance, $pct] = $count >= 5 ? [25, 35.0] : [15, 20.0];

        $context->passiveTraits['fracture_block_chance'] = $chance;
        $context->passiveTraits['fracture_block_pct']    = $pct;
        $context->activeEffects[] = "Ancienne Fracture : $chance % de blocage ($pct %)";
    }
}
