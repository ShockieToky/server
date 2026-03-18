<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Passif d'origine : "Héritage Nomade"
 *
 * Les combattants d'origine nomade sont agiles et difficiles à saisir.
 *   1 allié  → +8 % VIT
 *   3 alliés → +18 % VIT, +10 % DEF
 *   5 alliés → +30 % VIT, +20 % DEF, +15 % soin reçu
 */
class HeritageNomadePassive implements PassiveInterface
{
    public function getSlug(): string
    {
        return 'heritage_nomade';
    }

    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();

        if ($count >= 5) {
            $context->speedMultiplier   += 0.30;
            $context->defenseMultiplier += 0.20;
            $context->healingMultiplier += 0.15;
            $context->activeEffects[]    = 'Héritage Nomade [5] : +30% VIT, +20% DEF, +15% soin';
        } elseif ($count >= 3) {
            $context->speedMultiplier   += 0.18;
            $context->defenseMultiplier += 0.10;
            $context->activeEffects[]    = 'Héritage Nomade [3] : +18% VIT, +10% DEF';
        } elseif ($count >= 1) {
            $context->speedMultiplier   += 0.08;
            $context->activeEffects[]    = 'Héritage Nomade [1] : +8% VIT';
        }
    }
}
