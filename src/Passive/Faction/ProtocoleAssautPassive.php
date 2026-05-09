<?php

namespace App\Passive\Faction;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Passif de faction : "Protocole d'assaut"
 *
 * Les unités de la faction coordonnent leurs attaques.
 *   1 allié  → +10 % ATK
 *   2 alliés → +25 % ATK, +10 % chance de critique
 *   3 alliés → +45 % ATK, +20 % chance de critique, +30 % dégâts de critique
 */
class ProtocoleAssautPassive implements PassiveInterface
{
    public function getSlug(): string
    {
        return 'protocole_assaut';
    }

    public function thresholds(): array { return [1, 2, 3]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveFactionCount();

        if ($count >= 3) {
            $context->attackMultiplier  += 0.45;
            $context->critChanceBonus   += 0.20;
            $context->critDamageBonus   += 0.30;
            $context->activeEffects[]    = 'Protocole d\'assaut [3] : +45% ATK, +20% critique, +30% dégâts critique';
        } elseif ($count >= 2) {
            $context->attackMultiplier  += 0.25;
            $context->critChanceBonus   += 0.10;
            $context->activeEffects[]    = 'Protocole d\'assaut [2] : +25% ATK, +10% critique';
        } elseif ($count >= 1) {
            $context->attackMultiplier  += 0.10;
            $context->activeEffects[]    = 'Protocole d\'assaut [1] : +10% ATK';
        }
    }
}
