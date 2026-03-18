<?php

namespace App\Entity;

use App\Repository\AttackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente un sort/attaque d'un héros ou d'un monstre PVE.
 *
 * Chaque héros/monstre possède généralement 3 attaques (slotIndex 1, 2, 3).
 * Exactement l'un des deux champs hero/monster doit être défini.
 */
#[ORM\Entity(repositoryClass: AttackRepository::class)]
class Attack
{
    public const SCALING_STATS  = ['atk', 'def', 'hp', 'spd', 'none'];
    public const TARGET_TYPES   = ['single_enemy', 'all_enemies', 'random_enemy', 'self', 'single_ally', 'all_allies'];

    // ── Champs ────────────────────────────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hero::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Hero $hero = null;

    #[ORM\ManyToOne(targetEntity: Monster::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Monster $monster = null;

    /** Position du sort (1 = basique, 2, 3 = actifs). */
    #[ORM\Column(type: 'smallint')]
    private int $slotIndex = 1;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Nombre de frappes consécutives. */
    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $hitCount = 1;

    /**
     * Statistique de référence pour le scaling des dégâts.
     * 'none' = sort utilitaire sans dégâts.
     */
    #[ORM\Column(length: 10, options: ['default' => 'atk'])]
    private string $scalingStat = 'atk';

    /**
     * Pourcentage de scaling (ex: 150 = 150% de la stat de référence).
     * Divisé par hitCount pour chaque frappe individuelle.
     */
    #[ORM\Column(type: 'smallint', options: ['default' => 100])]
    private int $scalingPct = 100;

    /** Qui la compétence cible par défaut. */
    #[ORM\Column(length: 20, options: ['default' => 'single_enemy'])]
    private string $targetType = 'single_enemy';

    /**
     * Cooldown en nombre de tours après utilisation (0 = pas de cooldown, sort de base).
     */
    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $cooldown = 0;

    /** @var Collection<int, AttackEffect> */
    #[ORM\OneToMany(mappedBy: 'attack', targetEntity: AttackEffect::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $attackEffects;

    public function __construct()
    {
        $this->attackEffects = new ArrayCollection();
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getHero(): ?Hero { return $this->hero; }
    public function setHero(?Hero $hero): self { $this->hero = $hero; return $this; }

    public function getMonster(): ?Monster { return $this->monster; }
    public function setMonster(?Monster $monster): self { $this->monster = $monster; return $this; }

    public function getSlotIndex(): int { return $this->slotIndex; }
    public function setSlotIndex(int $i): self { $this->slotIndex = max(1, $i); return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getHitCount(): int { return $this->hitCount; }
    public function setHitCount(int $n): self { $this->hitCount = max(1, $n); return $this; }

    public function getScalingStat(): string { return $this->scalingStat; }
    public function setScalingStat(string $s): self { $this->scalingStat = $s; return $this; }

    public function getScalingPct(): int { return $this->scalingPct; }
    public function setScalingPct(int $p): self { $this->scalingPct = max(0, $p); return $this; }

    public function getTargetType(): string { return $this->targetType; }
    public function setTargetType(string $t): self { $this->targetType = $t; return $this; }

    public function getCooldown(): int { return $this->cooldown; }
    public function setCooldown(int $c): self { $this->cooldown = max(0, $c); return $this; }

    /** @return Collection<int, AttackEffect> */
    public function getAttackEffects(): Collection { return $this->attackEffects; }

    public function addAttackEffect(AttackEffect $ae): self
    {
        if (!$this->attackEffects->contains($ae)) {
            $this->attackEffects->add($ae);
            $ae->setAttack($this);
        }
        return $this;
    }

    public function removeAttackEffect(AttackEffect $ae): self
    {
        $this->attackEffects->removeElement($ae);
        return $this;
    }
}
