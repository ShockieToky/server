<?php

namespace App\Passive\Faction;

use App\Passive\CombatContext;
use App\Passive\PassiveInterface;

/**
 * Clan des Dinosaures — paliers 1 / 3 / 6.
 *
 * Le héros dispose d'un compagnon dinosaure qui agit en combat.
 *
 * Palier 1 (Bébé Dino) :
 *   Pré-attaque : 30 % de chance d'attaquer l'ennemi le plus faible
 *   → 20 % ATK du héros, 5 % de chance de réduire la défense 1 tour.
 *
 * Palier 2 (Dino Imposant) :
 *   Post-attaque : 40 % de chance d'infliger brûlure 2 tours à la/les cible(s).
 *
 * Palier 3 (Tyrannosaure) :
 *   Post-attaque : 35 % de chance d'un coup supplémentaire (50 % ATK)
 *   + tentative d'étourdissement 1 tour.
 */
class DinosaurClanPassive implements PassiveInterface
{
    public function getSlug(): string { return 'dinosaur_clan'; }
    public function thresholds(): array { return [1, 3, 6]; }

    public function apply(CombatContext $context): void
    {
        $count = $context->effectiveFactionCount();
        if ($count < 1) return;

        // Seul le héros le plus à droite (dernier de l'équipe) reçoit le dino.
        if ($context->heroIndex !== $context->teamSize - 1) return;

        $tier = match (true) {
            $count >= 6 => 3,
            $count >= 3 => 2,
            default     => 1,
        };

        $context->passiveTraits['dino_tier'] = $tier;
        $label = ['Bébé Dino', 'Dino Imposant', 'Tyrannosaure'][$tier - 1];
        $context->activeEffects[] = "Clan des Dinosaures [tier $tier] : $label actif";
    }
}
