<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Créalia — paliers 2 / 3.
 *
 * Augmente la vitesse de base (bonus plat ajouté après calcul du multiplicateur).
 *
 * Palier 2 : +7 de vitesse.
 * Palier 3 : +14.
 */
class CrealiaPassive implements PassiveInterface
{
    public function getSlug(): string { return 'crealia'; }
    public function thresholds(): array { return [2, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 2) return;

        $bonus = $count >= 3 ? 14 : 7;

        $context->flatSpeedBonus += $bonus;
        $context->activeEffects[] = "Créalia : +$bonus de vitesse";
    }
}
