<?php

namespace App\Entity;

use App\Repository\StoryWaveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vague de monstres au sein d'une étape (waveNumber 1, 2, 3).
 */
#[ORM\Entity(repositoryClass: StoryWaveRepository::class)]
class StoryWave
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StoryStage::class, inversedBy: 'waves')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StoryStage $stage = null;

    /** Numéro de vague dans l'étape (1, 2, 3). */
    #[ORM\Column(type: 'smallint')]
    private int $waveNumber = 1;

    /** @var Collection<int, StoryWaveMonster> */
    #[ORM\OneToMany(mappedBy: 'wave', targetEntity: StoryWaveMonster::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $waveMonsters;

    public function __construct()
    {
        $this->waveMonsters = new ArrayCollection();
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getStage(): ?StoryStage { return $this->stage; }
    public function setStage(?StoryStage $stage): self { $this->stage = $stage; return $this; }

    public function getWaveNumber(): int { return $this->waveNumber; }
    public function setWaveNumber(int $n): self { $this->waveNumber = $n; return $this; }

    /** @return Collection<int, StoryWaveMonster> */
    public function getWaveMonsters(): Collection { return $this->waveMonsters; }

    public function addWaveMonster(StoryWaveMonster $wm): self
    {
        if (!$this->waveMonsters->contains($wm)) {
            $this->waveMonsters->add($wm);
            $wm->setWave($this);
        }
        return $this;
    }
}
