<?php

namespace App\Entity;

use App\Repository\StoryRewardRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Récompense one-shot associée à une étape du mode histoire.
 * rewardType : 'history_token' | 'item' | 'scroll'
 */
#[ORM\Entity(repositoryClass: StoryRewardRepository::class)]
class StoryReward
{
    public const REWARD_TYPES = ['history_token', 'item', 'scroll'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StoryStage::class, inversedBy: 'rewards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StoryStage $stage = null;

    /** 'history_token' | 'item' | 'scroll' */
    #[ORM\Column(length: 15)]
    private string $rewardType = 'history_token';

    #[ORM\Column(options: ['default' => 1])]
    private int $quantity = 1;

    /** Rempli si rewardType = 'item'. */
    #[ORM\ManyToOne(targetEntity: Item::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Item $item = null;

    /** Rempli si rewardType = 'scroll'. */
    #[ORM\ManyToOne(targetEntity: Scroll::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Scroll $scroll = null;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getStage(): ?StoryStage { return $this->stage; }
    public function setStage(?StoryStage $stage): self { $this->stage = $stage; return $this; }

    public function getRewardType(): string { return $this->rewardType; }
    public function setRewardType(string $t): self { $this->rewardType = $t; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $q): self { $this->quantity = max(1, $q); return $this; }

    public function getItem(): ?Item { return $this->item; }
    public function setItem(?Item $item): self { $this->item = $item; return $this; }

    public function getScroll(): ?Scroll { return $this->scroll; }
    public function setScroll(?Scroll $scroll): self { $this->scroll = $scroll; return $this; }
}
