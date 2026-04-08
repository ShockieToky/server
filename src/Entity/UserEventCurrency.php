<?php

namespace App\Entity;

use App\Repository\UserEventCurrencyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Solde d'une monnaie d'événement pour un joueur donné.
 */
#[ORM\Entity(repositoryClass: UserEventCurrencyRepository::class)]
#[ORM\UniqueConstraint(name: 'UQ_user_event_currency', columns: ['user_id', 'event_currency_id'])]
class UserEventCurrency
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: EventCurrency::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EventCurrency $eventCurrency = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $amount = 0;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getEventCurrency(): ?EventCurrency { return $this->eventCurrency; }
    public function setEventCurrency(?EventCurrency $ec): self { $this->eventCurrency = $ec; return $this; }

    public function getAmount(): int { return $this->amount; }
    public function setAmount(int $amount): self { $this->amount = max(0, $amount); return $this; }
    public function addAmount(int $qty): self { $this->amount = max(0, $this->amount + $qty); return $this; }
    public function removeAmount(int $qty): self { $this->amount = max(0, $this->amount - $qty); return $this; }
}
