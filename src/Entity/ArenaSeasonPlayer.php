<?php

namespace App\Entity;

use App\Repository\ArenaSeasonPlayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArenaSeasonPlayerRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_arena_season_player', columns: ['user_id', 'season_id'])]
class ArenaSeasonPlayer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ArenaSeason $season = null;

    #[ORM\Column]
    private int $wins = 0;

    #[ORM\Column]
    private int $losses = 0;

    /** Nombre d'attaques lancées aujourd'hui */
    #[ORM\Column]
    private int $attacksUsedToday = 0;

    /** Date (sans heure) de la dernière attaque */
    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $lastAttackDate = null;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getSeason(): ?ArenaSeason { return $this->season; }
    public function setSeason(ArenaSeason $season): self { $this->season = $season; return $this; }

    public function getWins(): int { return $this->wins; }
    public function addWin(): self { $this->wins++; return $this; }

    public function getLosses(): int { return $this->losses; }
    public function addLoss(): self { $this->losses++; return $this; }

    public function getAttacksUsedToday(): int { return $this->attacksUsedToday; }

    public function getLastAttackDate(): ?\DateTimeInterface { return $this->lastAttackDate; }

    /**
     * Réinitialise le compteur quotidien si c'est un nouveau jour,
     * puis incrémente le compteur. Retourne false si la limite est atteinte.
     */
    public function tryConsumeAttack(int $dailyLimit = 10): bool
    {
        $today = new \DateTime('today');
        if ($this->lastAttackDate === null || $this->lastAttackDate->format('Y-m-d') !== $today->format('Y-m-d')) {
            $this->attacksUsedToday = 0;
        }

        if ($this->attacksUsedToday >= $dailyLimit) {
            return false;
        }

        $this->attacksUsedToday++;
        $this->lastAttackDate = $today;
        return true;
    }

    public function getAttacksRemaining(int $dailyLimit = 10): int
    {
        $today = new \DateTime('today');
        if ($this->lastAttackDate === null || $this->lastAttackDate->format('Y-m-d') !== $today->format('Y-m-d')) {
            return $dailyLimit;
        }
        return max(0, $dailyLimit - $this->attacksUsedToday);
    }
}
