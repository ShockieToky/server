<?php

namespace App\Passive\Faction;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Culte de la Lune — paliers 1 / 3 / 6.
 *
 * À chaque début de tour d'un héros de cette faction, la lune change de phase
 * (cycle : Nouvelle → Demi → Pleine → Nouvelle…).
 *
 * Nouvelle lune : soigne tous les alliés de X % PV max
 *   Tier 1 : 5 % | Tier 2 : 7 % | Tier 3 : 10 %
 *
 * Demi-Lune : prolonge la durée de tous les effets bénéfiques alliés
 *   Tier 1 : +1 tour | Tier 2 : +1 | Tier 3 : +2
 *
 * Pleine Lune : applique aug_attaque 2 tours à ce héros
 *   Tier 1 : +5 % | Tier 2 : +10 % | Tier 3 : +15 %
 */
class MoonCultPassive implements PassiveInterface
{
    public function getSlug(): string { return 'moon_cult'; }
    public function thresholds(): array { return [1, 3, 6]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveFactionCount();
        if ($count < 1) return;

        $tier = match (true) {
            $count >= 6 => 3,
            $count >= 3 => 2,
            default     => 1,
        };

        $context->passiveTraits['moon_cult_tier'] = $tier;
        $context->activeEffects[] = "Culte de la Lune [tier $tier] : cycle lunaire actif";
    }
}
