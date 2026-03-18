<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Fracture des Dieux — paliers 1 / 3.
 *
 * Après une attaque — chance d'obtenir un tour supplémentaire.
 *
 * Palier 1 : 10 % de chance.
 * Palier 3 : 18 %.
 */
class GodsFracturePassive implements PassiveInterface
{
    public function getSlug(): string { return 'gods_fracture'; }
    public function thresholds(): array { return [1, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $chance = $count >= 3 ? 18 : 10;
        $context->passiveTraits['passive_extra_turn_chance'] = $chance;
        $context->activeEffects[] = "Fracture des Dieux : $chance % de tour supplémentaire";
    }
}
