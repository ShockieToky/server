<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Créalia — paliers 1 / 3 / 5.
 *
 * Augmente la vitesse de base (bonus plat ajouté après calcul du multiplicateur).
 *
 * Palier 1 : +5 de vitesse.
 * Palier 3 : +9.
 * Palier 5 : +13.
 */
class CrealiaPassive implements PassiveInterface
{
    public function getSlug(): string { return 'crealia'; }
    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $bonus = match (true) {
            $count >= 5 => 13,
            $count >= 3 =>  9,
            default     =>  5,
        };

        $context->flatSpeedBonus += $bonus;
        $context->activeEffects[] = "Créalia : +$bonus de vitesse";
    }
}
