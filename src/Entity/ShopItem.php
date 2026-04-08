<?php

namespace App\Entity;

use App\Repository\ShopItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Article vendable dans la boutique.
 *
 * Chaque ligne représente une offre : ce que le joueur paye (cost)
 * et ce qu'il reçoit (reward), avec les limites d'achat.
 */
#[ORM\Entity(repositoryClass: ShopItemRepository::class)]
#[ORM\Table(name: 'shop_item')]
class ShopItem
{
    public const REWARD_TYPES = ['gold', 'history_token', 'item', 'scroll', 'hero', 'event_currency'];
    public const COST_TYPES   = ['gold', 'history_token', 'item', 'scroll', 'event_currency'];
    public const PERIODS      = ['daily', 'weekly'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Onglet d'affichage dans la boutique (ex: "Centre de recherche"). */
    #[ORM\Column(length: 100, options: ['default' => 'Général'])]
    private string $category = 'Général';

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    // ── Ce que le joueur reçoit ───────────────────────────────────────────────

    /** gold | history_token | item | scroll | hero */
    #[ORM\Column(length: 20, options: ['default' => 'gold'])]
    private string $rewardType = 'gold';

    #[ORM\Column(options: ['default' => 1])]
    private int $rewardQuantity = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Item $rewardItem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Scroll $rewardScroll = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $rewardHero = null;

    // ── Ce que le joueur paye ─────────────────────────────────────────────────

    /** gold | history_token | item | scroll */
    #[ORM\Column(length: 20, options: ['default' => 'gold'])]
    private string $costType = 'gold';

    #[ORM\Column(options: ['default' => 1])]
    private int $costQuantity = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Item $costItem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Scroll $costScroll = null;

    /** Rempli si costType = 'event_currency' ou rewardType = 'event_currency'. */
    #[ORM\ManyToOne(targetEntity: EventCurrency::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EventCurrency $costEventCurrency = null;

    #[ORM\ManyToOne(targetEntity: EventCurrency::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EventCurrency $rewardEventCurrency = null;

    // ── Limites d'achat ───────────────────────────────────────────────────────

    /** Nombre maximum d'achats total par compte. Null = illimité. */
    #[ORM\Column(nullable: true)]
    private ?int $limitPerAccount = null;

    /** Période de remise à zéro : null | 'daily' | 'weekly'. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $limitPeriod = null;

    /** Nombre maximum d'achats par période. Null = illimité sur la période. */
    #[ORM\Column(nullable: true)]
    private ?int $limitPerPeriod = null;

    // ── Affichage ─────────────────────────────────────────────────────────────

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $v): self { $this->category = $v; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description = $v; return $this; }

    public function getRewardType(): string { return $this->rewardType; }
    public function setRewardType(string $v): self { $this->rewardType = $v; return $this; }

    public function getRewardQuantity(): int { return $this->rewardQuantity; }
    public function setRewardQuantity(int $v): self { $this->rewardQuantity = max(1, $v); return $this; }

    public function getRewardItem(): ?Item { return $this->rewardItem; }
    public function setRewardItem(?Item $v): self { $this->rewardItem = $v; return $this; }

    public function getRewardScroll(): ?Scroll { return $this->rewardScroll; }
    public function setRewardScroll(?Scroll $v): self { $this->rewardScroll = $v; return $this; }

    public function getRewardHero(): ?Hero { return $this->rewardHero; }
    public function setRewardHero(?Hero $v): self { $this->rewardHero = $v; return $this; }

    public function getCostType(): string { return $this->costType; }
    public function setCostType(string $v): self { $this->costType = $v; return $this; }

    public function getCostQuantity(): int { return $this->costQuantity; }
    public function setCostQuantity(int $v): self { $this->costQuantity = max(1, $v); return $this; }

    public function getCostItem(): ?Item { return $this->costItem; }
    public function setCostItem(?Item $v): self { $this->costItem = $v; return $this; }

    public function getCostScroll(): ?Scroll { return $this->costScroll; }
    public function setCostScroll(?Scroll $v): self { $this->costScroll = $v; return $this; }

    public function getCostEventCurrency(): ?EventCurrency { return $this->costEventCurrency; }
    public function setCostEventCurrency(?EventCurrency $v): self { $this->costEventCurrency = $v; return $this; }

    public function getRewardEventCurrency(): ?EventCurrency { return $this->rewardEventCurrency; }
    public function setRewardEventCurrency(?EventCurrency $v): self { $this->rewardEventCurrency = $v; return $this; }

    public function getLimitPerAccount(): ?int { return $this->limitPerAccount; }
    public function setLimitPerAccount(?int $v): self { $this->limitPerAccount = $v; return $this; }

    public function getLimitPeriod(): ?string { return $this->limitPeriod; }
    public function setLimitPeriod(?string $v): self { $this->limitPeriod = $v; return $this; }

    public function getLimitPerPeriod(): ?int { return $this->limitPerPeriod; }
    public function setLimitPerPeriod(?int $v): self { $this->limitPerPeriod = $v; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): self { $this->sortOrder = $v; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): self { $this->active = $v; return $this; }
}
