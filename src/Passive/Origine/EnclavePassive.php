<?php

namespace App\Passive\Origine;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Enclave — paliers 1 / 3 / 5.
 *
 * Bonus de dégâts selon la direction (nord / sud) choisie par le joueur.
 *
 * Palier 1 :  +4 % de dégâts aux ennemis de la direction opposée.
 * Palier 3 :  +7 %.
 * Palier 5 : +10 %.
 *
 * La direction ('nord' ou 'sud') est fournie par le contrôleur via passiveTraits['enclave_direction'].
 */
class EnclavePassive implements PassiveInterface
{
    public function getSlug(): string { return 'enclave'; }
    public function thresholds(): array { return [1, 3, 5]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveOrigineCount();
        if ($count < 1) return;

        $bonus = match (true) {
            $count >= 5 => 10.0,
            $count >= 3 =>  7.0,
            default     =>  4.0,
        };

        $context->passiveTraits['enclave_bonus_pct'] = $bonus;
        // enclave_direction will be set by StoryFightController after passive application
        $context->activeEffects[] = "Enclave : +$bonus % de dégâts directionnels";
    }
}
