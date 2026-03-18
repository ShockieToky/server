<?php

namespace App\Entity;

use App\Repository\MonsterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ennemi PVE utilisé dans les vagues du mode histoire et des donjons.
 */
#[ORM\Entity(repositoryClass: MonsterRepository::class)]
class Monster
{
    public const TYPES = ['attack', 'defense', 'support'];

    // ── Champs ────────────────────────────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Niveau du monstre (1-100), utilisé pour les calculs de scaling. */
    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $level = 1;

    /** attack | defense | support */
    #[ORM\Column(length: 10, options: ['default' => 'attack'])]
    private string $type = 'attack';

    #[ORM\Column(options: ['default' => 0])]
    private int $attack = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $defense = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $hp = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $speed = 0;

    /** Taux de coup critique en % (ex: 15 = 15%). */
    #[ORM\Column(options: ['default' => 15])]
    private int $critRate = 15;

    /** Multiplicateur de coup critique en % (ex: 150 = 150%). */
    #[ORM\Column(options: ['default' => 150])]
    private int $critDamage = 150;

    #[ORM\Column(options: ['default' => 0])]
    private int $accuracy = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $resistance = 0;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getLevel(): int { return $this->level; }
    public function setLevel(int $l): self { $this->level = max(1, $l); return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = $t; return $this; }

    public function getAttack(): int { return $this->attack; }
    public function setAttack(int $v): self { $this->attack = $v; return $this; }

    public function getDefense(): int { return $this->defense; }
    public function setDefense(int $v): self { $this->defense = $v; return $this; }

    public function getHp(): int { return $this->hp; }
    public function setHp(int $v): self { $this->hp = $v; return $this; }

    public function getSpeed(): int { return $this->speed; }
    public function setSpeed(int $v): self { $this->speed = $v; return $this; }

    public function getCritRate(): int { return $this->critRate; }
    public function setCritRate(int $v): self { $this->critRate = $v; return $this; }

    public function getCritDamage(): int { return $this->critDamage; }
    public function setCritDamage(int $v): self { $this->critDamage = $v; return $this; }

    public function getAccuracy(): int { return $this->accuracy; }
    public function setAccuracy(int $v): self { $this->accuracy = $v; return $this; }

    public function getResistance(): int { return $this->resistance; }
    public function setResistance(int $v): self { $this->resistance = $v; return $this; }
}
