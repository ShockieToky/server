<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Mystique — paliers 1 / 3 / 5.
 *
 * Réduit les dégâts subis (réduction plate, appliquée dans Combatant::takeDamage).
 *
 * Palier 1 : −5 % de dégâts subis.
 * Palier 3 : −10 %.
 * Palier 5 : −15 %.
 */
class MystiquePassive implements PassiveInterface
{
    public function getSlug(): string { return 'mystique'; }
    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $reduction = match (true) {
            $count >= 5 => 15.0,
            $count >= 3 => 10.0,
            default     =>  5.0,
        };

        $context->damageReductionPct = max($context->damageReductionPct, $reduction);
        $context->activeEffects[] = "Mystique : −$reduction % dégâts subis";
    }
}
