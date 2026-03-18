<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Désert — paliers 1 / 3 / 5.
 *
 * Au début du tour — chance de nettoyer un effet négatif et de se soigner.
 *
 * Palier 1 : 20 % de chance ; soin de 4 % des PV max.
 * Palier 3 : 30 %            ; soin de 6 % des PV max.
 * Palier 5 : 40 %            ; soin de 9 % des PV max.
 */
class DesertPassive implements PassiveInterface
{
    public function getSlug(): string { return 'desert'; }
    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        [$chance, $heal] = match (true) {
            $count >= 5 => [40, 9.0],
            $count >= 3 => [30, 6.0],
            default     => [20, 4.0],
        };

        $context->passiveTraits['desert_cleanse_chance'] = $chance;
        $context->passiveTraits['desert_heal_pct']       = $heal;
        $context->activeEffects[] = "Désert : $chance % de purge + soin $heal %";
    }
}
