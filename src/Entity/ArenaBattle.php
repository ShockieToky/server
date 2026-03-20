<?php

namespace App\Entity;

use App\Repository\ArenaBattleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArenaBattleRepository::class)]
class ArenaBattle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ArenaSeason $season = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $attacker = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $defender = null;

    /** Référence à la défense utilisée (peut être nulle si supprimée) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ArenaDefense $arenaDefense = null;

    /** Snapshot JSON de la défense au moment du combat */
    #[ORM\Column(type: 'json')]
    private array $defenseSnapshot = [];

    /** true = victoire pour l'attaquant */
    #[ORM\Column]
    private bool $victory = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $foughtAt;

    public function __construct()
    {
        $this->foughtAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getSeason(): ?ArenaSeason { return $this->season; }
    public function setSeason(ArenaSeason $season): self { $this->season = $season; return $this; }

    public function getAttacker(): ?User { return $this->attacker; }
    public function setAttacker(User $attacker): self { $this->attacker = $attacker; return $this; }

    public function getDefender(): ?User { return $this->defender; }
    public function setDefender(User $defender): self { $this->defender = $defender; return $this; }

    public function getArenaDefense(): ?ArenaDefense { return $this->arenaDefense; }
    public function setArenaDefense(?ArenaDefense $arenaDefense): self { $this->arenaDefense = $arenaDefense; return $this; }

    public function getDefenseSnapshot(): array { return $this->defenseSnapshot; }
    public function setDefenseSnapshot(array $snapshot): self { $this->defenseSnapshot = $snapshot; return $this; }

    public function isVictory(): bool { return $this->victory; }
    public function setVictory(bool $victory): self { $this->victory = $victory; return $this; }

    public function getFoughtAt(): \DateTimeInterface { return $this->foughtAt; }
}
