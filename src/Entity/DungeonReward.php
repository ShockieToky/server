<?php

namespace App\Entity;

use App\Repository\DungeonRewardRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Récompense accordée à chaque complétion d'un donjon.
 * rewardType : 'history_token' | 'item' | 'scroll'
 */
#[ORM\Entity(repositoryClass: DungeonRewardRepository::class)]
class DungeonReward
{
    public const REWARD_TYPES = ['gold', 'item', 'scroll'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Dungeon::class, inversedBy: 'rewards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dungeon $dungeon = null;

    /** 'gold' | 'item' | 'scroll' */
    #[ORM\Column(length: 15)]
    private string $rewardType = 'gold';

    /** Quantité minimale droppée (incluse). */
    #[ORM\Column(options: ['default' => 1])]
    private int $quantityMin = 1;

    /** Quantité maximale droppée (incluse). Si égale à quantityMin → fixe. */
    #[ORM\Column(options: ['default' => 1])]
    private int $quantityMax = 1;

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

    public function getDungeon(): ?Dungeon { return $this->dungeon; }
    public function setDungeon(?Dungeon $dungeon): self { $this->dungeon = $dungeon; return $this; }

    public function getRewardType(): string { return $this->rewardType; }
    public function setRewardType(string $t): self { $this->rewardType = $t; return $this; }

    public function getQuantityMin(): int { return $this->quantityMin; }
    public function setQuantityMin(int $q): self { $this->quantityMin = max(1, $q); return $this; }

    public function getQuantityMax(): int { return $this->quantityMax; }
    public function setQuantityMax(int $q): self { $this->quantityMax = max(1, $q); return $this; }

    /** Tire un nombre aléatoire dans la plage [min, max]. */
    public function rollQuantity(): int
    {
        return $this->quantityMin >= $this->quantityMax
            ? $this->quantityMin
            : random_int($this->quantityMin, $this->quantityMax);
    }

    public function getItem(): ?Item { return $this->item; }
    public function setItem(?Item $item): self { $this->item = $item; return $this; }

    public function getScroll(): ?Scroll { return $this->scroll; }
    public function setScroll(?Scroll $scroll): self { $this->scroll = $scroll; return $this; }
}
