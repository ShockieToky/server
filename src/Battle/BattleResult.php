<?php

namespace App\Battle;

/**
 * Résultat complet d'un combat de scénario.
 */
class BattleResult
{
    /**
     * @param bool         $victory         true = les héros ont gagné
     * @param TurnEntry[]  $log             Chronologie complète du combat
     * @param int          $wavesCleared    Nombre de vagues éliminées
     * @param int          $totalWaves      Nombre total de vagues dans le stage
     * @param array<string,int> $heroHpLeft  { combatantId => currentHp } à la fin
     */
    public function __construct(
        public readonly bool  $victory,
        public readonly array $log,
        public readonly int   $wavesCleared,
        public readonly int   $totalWaves,
        public readonly array $heroHpLeft,
    ) {}

    public function toArray(): array
    {
        return [
            'victory'      => $this->victory,
            'wavesCleared' => $this->wavesCleared,
            'totalWaves'   => $this->totalWaves,
            'heroHpLeft'   => $this->heroHpLeft,
            'log'          => array_map(fn(TurnEntry $e) => $e->toArray(), $this->log),
        ];
    }
}
