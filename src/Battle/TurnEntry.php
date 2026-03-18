<?php

namespace App\Battle;

/**
 * Entrée de log pour une action de combat (attaque, effet, fin de tour…).
 */
class TurnEntry
{
    /**
     * @param string        $type      Type d'événement :
     *                                   'attack'       — Une unité utilise un sort
     *                                   'damage'       — Un hit inflige des dégâts
     *                                   'effect_apply' — Un effet est posé sur une cible
     *                                   'effect_tick'  — Effet de durée déclenché (DoT/HoT)
     *                                   'effect_expire'— Un effet expire
     *                                   'heal'         — Soin déclenché
     *                                   'death'        — Une unité tombe
     *                                   'wave_start'   — Nouvelle vague
     *                                   'wave_clear'   — Vague terminée
     *                                   'skip'         — Tour passé (étourdi/endormi)
     * @param string        $actorId   Identifiant du combattant qui agit
     * @param string        $actorName Nom affiché de l'acteur
     * @param string|null   $targetId
     * @param string|null   $targetName
     * @param array<string,mixed> $data Données spécifiques à l'événement
     */
    public function __construct(
        public readonly string  $type,
        public readonly string  $actorId,
        public readonly string  $actorName,
        public readonly ?string $targetId   = null,
        public readonly ?string $targetName = null,
        public readonly array   $data       = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type'       => $this->type,
            'actorId'    => $this->actorId,
            'actorName'  => $this->actorName,
            'targetId'   => $this->targetId,
            'targetName' => $this->targetName,
            'data'       => $this->data,
        ];
    }
}
