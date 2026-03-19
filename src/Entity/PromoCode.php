<?php

namespace App\Entity;

use App\Repository\PromoCodeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Code promotionnel claimable une seule fois par utilisateur.
 *
 * rewards est un tableau JSON de récompenses :
 *   [{"type":"gold_token","quantity":100}, {"type":"item","itemId":3,"quantity":2}, ...]
 * Types supportés : gold_token | history_token | item | scroll
 */
#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\Table(name: 'promo_code')]
class PromoCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Le code lui-même (stocké en majuscules, unique). */
    #[ORM\Column(length: 50, unique: true)]
    private string $code = '';

    /** Tableau JSON de récompenses. */
    #[ORM\Column(type: 'json')]
    private array $rewards = [];

    /** Date d'expiration (null = pas de limite). */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    /** Nombre max de claims toutes utilisateurs confondus (null = illimité). */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = strtoupper(trim($code)); return $this; }

    public function getRewards(): array { return $this->rewards; }
    public function setRewards(array $rewards): static { $this->rewards = $rewards; return $this; }

    public function getExpiresAt(): ?\DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeInterface $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function getMaxUses(): ?int { return $this->maxUses; }
    public function setMaxUses(?int $maxUses): static { $this->maxUses = $maxUses; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
