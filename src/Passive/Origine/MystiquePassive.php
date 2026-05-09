<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Mystique — paliers 1 / 2 / 3.
 *
 * Réduit les dégâts subis (réduction plate, appliquée dans Combatant::takeDamage).
 *
 * Palier 1 : −5 % de dégâts subis.
 * Palier 2 : −10 %.
 * Palier 3 : −15 %.
 */
class MystiquePassive implements PassiveInterface
{
    public function getSlug(): string { return 'mystique'; }
    public function thresholds(): array { return [1, 2, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $reduction = match (true) {
            $count >= 3 => 15.0,
            $count >= 2 => 10.0,
            default     =>  5.0,
        };

        $context->damageReductionPct = max($context->damageReductionPct, $reduction);
        $context->activeEffects[] = "Mystique : −$reduction % dégâts subis";
    }
}
