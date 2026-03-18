<?php

namespace App\Entity;

use App\Repository\DungeonWaveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vague de monstres au sein d'un donjon (waveNumber 1, 2, 3…).
 */
#[ORM\Entity(repositoryClass: DungeonWaveRepository::class)]
class DungeonWave
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Dungeon::class, inversedBy: 'waves')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dungeon $dungeon = null;

    #[ORM\Column(type: 'smallint')]
    private int $waveNumber = 1;

    /** @var Collection<int, DungeonWaveMonster> */
    #[ORM\OneToMany(mappedBy: 'wave', targetEntity: DungeonWaveMonster::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $waveMonsters;

    public function __construct()
    {
        $this->waveMonsters = new ArrayCollection();
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getDungeon(): ?Dungeon { return $this->dungeon; }
    public function setDungeon(?Dungeon $dungeon): self { $this->dungeon = $dungeon; return $this; }

    public function getWaveNumber(): int { return $this->waveNumber; }
    public function setWaveNumber(int $n): self { $this->waveNumber = $n; return $this; }

    /** @return Collection<int, DungeonWaveMonster> */
    public function getWaveMonsters(): Collection { return $this->waveMonsters; }

    public function addWaveMonster(DungeonWaveMonster $wm): self
    {
        if (!$this->waveMonsters->contains($wm)) {
            $this->waveMonsters->add($wm);
            $wm->setWave($this);
        }
        return $this;
    }
}
