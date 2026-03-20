<?php

namespace App\Entity;

use App\Repository\ArenaAdminTeamRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Équipe bot créée par l'admin.
 * Les joueurs peuvent l'attaquer pour consommer leurs 10 attaques journalières
 * sans affronter d'autres joueurs. Les victoires sur ces équipes ne comptent
 * ni en gains ni en pertes dans le classement.
 */
#[ORM\Entity(repositoryClass: ArenaAdminTeamRepository::class)]
class ArenaAdminTeam
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    /** Position d'affichage (1-5) */
    #[ORM\Column]
    private int $slotIndex = 1;

    /** Héros du catalogue (pas des UserHero — aucun niveau ni extension) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $hero1 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $hero2 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $hero3 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $hero4 = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadFactionId = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadOrigineId = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSlotIndex(): int { return $this->slotIndex; }
    public function setSlotIndex(int $slotIndex): self { $this->slotIndex = $slotIndex; return $this; }

    public function getHero1(): ?Hero { return $this->hero1; }
    public function setHero1(?Hero $hero1): self { $this->hero1 = $hero1; return $this; }

    public function getHero2(): ?Hero { return $this->hero2; }
    public function setHero2(?Hero $hero2): self { $this->hero2 = $hero2; return $this; }

    public function getHero3(): ?Hero { return $this->hero3; }
    public function setHero3(?Hero $hero3): self { $this->hero3 = $hero3; return $this; }

    public function getHero4(): ?Hero { return $this->hero4; }
    public function setHero4(?Hero $hero4): self { $this->hero4 = $hero4; return $this; }

    public function getLeadFactionId(): ?int { return $this->leadFactionId; }
    public function setLeadFactionId(?int $id): self { $this->leadFactionId = $id; return $this; }

    public function getLeadOrigineId(): ?int { return $this->leadOrigineId; }
    public function setLeadOrigineId(?int $id): self { $this->leadOrigineId = $id; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    /** @return Hero[] heroes non-null */
    public function getHeroes(): array
    {
        return array_values(array_filter([$this->hero1, $this->hero2, $this->hero3, $this->hero4]));
    }

    public function isEmpty(): bool
    {
        return $this->hero1 === null && $this->hero2 === null
            && $this->hero3 === null && $this->hero4 === null;
    }
}
