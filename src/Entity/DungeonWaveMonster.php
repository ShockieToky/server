<?php

namespace App\Entity;

use App\Repository\DungeonWaveMonsterRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Association monstre ↔ vague de donjon avec quantité.
 */
#[ORM\Entity(repositoryClass: DungeonWaveMonsterRepository::class)]
class DungeonWaveMonster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DungeonWave::class, inversedBy: 'waveMonsters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DungeonWave $wave = null;

    #[ORM\ManyToOne(targetEntity: Monster::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Monster $monster = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $quantity = 1;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getWave(): ?DungeonWave { return $this->wave; }
    public function setWave(?DungeonWave $wave): self { $this->wave = $wave; return $this; }

    public function getMonster(): ?Monster { return $this->monster; }
    public function setMonster(?Monster $monster): self { $this->monster = $monster; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $q): self { $this->quantity = max(1, $q); return $this; }
}
