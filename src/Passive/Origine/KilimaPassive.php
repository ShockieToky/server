<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Kilima — paliers 1 / 3.
 *
 * Palier 1 : Bouclier initial de 6 % des PV max.
 * Palier 3 : Bouclier initial de 12 % des PV max.
 */
class KilimaPassive implements PassiveInterface
{
    public function getSlug(): string { return 'kilima'; }
    public function thresholds(): array { return [1, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $shieldPct = $count >= 3 ? 12.0 : 6.0;
        $context->initialShieldPct = max($context->initialShieldPct, $shieldPct);
        $context->activeEffects[] = "Kilima : Bouclier initial $shieldPct % PV";
    }
}
