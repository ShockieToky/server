<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Fracture des Dieux — paliers 2 / 5.
 *
 * Après une attaque — chance d'obtenir un tour supplémentaire.
 *
 * Palier 2 : 10 % de chance.
 * Palier 5 : 18 %.
 */
class GodsFracturePassive implements PassiveInterface
{
    public function getSlug(): string { return 'gods_fracture'; }
    public function thresholds(): array { return [2, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 2) return;

        $chance = $count >= 5 ? 18 : 10;
        $context->passiveTraits['passive_extra_turn_chance'] = $chance;
        $context->activeEffects[] = "Fracture des Dieux : $chance % de tour supplémentaire";
    }
}
