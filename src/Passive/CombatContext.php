<?php

namespace App\Passive;

/**
 * Contexte passé à chaque passif au moment du calcul de combat.
 *
 * Contient les compteurs d'alliés et les stats modifiables du héros.
 * Les passifs lisent les compteurs et écrivent dans les modificateurs.
 */
class CombatContext
{
    /** Nombre de héros alliés (hors soi-même) partageant la même faction. */
    public int $alliedFactionCount = 0;

    /** Nombre de héros alliés (hors soi-même) partageant la même origine. */
    public int $alliedOrigineCount = 0;

    /**
     * Bonus de faction sélectionné par le joueur avant combat (+2 max).
     * Ajouter cette valeur à alliedFactionCount pour simuler le choix joueur.
     */
    public int $playerFactionBonus = 0;

    /**
     * Bonus d'origine sélectionné par le joueur avant combat (+1 max).
     */
    public int $playerOrigineBonus = 0;

    // --- Modificateurs de stats (accumulés par tous les passifs) ---

    public float $attackMultiplier   = 1.0;
    public float $defenseMultiplier  = 1.0;
    public float $speedMultiplier    = 1.0;
    public float $critChanceBonus    = 0.0;  // valeur absolue, ex: 0.15 = +15 %
    public float $critDamageBonus    = 0.0;
    public float $healingMultiplier  = 1.0;

    /**
     * Effets textuels actifs (pour affichage en UI).
     * @var string[]
     */
    public array $activeEffects = [];

    // --- Helpers ---

    public function effectiveFactionCount(): int
    {
        return $this->alliedFactionCount + $this->playerFactionBonus;
    }

    public function effectiveOrigineCount(): int
    {
        return $this->alliedOrigineCount + $this->playerOrigineBonus;
    }
}
