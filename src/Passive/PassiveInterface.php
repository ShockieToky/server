<?php

namespace App\Passive;

/**
 * Contrat de tous les passifs de faction et d'origine.
 *
 * Chaque passif est identifié par un slug unique (getSlug())
 * qui correspond à la valeur de passiveName en base de données.
 *
 * apply() reçoit le CombatContext et le modifie directement —
 * pas de valeur de retour, ce qui permet d'enchaîner plusieurs passifs.
 */
interface PassiveInterface
{
    /**
     * Identifiant unique du passif, en snake_case.
     * Doit correspondre exactement à Faction::passiveName ou Origine::passiveName.
     */
    public function getSlug(): string;

    /**
     * Paliers de héros requis pour activer / monter de tier le passif.
     * Exemple : [1, 3, 6] signifie « tier 1 à partir de 1 allié, tier 2 à 3, tier 3 à 6 ».
     *
     * @return list<int>
     */
    public function thresholds(): array;

    /**
     * Applique les effets du passif sur le contexte de combat.
     * Lit les compteurs d'alliés dans $context et modifie ses modificateurs.
     */
    public function apply(CombatContext $context): void;
}
