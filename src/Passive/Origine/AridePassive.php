<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Aride — paliers 1 / 3 / 5.
 *
 * Réduit la précision des ennemis.
 *
 * Palier 1 : −5 de précision ennemie.
 * Palier 3 : −8.
 * Palier 5 : −12.
 */
class AridePassive implements PassiveInterface
{
    public function getSlug(): string { return 'aride'; }
    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $debuff = match (true) {
            $count >= 5 => 12,
            $count >= 3 =>  8,
            default     =>  5,
        };

        $context->foesAccuracyDebuffPct = max($context->foesAccuracyDebuffPct, $debuff);
        $context->activeEffects[] = "Aride : −$debuff précision ennemis";
    }
}
