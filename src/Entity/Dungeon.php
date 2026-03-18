<?php

namespace App\Entity;

use App\Repository\DungeonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Donjon PVE — comme le mode histoire mais avec l'IA avancée.
 * Peut être rejoué autant de fois que souhaité.
 */
#[ORM\Entity(repositoryClass: DungeonRepository::class)]
class Dungeon
{
    public const DIFFICULTIES = ['easy', 'normal', 'hard', 'legendary'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** easy | normal | hard | legendary */
    #[ORM\Column(length: 12, options: ['default' => 'normal'])]
    private string $difficulty = 'normal';

    /** Donjon visible et jouable. */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /** @var Collection<int, DungeonWave> */
    #[ORM\OneToMany(mappedBy: 'dungeon', targetEntity: DungeonWave::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['waveNumber' => 'ASC'])]
    private Collection $waves;

    /** @var Collection<int, DungeonReward> */
    #[ORM\OneToMany(mappedBy: 'dungeon', targetEntity: DungeonReward::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rewards;

    public function __construct()
    {
        $this->waves   = new ArrayCollection();
        $this->rewards = new ArrayCollection();
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getDifficulty(): string { return $this->difficulty; }
    public function setDifficulty(string $d): self { $this->difficulty = $d; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): self { $this->active = $v; return $this; }

    /** @return Collection<int, DungeonWave> */
    public function getWaves(): Collection { return $this->waves; }

    public function addWave(DungeonWave $wave): self
    {
        if (!$this->waves->contains($wave)) {
            $this->waves->add($wave);
            $wave->setDungeon($this);
        }
        return $this;
    }

    /** @return Collection<int, DungeonReward> */
    public function getRewards(): Collection { return $this->rewards; }

    public function addReward(DungeonReward $reward): self
    {
        if (!$this->rewards->contains($reward)) {
            $this->rewards->add($reward);
            $reward->setDungeon($this);
        }
        return $this;
    }
}
