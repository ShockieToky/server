<?php

namespace App\Passive\Faction;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Clan des Bêtes — paliers 1 / 3 / 6.
 *
 * À chaque début de tour : chance d'enrager pour 2 tours
 * (enrage = +10 % ATK / DEF / VIT).
 * Si l'unité enragée tue une cible, elle attaque une deuxième fois.
 *
 *   1 : 15 % chance
 *   3 : 30 %
 *   6 : 50 %
 */
class BeastClanPassive implements PassiveInterface
{
    public function getSlug(): string { return 'beast_clan'; }
    public function thresholds(): array { return [1, 3, 6]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveFactionCount();
        if ($count < 1) return;

        $chance = match (true) {
            $count >= 6 => 50,
            $count >= 3 => 30,
            default     => 15,
        };

        $context->passiveTraits['beast_rage_chance']       = $chance;
        $context->passiveTraits['beast_kill_extra_attack'] = true;
        $context->activeEffects[] = "Clan des Bêtes [$count] : $chance% enrage, attaque si kill";
    }
}
