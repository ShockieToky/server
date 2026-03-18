<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Samora — paliers 1 / 3 / 5.
 *
 * Augmente la résistance (plafonnée à 100 dans le contrôleur).
 *
 * Palier 1 : +5 de résistance.
 * Palier 3 : +10.
 * Palier 5 : +15.
 */
class SamoraPassive implements PassiveInterface
{
    public function getSlug(): string { return 'samora'; }
    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $bonus = match (true) {
            $count >= 5 => 15,
            $count >= 3 => 10,
            default     =>  5,
        };

        $context->resistanceBonus += $bonus;
        $context->activeEffects[] = "Samora : +$bonus de résistance";
    }
}
