<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Île de la Lune — paliers 1 / 3.
 *
 * À chaque coup — chance d'infliger Silence 1 tour à la cible.
 *
 * Palier 1 : 6 % de chance.
 * Palier 3 : 9 %.
 */
class MoonIslandPassive implements PassiveInterface
{
    public function getSlug(): string { return 'moon_island'; }
    public function thresholds(): array { return [1, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $chance = $count >= 3 ? 9 : 6;

        $context->passiveTraits['silence_proc_chance'] = $chance;
        $context->activeEffects[] = "Île de la Lune : $chance % de chance de Silence";
    }
}
