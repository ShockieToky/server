<?php

namespace App\Entity;

use App\Repository\AttackEffectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien entre une attaque et un effet qu'elle peut appliquer.
 *
 * Exemple : "Brûlure (3%), 30% de chance, pendant 1 tour, sur l'ennemi ciblé"
 */
#[ORM\Entity(repositoryClass: AttackEffectRepository::class)]
class AttackEffect
{
    /**
     * Sur qui l'effet est appliqué (peut différer de la cible de l'attaque).
     * Exemple : un sort qui attaque un ennemi mais se buffe soi-même.
     */
    public const EFFECT_TARGETS = ['target', 'self', 'all_enemies', 'all_allies', 'random_enemy', 'random_ally'];

    // ── Champs ────────────────────────────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Attack::class, inversedBy: 'attackEffects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Attack $attack = null;

    #[ORM\ManyToOne(targetEntity: Effect::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Effect $effect = null;

    /** Probabilité d'application de l'effet (0–100). */
    #[ORM\Column(type: 'smallint', options: ['default' => 100])]
    private int $chance = 100;

    /**
     * Durée en tours pour les effets de durée.
     * Null pour les effets instantanés.
     */
    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $duration = null;

    /**
     * Surcharge de la valeur par défaut de l'effet (null = utiliser Effect.defaultValue).
     * Exemple : brûlure à 5% au lieu du 3% par défaut.
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $value = null;

    /** Sur qui appliquer l'effet. */
    #[ORM\Column(length: 20, options: ['default' => 'target'])]
    private string $effectTarget = 'target';

    /**
     * Si true, l'effet est appliqué une fois par hit (utile pour les attaques multi-hit).
     * Si false, l'effet est résolu une seule fois après tous les hits.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $perHit = false;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getAttack(): ?Attack { return $this->attack; }
    public function setAttack(Attack $a): self { $this->attack = $a; return $this; }

    public function getEffect(): ?Effect { return $this->effect; }
    public function setEffect(Effect $e): self { $this->effect = $e; return $this; }

    public function getChance(): int { return $this->chance; }
    public function setChance(int $c): self { $this->chance = max(0, min(100, $c)); return $this; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $d): self { $this->duration = $d !== null ? max(1, $d) : null; return $this; }

    public function getValue(): ?float { return $this->value; }
    public function setValue(?float $v): self { $this->value = $v; return $this; }

    /** Retourne la valeur effective (surcharge sinon valeur par défaut de l'effet). */
    public function getEffectiveValue(): ?float
    {
        return $this->value ?? $this->effect?->getDefaultValue();
    }

    public function getEffectTarget(): string { return $this->effectTarget; }
    public function setEffectTarget(string $t): self { $this->effectTarget = $t; return $this; }

    public function isPerHit(): bool { return $this->perHit; }
    public function setPerHit(bool $v): self { $this->perHit = $v; return $this; }
}
