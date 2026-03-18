<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Île de la Lune — paliers 1 / 3 / 5.
 *
 * À chaque coup — chance d'infliger Silence 1 tour à la cible.
 *
 * Palier 1 : 10 % de chance.
 * Palier 3 : 18 %.
 * Palier 5 : 25 %.
 */
class MoonIslandPassive implements PassiveInterface
{
    public function getSlug(): string { return 'moon_island'; }
    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $chance = match (true) {
            $count >= 5 => 25,
            $count >= 3 => 18,
            default     => 10,
        };

        $context->passiveTraits['silence_proc_chance'] = $chance;
        $context->activeEffects[] = "Île de la Lune : $chance % de chance de Silence";
    }
}
