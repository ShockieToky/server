<?php

namespace App\Entity;

use App\Repository\UserInventoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente une entrée d'inventaire d'un utilisateur.
 * Exactement l'un des champs `item` ou `scroll` est non-null.
 */
#[ORM\Entity(repositoryClass: UserInventoryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_item',   columns: ['user_id', 'item_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_scroll', columns: ['user_id', 'scroll_id'])]
class UserInventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Renseigné si le type est 'item'. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Item $item = null;

    /** Renseigné si le type est 'scroll'. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Scroll $scroll = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getItem(): ?Item { return $this->item; }
    public function setItem(?Item $item): self { $this->item = $item; return $this; }

    public function getScroll(): ?Scroll { return $this->scroll; }
    public function setScroll(?Scroll $scroll): self { $this->scroll = $scroll; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = max(0, $quantity); return $this; }

    /** Retourne 'item' ou 'scroll' selon ce qui est renseigné. */
    public function getType(): string
    {
        return $this->item !== null ? 'item' : 'scroll';
    }
}
