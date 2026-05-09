<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Samora — paliers 1 / 2 / 3.
 *
 * Augmente la résistance (plafonnée à 100 dans le contrôleur).
 *
 * Palier 1 : +5 de résistance.
 * Palier 2 : +10.
 * Palier 3 : +15.
 */
class SamoraPassive implements PassiveInterface
{
    public function getSlug(): string { return 'samora'; }
    public function thresholds(): array { return [1, 2, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $bonus = match (true) {
            $count >= 3 => 15,
            $count >= 2 => 10,
            default     =>  5,
        };

        $context->resistanceBonus += $bonus;
        $context->activeEffects[] = "Samora : +$bonus de résistance";
    }
}
