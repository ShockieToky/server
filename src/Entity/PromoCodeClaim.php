<?php

namespace App\Entity;

use App\Repository\PromoCodeClaimRepository;
use Doctrine\ORM\Mapping as ORM;

/** Trace qu'un utilisateur a utilisé un code promo (unique par user+code). */
#[ORM\Entity(repositoryClass: PromoCodeClaimRepository::class)]
#[ORM\Table(name: 'promo_code_claim')]
#[ORM\UniqueConstraint(name: 'uq_claim_user_code', columns: ['promo_code_id', 'user_id'])]
class PromoCodeClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PromoCode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PromoCode $promoCode;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $claimedAt;

    public function __construct()
    {
        $this->claimedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getPromoCode(): PromoCode { return $this->promoCode; }
    public function setPromoCode(PromoCode $promoCode): static { $this->promoCode = $promoCode; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getClaimedAt(): \DateTimeInterface { return $this->claimedAt; }
}
