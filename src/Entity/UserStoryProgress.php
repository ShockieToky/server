<?php

namespace App\Entity;

use App\Repository\UserStoryProgressRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Progression d'un joueur dans le mode histoire.
 * Une ligne par (user, stage). completedAt = null si pas encore terminé.
 */
#[ORM\Entity(repositoryClass: UserStoryProgressRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_stage', columns: ['user_id', 'stage_id'])]
class UserStoryProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: StoryStage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StoryStage $stage = null;

    /** Null si l'étape n'est pas encore terminée. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** Récompenses déjà réclamées pour cette étape. */
    #[ORM\Column(options: ['default' => false])]
    private bool $rewardClaimed = false;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getStage(): ?StoryStage { return $this->stage; }
    public function setStage(?StoryStage $stage): self { $this->stage = $stage; return $this; }

    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $dt): self { $this->completedAt = $dt; return $this; }

    public function isRewardClaimed(): bool { return $this->rewardClaimed; }
    public function setRewardClaimed(bool $v): self { $this->rewardClaimed = $v; return $this; }

    public function isCompleted(): bool { return $this->completedAt !== null; }
}
