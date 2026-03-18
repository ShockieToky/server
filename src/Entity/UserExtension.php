<?php

namespace App\Entity;

use App\Repository\UserExtensionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Extension possédée par un joueur.
 * La valeur (rolledValue) est tirée une seule fois à l'acquisition dans [min, max].
 * Une UserExtension ne peut être équipée que dans un seul slot à la fois
 * (contrainte UNIQUE sur equipped_extension.user_extension_id).
 */
#[ORM\Entity(repositoryClass: UserExtensionRepository::class)]
class UserExtension
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Extension $extension = null;

    #[ORM\Column(type: 'smallint')]
    private int $rolledValue = 0;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getExtension(): ?Extension { return $this->extension; }
    public function setExtension(Extension $extension): self { $this->extension = $extension; return $this; }

    public function getRolledValue(): int { return $this->rolledValue; }
    public function setRolledValue(int $value): self { $this->rolledValue = $value; return $this; }
}
