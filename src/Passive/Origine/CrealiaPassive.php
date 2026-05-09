<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Créalia — paliers 1 / 2.
 *
 * Augmente la vitesse de base (bonus plat ajouté après calcul du multiplicateur).
 *
 * Palier 1 : +7 de vitesse.
 * Palier 2 : +14.
 */
class CrealiaPassive implements PassiveInterface
{
    public function getSlug(): string { return 'crealia'; }
    public function thresholds(): array { return [1, 2]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $bonus = $count >= 2 ? 14 : 7;

        $context->flatSpeedBonus += $bonus;
        $context->activeEffects[] = "Créalia : +$bonus de vitesse";
    }
}
