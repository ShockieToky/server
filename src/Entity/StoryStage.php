<?php

namespace App\Entity;

use App\Repository\StoryStageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Étape du mode histoire (1 à 4 pour l'instant).
 * Chaque étape contient 3 vagues de monstres et donne des récompenses one-shot.
 */
#[ORM\Entity(repositoryClass: StoryStageRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_stage_number', columns: ['stage_number'])]
class StoryStage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Numéro d'ordre (1, 2, 3, 4…). Sert aussi de condition de débloquage. */
    #[ORM\Column(type: 'smallint')]
    private int $stageNumber = 1;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Étape visible et jouable. */
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /** @var Collection<int, StoryWave> */
    #[ORM\OneToMany(mappedBy: 'stage', targetEntity: StoryWave::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['waveNumber' => 'ASC'])]
    private Collection $waves;

    /** @var Collection<int, StoryReward> */
    #[ORM\OneToMany(mappedBy: 'stage', targetEntity: StoryReward::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rewards;

    public function __construct()
    {
        $this->waves   = new ArrayCollection();
        $this->rewards = new ArrayCollection();
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getStageNumber(): int { return $this->stageNumber; }
    public function setStageNumber(int $n): self { $this->stageNumber = $n; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): self { $this->active = $v; return $this; }

    /** @return Collection<int, StoryWave> */
    public function getWaves(): Collection { return $this->waves; }

    public function addWave(StoryWave $wave): self
    {
        if (!$this->waves->contains($wave)) {
            $this->waves->add($wave);
            $wave->setStage($this);
        }
        return $this;
    }

    /** @return Collection<int, StoryReward> */
    public function getRewards(): Collection { return $this->rewards; }

    public function addReward(StoryReward $reward): self
    {
        if (!$this->rewards->contains($reward)) {
            $this->rewards->add($reward);
            $reward->setStage($this);
        }
        return $this;
    }
}
