<?php

namespace App\Battle;

/**
 * Représente un effet actif (buff ou debuff) sur un combattant.
 *
 * Les effets instantanés ne sont JAMAIS stockés ici : ils sont résolus
 * immédiatement lors de l'application de l'attaque.
 */
class ActiveEffect
{
    public function __construct(
        /** Identifiant fonctionnel (ex: 'brulure', 'aug_attaque'). */
        public readonly string $name,
        /** Libellé affiché (ex: 'Brûlure'). */
        public readonly string $label,
        /** 'positive' | 'negative' */
        public readonly string $polarity,
        /** Tours restants (>= 1). Décrémenté à la fin du tour de l'unité portante. */
        public int $remainingTurns,
        /** Valeur numérique (%, flat…). Ex: 30.0 pour 30% d'attaque en plus. */
        public readonly float $value,
        /** PV de bouclier restants (uniquement pour l'effet 'bouclier'). */
        public float $shieldHp = 0.0,
        /**
         * Marqueur « appliqué ce tour-ci ».
         * Le premier appel à tickEffects() l'ignore et le passe à false,
         * garantissant qu'un effet durée=1 survit au tick de fin du tour où il est posé.
         */
        public bool $fresh = true,
    ) {}
}
