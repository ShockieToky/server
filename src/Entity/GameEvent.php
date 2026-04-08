<?php

namespace App\Entity;

use App\Repository\GameEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Événement temporaire du jeu.
 * Regroupe des donjons, des parchemins et des articles de boutique exclusifs.
 * Un seul event peut être actif à la fois (règle métier).
 */
#[ORM\Entity(repositoryClass: GameEventRepository::class)]
class GameEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Toggle manuel : désactivé = event invisible quelle que soit la date. */
    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /** Si null, l'event commence dès qu'il est activé. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startAt = null;

    /** Si null, l'event n'a pas de date de fin. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Dungeon> */
    #[ORM\ManyToMany(targetEntity: Dungeon::class)]
    #[ORM\JoinTable(name: 'game_event_dungeon')]
    private Collection $dungeons;

    /** @var Collection<int, Scroll> */
    #[ORM\ManyToMany(targetEntity: Scroll::class)]
    #[ORM\JoinTable(name: 'game_event_scroll')]
    private Collection $scrolls;

    /** @var Collection<int, ShopItem> */
    #[ORM\ManyToMany(targetEntity: ShopItem::class)]
    #[ORM\JoinTable(name: 'game_event_shop_item')]
    private Collection $shopItems;

    /** @var Collection<int, EventCurrency> */
    #[ORM\OneToMany(mappedBy: 'gameEvent', targetEntity: EventCurrency::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $currencies;

    public function __construct()
    {
        $this->dungeons   = new ArrayCollection();
        $this->scrolls    = new ArrayCollection();
        $this->shopItems  = new ArrayCollection();
        $this->currencies = new ArrayCollection();
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getId(): ?int                       { return $this->id; }
    public function getName(): string                   { return $this->name; }
    public function setName(string $name): self         { $this->name = $name; return $this; }
    public function getDescription(): ?string           { return $this->description; }
    public function setDescription(?string $d): self    { $this->description = $d; return $this; }
    public function isActive(): bool                    { return $this->isActive; }
    public function setIsActive(bool $v): self          { $this->isActive = $v; return $this; }
    public function getStartAt(): ?\DateTimeImmutable   { return $this->startAt; }
    public function setStartAt(?\DateTimeImmutable $v): self { $this->startAt = $v; return $this; }
    public function getEndAt(): ?\DateTimeImmutable     { return $this->endAt; }
    public function setEndAt(?\DateTimeImmutable $v): self   { $this->endAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable  { return $this->createdAt; }

    /** Vrai si l'event est actif et dans la plage de dates (si définie). */
    public function isLive(): bool
    {
        if (!$this->isActive) return false;
        $now = new \DateTimeImmutable();
        if ($this->startAt !== null && $now < $this->startAt) return false;
        if ($this->endAt   !== null && $now > $this->endAt)   return false;
        return true;
    }

    /** @return Collection<int, Dungeon> */
    public function getDungeons(): Collection { return $this->dungeons; }

    /** @return Collection<int, Scroll> */
    public function getScrolls(): Collection { return $this->scrolls; }

    /** @return Collection<int, ShopItem> */
    public function getShopItems(): Collection { return $this->shopItems; }

    /** @return Collection<int, EventCurrency> */
    public function getCurrencies(): Collection { return $this->currencies; }

    public function addCurrency(EventCurrency $currency): self
    {
        if (!$this->currencies->contains($currency)) {
            $this->currencies->add($currency);
            $currency->setGameEvent($this);
        }
        return $this;
    }

    public function removeCurrency(EventCurrency $currency): self
    {
        $this->currencies->removeElement($currency);
        return $this;
    }
}
