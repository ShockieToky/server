<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Île de la Lune — paliers 2 / 5.
 *
 * À chaque coup — chance d'infliger Silence 1 tour à la cible.
 *
 * Palier 2 : 6 % de chance.
 * Palier 5 : 9 %.
 */
class MoonIslandPassive implements PassiveInterface
{
    public function getSlug(): string { return 'moon_island'; }
    public function thresholds(): array { return [2, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 2) return;

        $chance = $count >= 5 ? 9 : 6;

        $context->passiveTraits['silence_proc_chance'] = $chance;
        $context->activeEffects[] = "Île de la Lune : $chance % de chance de Silence";
    }
}
