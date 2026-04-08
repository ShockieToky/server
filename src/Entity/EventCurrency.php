<?php

namespace App\Entity;

use App\Repository\EventCurrencyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Monnaie spécifique à un événement (ex: "Médailles du Festival", "Jetons de Donjon").
 * Les joueurs en accumulent via les récompenses de donjons d'événement
 * et peuvent les dépenser dans la boutique d'événement.
 */
#[ORM\Entity(repositoryClass: EventCurrencyRepository::class)]
class EventCurrency
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GameEvent::class, inversedBy: 'currencies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GameEvent $gameEvent = null;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Emoji affiché à côté du montant dans l'interface (ex: '🪙', '💎', '⚔️'). */
    #[ORM\Column(length: 10, options: ['default' => '🪙'])]
    private string $icon = '🪙';

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getGameEvent(): ?GameEvent { return $this->gameEvent; }
    public function setGameEvent(?GameEvent $gameEvent): self { $this->gameEvent = $gameEvent; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon): self { $this->icon = $icon; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $s): self { $this->sortOrder = $s; return $this; }
}
