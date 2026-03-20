<?php

namespace App\Entity;

use App\Repository\ArenaDefenseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArenaDefenseRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_arena_defense_user_slot', columns: ['user_id', 'slot_index'])]
class ArenaDefense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Slot 1, 2 ou 3 */
    #[ORM\Column]
    private int $slotIndex = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserHero $hero1 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserHero $hero2 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserHero $hero3 = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserHero $hero4 = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadFactionId = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadOrigineId = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getSlotIndex(): int { return $this->slotIndex; }
    public function setSlotIndex(int $slotIndex): self { $this->slotIndex = $slotIndex; return $this; }

    public function getHero1(): ?UserHero { return $this->hero1; }
    public function setHero1(?UserHero $hero1): self { $this->hero1 = $hero1; return $this; }

    public function getHero2(): ?UserHero { return $this->hero2; }
    public function setHero2(?UserHero $hero2): self { $this->hero2 = $hero2; return $this; }

    public function getHero3(): ?UserHero { return $this->hero3; }
    public function setHero3(?UserHero $hero3): self { $this->hero3 = $hero3; return $this; }

    public function getHero4(): ?UserHero { return $this->hero4; }
    public function setHero4(?UserHero $hero4): self { $this->hero4 = $hero4; return $this; }

    public function getLeadFactionId(): ?int { return $this->leadFactionId; }
    public function setLeadFactionId(?int $id): self { $this->leadFactionId = $id; return $this; }

    public function getLeadOrigineId(): ?int { return $this->leadOrigineId; }
    public function setLeadOrigineId(?int $id): self { $this->leadOrigineId = $id; return $this; }

    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTime(); return $this; }

    /** @return UserHero[] liste des héros non-null */
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
