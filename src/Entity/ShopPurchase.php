<?php

namespace App\Entity;

use App\Repository\ShopPurchaseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace chaque achat d'un joueur dans la boutique.
 * Utilisé pour appliquer les limites par compte et par période.
 */
#[ORM\Entity(repositoryClass: ShopPurchaseRepository::class)]
#[ORM\Table(name: 'shop_purchase')]
class ShopPurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private int $shopItemId = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $purchasedAt;

    public function __construct()
    {
        $this->purchasedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getShopItemId(): int { return $this->shopItemId; }
    public function setShopItemId(int $v): self { $this->shopItemId = $v; return $this; }

    public function getPurchasedAt(): \DateTimeInterface { return $this->purchasedAt; }
    public function setPurchasedAt(\DateTimeInterface $v): self { $this->purchasedAt = $v; return $this; }
}
