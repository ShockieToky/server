<?php

namespace App\Battle;

use App\Entity\Attack;

/**
 * État mutable d'une unité pendant un combat.
 *
 * Créé à partir d'un Hero ou d'un Monster avant le début du combat.
 * Les stats de base incluent déjà les bonus passifs (CombatContext appliqué).
 */
class Combatant
{
    public int $currentHp;

    /** @var ActiveEffect[] Buffs/debuffs actifs indexés par nom d'effet. */
    public array $activeEffects = [];

    /** @var array<int, int> attackId => tours restants de cooldown */
    public array $cooldowns = [];

    /**
     * Mode IA utilisé quand cette unité est contrôlée automatiquement.
     * 'story'    — IA simple mode histoire (slot 3 → 2 → 1)
     * 'advanced' — IA scoring contextuelle (arène, défenses joueurs…)
     */
    public string $aiMode = 'story';

    /**
     * @param string   $id          Identifiant unique en combat (ex: "hero_3", "enemy_0_1")
     * @param string   $side        'player' | 'enemy'
     * @param string   $name        Nom affiché
     * @param int      $maxHp
     * @param int      $baseAttack  Stat finale avant buffs/debuffs de combat
     * @param int      $baseDefense
     * @param int      $baseSpeed
     * @param int      $critRate    En % entier (ex: 15 = 15%)
     * @param int      $critDamage  En % entier (ex: 150 = 150%)
     * @param int      $accuracy    En % entier
     * @param int      $resistance  En % entier
     * @param Attack[] $attacks     Sorts de l'unité avec leurs effets chargés
     */
    public function __construct(
        public readonly string $id,
        public readonly string $side,
        public readonly string $name,
        public readonly int $maxHp,
        public readonly int $baseAttack,
        public readonly int $baseDefense,
        public readonly int $baseSpeed,
        public readonly int $critRate,
        public readonly int $critDamage,
        public readonly int $accuracy,
        public readonly int $resistance,
        public readonly array $attacks,
        /** Réduction flat % de tous les dégâts subis (passif mystique). */
        public float $damageReductionPct = 0.0,
        /** @var array<string, mixed> Traits passifs runtime (procs, comportements). */
        public array $passiveTraits = [],
    ) {
        $this->currentHp = $maxHp;
    }

    // ── État ──────────────────────────────────────────────────────────────────

    public function isAlive(): bool
    {
        return $this->currentHp > 0;
    }

    /** Peut agir ce tour (pas étourdi / endormi). */
    public function canAct(): bool
    {
        return !$this->hasEffect('etourdissement') && !$this->hasEffect('sommeil');
    }

    // ── Gestion des effets ────────────────────────────────────────────────────

    public function hasEffect(string $name): bool
    {
        foreach ($this->activeEffects as $e) {
            if ($e->name === $name) return true;
        }
        return false;
    }

    public function getEffect(string $name): ?ActiveEffect
    {
        foreach ($this->activeEffects as $e) {
            if ($e->name === $name) return $e;
        }
        return null;
    }

    private const MAX_ACTIVE_EFFECTS = 10;

    /**
     * Ajoute ou remplace un effet.
     * Règles :
     *   - 'protection' bloque tous les effets négatifs de durée (les effets instantanés
     *     comme Suppression contournent ce blocage car ils ne passent pas par applyEffect).
     *   - 'bloqueur' bloque tous les effets positifs.
     *   - Les effets cumulables (brulure, recuperation) s'accumulent sans limite de stack
     *     (uniquement plafonné par MAX_ACTIVE_EFFECTS).
     *   - Les autres effets renouvellent simplement la durée (remplacement).
     *   - Plafond de 10 effets sur la durée simultanés.
     *     Exception : si un effet non-cumulable remplace un effet existant du même nom,
     *     le slot est libéré avant vérification du plafond (remplacement libre).
     * Retourne true si l'effet a été appliqué.
     */
    private const STACKABLE_EFFECTS = ['brulure', 'recuperation'];

    public function applyEffect(ActiveEffect $effect): bool
    {
        if ($effect->polarity === 'negative' && $this->hasEffect('protection')) {
            return false;
        }
        if ($effect->polarity === 'positive' && $this->hasEffect('bloqueur')) {
            return false;
        }

        $stackable = in_array($effect->name, self::STACKABLE_EFFECTS, true);

        if ($stackable) {
            // Les effets cumulables s'empilent : on vérifie juste le plafond global.
            if (count($this->activeEffects) >= self::MAX_ACTIVE_EFFECTS) {
                return false;
            }
        } else {
            // Comportement existant : renouvellement (remplace l'existant).
            $isRenewal = $this->hasEffect($effect->name);
            if ($isRenewal) {
                $this->removeEffect($effect->name);
            }
            if (!$isRenewal && count($this->activeEffects) >= self::MAX_ACTIVE_EFFECTS) {
                return false;
            }
        }

        $this->activeEffects[] = $effect;
        return true;
    }

    public function removeEffect(string $name): void
    {
        $this->activeEffects = array_values(
            array_filter($this->activeEffects, fn($e) => $e->name !== $name)
        );
    }

    /** Supprime tous les effets d'une polarité ('positive' ou 'negative'). */
    public function removeEffectsByPolarity(string $polarity): void
    {
        $this->activeEffects = array_values(
            array_filter($this->activeEffects, fn($e) => $e->polarity !== $polarity)
        );
    }

    /** Supprime le premier effet négatif présent (cleanse partiel). */
    public function removeFirstNegativeEffect(): void
    {
        foreach ($this->activeEffects as $i => $e) {
            if ($e->polarity === 'negative') {
                array_splice($this->activeEffects, $i, 1);
                $this->activeEffects = array_values($this->activeEffects);
                return;
            }
        }
    }

    /** Prolonge tous les effets positifs actifs de $turns tours. */
    public function extendPositiveEffects(int $turns): void
    {
        foreach ($this->activeEffects as $e) {
            if ($e->polarity === 'positive') {
                $e->remainingTurns += $turns;
            }
        }
    }

    // ── Stats effectives (après buffs/debuffs actifs) ─────────────────────────

    public function effectiveAttack(): int
    {
        return $this->applyStatMod($this->baseAttack, 'aug_attaque', 'red_attaque');
    }

    public function effectiveDefense(): int
    {
        return $this->applyStatMod($this->baseDefense, 'aug_defense', 'red_defense');
    }

    public function effectiveSpeed(): int
    {
        return $this->applyStatMod($this->baseSpeed, 'aug_vitesse', 'red_vitesse');
    }

    private function applyStatMod(int $base, string $buffName, string $debuffName): int
    {
        $mult = 1.0;
        if ($e = $this->getEffect($buffName))  $mult += $e->value / 100.0;
        if ($e = $this->getEffect($debuffName)) $mult -= $e->value / 100.0;
        return max(1, (int) round($base * max(0.0, $mult)));
    }

    // ── Choix du sort ─────────────────────────────────────────────────────────

    /**
     * Choisit le meilleur sort disponible (IA auto).
     * Si silencié, ne peut utiliser que le slot 1.
     * Si provoqué, retourne le slot 1 (la cible est gérée par l'appelant).
     */
    public function pickAttack(): ?Attack
    {
        if ($this->hasEffect('provocation')) {
            $slot1 = array_values(array_filter($this->attacks, fn(Attack $a) => $a->getSlotIndex() === 1));
            return $slot1[0] ?? null;
        }

        $silenced  = $this->hasEffect('silence');
        $available = array_filter(
            $this->attacks,
            fn(Attack $a) => ($this->cooldowns[$a->getId() ?? 0] ?? 0) === 0
                && (!$silenced || $a->getSlotIndex() === 1)
        );

        if (empty($available)) {
            // Fallback : slot 1 toujours utilisable même si on n'en a pas
            $available = array_filter($this->attacks, fn($a) => $a->getSlotIndex() === 1);
        }
        if (empty($available)) return null;

        // Priorité au slot le plus élevé disponible
        usort($available, fn($a, $b) => $b->getSlotIndex() <=> $a->getSlotIndex());
        return array_values($available)[0];
    }

    /**
     * IA ennemie histoire : utilise toujours le slot le plus haut disponible.
     * Priorité : slot 3 → slot 2 → slot 1.
     * Un slot utilisé une fois mis en recharge est réutilisé dès qu'il revient.
     * Si silencié, seul le slot 1 est utilisable.
     */
    public function pickAttackEnemyAI(): ?Attack
    {
        $silenced  = $this->hasEffect('silence');

        // Slots disponibles (hors cooldown, hors silence si applicable)
        $available = array_filter(
            $this->attacks,
            fn(Attack $a) => ($this->cooldowns[$a->getId() ?? 0] ?? 0) === 0
                && (!$silenced || $a->getSlotIndex() === 1)
        );

        if (empty($available)) {
            // Fallback au slot 1 même s'il n'existe pas dans la liste
            $available = array_filter($this->attacks, fn($a) => $a->getSlotIndex() === 1);
        }
        if (empty($available)) return null;

        // Toujours le slot numéroté le plus haut (3 > 2 > 1)
        usort($available, fn($a, $b) => $b->getSlotIndex() <=> $a->getSlotIndex());
        return array_values($available)[0];
    }

    // ── Fin de tour ───────────────────────────────────────────────────────────

    /**
     * Décrémente les durées de tous les effets actifs.
     * Supprime ceux dont la durée est tombée à 0.
     * Retourne les noms d'effets expirés.
     *
     * @return string[]
     */
    public function tickEffects(): array
    {
        $expired = [];
        foreach ($this->activeEffects as $e) {
            // Les effets appliqués ce même tour sont protégés du premier tick.
            if ($e->fresh) {
                $e->fresh = false;
                continue;
            }
            $e->remainingTurns--;
            if ($e->remainingTurns <= 0) {
                $expired[] = $e->name;
            }
        }
        $this->activeEffects = array_values(
            array_filter($this->activeEffects, fn($e) => $e->remainingTurns > 0)
        );
        return $expired;
    }

    /** Décrémente les cooldowns de tous les sorts. */
    public function tickCooldowns(): void
    {
        foreach ($this->cooldowns as $id => &$cd) {
            if ($cd > 0) $cd--;
        }
    }

    /**
     * Applique des dégâts en tenant compte du bouclier actif.
     * Retourne le nombre de PV réellement perdus.
     */
    public function takeDamage(int $amount): int
    {
        if ($this->hasEffect('invincibilite')) return 0;

        // Réduction flat (passif mystique)
        if ($this->damageReductionPct > 0.0) {
            $amount = max(1, (int) floor($amount * (1.0 - $this->damageReductionPct / 100.0)));
        }

        // Absorption par le bouclier
        $shield = $this->getEffect('bouclier');
        if ($shield && $shield->shieldHp > 0) {
            $absorbed         = min($amount, (int) ceil($shield->shieldHp));
            $shield->shieldHp -= $absorbed;
            $amount           -= $absorbed;
            if ($shield->shieldHp <= 0) {
                $this->removeEffect('bouclier');
            }
        }

        $actual           = min($amount, $this->currentHp);
        $this->currentHp -= $actual;
        return $actual;
    }

    /** Soigne l'unité (plafond à maxHp). Retourne les PV récupérés. */
    public function heal(int $amount): int
    {
        $healed           = min($amount, $this->maxHp - $this->currentHp);
        $this->currentHp += $healed;
        return $healed;
    }
}
