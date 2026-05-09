<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Aride — paliers 1 / 2 / 3.
 *
 * Réduit la précision des ennemis.
 *
 * Palier 1 : −5 de précision ennemie.
 * Palier 2 : −8.
 * Palier 3 : −12.
 */
class AridePassive implements PassiveInterface
{
    public function getSlug(): string { return 'aride'; }
    public function thresholds(): array { return [1, 2, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $debuff = match (true) {
            $count >= 3 => 12,
            $count >= 2 =>  8,
            default     =>  5,
        };

        $context->foesAccuracyDebuffPct = max($context->foesAccuracyDebuffPct, $debuff);
        $context->activeEffects[] = "Aride : −$debuff précision ennemis";
    }
}
