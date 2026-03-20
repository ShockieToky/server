<?php

namespace App\Entity;

use App\Repository\UserDeckRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserDeckRepository::class)]
class UserDeck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $name = '';

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
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getHero1(): ?UserHero { return $this->hero1; }
    public function setHero1(?UserHero $hero1): self { $this->hero1 = $hero1; return $this; }

    public function getHero2(): ?UserHero { return $this->hero2; }
    public function setHero2(?UserHero $hero2): self { $this->hero2 = $hero2; return $this; }

    public function getHero3(): ?UserHero { return $this->hero3; }
    public function setHero3(?UserHero $hero3): self { $this->hero3 = $hero3; return $this; }

    public function getHero4(): ?UserHero { return $this->hero4; }
    public function setHero4(?UserHero $hero4): self { $this->hero4 = $hero4; return $this; }

    public function getLeadFactionId(): ?int { return $this->leadFactionId; }
    public function setLeadFactionId(?int $leadFactionId): self { $this->leadFactionId = $leadFactionId; return $this; }

    public function getLeadOrigineId(): ?int { return $this->leadOrigineId; }
    public function setLeadOrigineId(?int $leadOrigineId): self { $this->leadOrigineId = $leadOrigineId; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
