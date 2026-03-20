<?php

namespace App\Service;

use App\Entity\Faction;
use App\Entity\Origine;
use App\Passive\CombatContext;

/**
 * Point d'entrée pour appliquer les passifs en combat.
 *
 * Délègue la logique métier au PassiveRegistry et aux classes passif.
 */
class BonusResolverService
{
    public function __construct(
        private readonly PassiveRegistry $registry,
    ) {
    }

    /**
     * Applique le passif de faction sur le contexte de combat.
     */
    public function applyFactionPassive(Faction $faction, CombatContext $context): void
    {
        $passive = $this->registry->get($faction->getPassiveName());
        $passive?->apply($context);
    }

    /**
     * Applique le passif d'origine sur le contexte de combat.
     */
    public function applyOriginePassive(Origine $origine, CombatContext $context): void
    {
        $passive = $this->registry->get($origine->getPassiveName());
        $passive?->apply($context);
    }

    /**
     * Applique tous les passifs d'un héros en une seule passe.
     *
     * Exemple d'utilisation :
     *   $ctx = new CombatContext();
     *   $ctx->alliedFactionCount = 3;
     *   $ctx->alliedOrigineCount = 2;
     *   $ctx->playerFactionBonus = 2;  // choix joueur avant combat
     *   $ctx->playerOrigineBonus = 1;
     *   $this->bonusResolver->applyAll($hero->getFaction(), $hero->getOrigine(), $ctx);
     *   // $ctx->attackMultiplier, $ctx->speedMultiplier... sont maintenant prêts
     */
    public function applyAll(Faction $faction, Origine $origine, CombatContext $context): void
    {
        $this->applyFactionPassive($faction, $context);
        $this->applyOriginePassive($origine, $context);
    }

    /**
     * Déplace le trait dino_tier vers le dernier héros de l'équipe.
     * À appeler après avoir construit tous les Combatants héros.
     *
     * @param \App\Battle\Combatant[] $combatants
     */
    public function redistributeDinoTrait(array $combatants): void
    {
        if (empty($combatants)) return;

        $maxTier = 0;
        foreach ($combatants as $c) {
            $tier = (int) ($c->passiveTraits['dino_tier'] ?? 0);
            if ($tier > $maxTier) $maxTier = $tier;
            unset($c->passiveTraits['dino_tier']);
        }

        if ($maxTier > 0) {
            $combatants[count($combatants) - 1]->passiveTraits['dino_tier'] = $maxTier;
        }
    }
}

