<?php

namespace App\Entity;

use App\Repository\UserDungeonProgressRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Suivi des runs de donjon par utilisateur.
 * Contrairement au mode histoire, les donjons sont rejouables :
 * on stocke le nombre de runs complétés plutôt qu'un simple "done".
 */
#[ORM\Entity(repositoryClass: UserDungeonProgressRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_dungeon', columns: ['user_id', 'dungeon_id'])]
class UserDungeonProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Dungeon::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dungeon $dungeon = null;

    /** Nombre total de runs réussis. */
    #[ORM\Column(options: ['default' => 0])]
    private int $runCount = 0;

    /** Date du dernier run réussi. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastCompletedAt = null;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getDungeon(): ?Dungeon { return $this->dungeon; }
    public function setDungeon(?Dungeon $dungeon): self { $this->dungeon = $dungeon; return $this; }

    public function getRunCount(): int { return $this->runCount; }
    public function incrementRunCount(): self { $this->runCount++; return $this; }

    public function getLastCompletedAt(): ?\DateTimeImmutable { return $this->lastCompletedAt; }
    public function setLastCompletedAt(\DateTimeImmutable $dt): self { $this->lastCompletedAt = $dt; return $this; }

    public function hasCompleted(): bool { return $this->runCount > 0; }
}
