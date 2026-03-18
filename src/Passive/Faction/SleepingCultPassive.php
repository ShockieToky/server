<?php

namespace App\Passive\Faction;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Occultiste du Sommeil — paliers 1 / 3 / 6.
 *
 * Proc post-attaque : chance d'endormir la cible.
 * Si la cible est DÉJÀ endormie, les dégâts sont amplifiés.
 *   1 : 5 % sommeil, +20 % dégâts vs endormi
 *   3 : 8 % sommeil, +30 %
 *   6 : 12 % sommeil, +45 %
 */
class SleepingCultPassive implements PassiveInterface
{
    public function getSlug(): string { return 'sleeping_cult'; }
    public function thresholds(): array { return [1, 3, 6]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveFactionCount();
        if ($count < 1) return;

        [$chance, $bonus] = match (true) {
            $count >= 6 => [12, 45.0],
            $count >= 3 => [8,  30.0],
            default     => [5,  20.0],
        };

        $context->passiveTraits['sleep_proc_chance']   = $chance;
        $context->passiveTraits['sleep_dmg_bonus_pct'] = $bonus;
        $context->activeEffects[] = "Occultiste du Sommeil [$count] : $chance% sommeil, +{$bonus}% dégâts vs endormi";
    }
}
