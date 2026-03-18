<?php

namespace App\Entity;

use App\Repository\EquippedExtensionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Slot d'extension dans un module.
 * Toujours pré-créé (slot vide) pour faciliter l'affichage UI.
 * Un slot est occupé en renseignant `userExtension` (pointe vers une extension possédée).
 * Contrainte UNIQUE sur user_extension_id : une extension ne peut être dans qu'un seul slot.
 */
#[ORM\Entity(repositoryClass: EquippedExtensionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_equipped_slot', columns: ['module_id', 'slot_index'])]
#[ORM\UniqueConstraint(name: 'uniq_equipped_user_ext', columns: ['user_extension_id'])]
class EquippedExtension
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HeroModule $module = null;

    #[ORM\Column(name: 'slot_index', type: 'smallint')]
    private int $slotIndex = 1;

    /** Null = slot vide. L'extension possédée avec sa valeur tirée. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserExtension $userExtension = null;

    public function getId(): ?int { return $this->id; }

    public function getModule(): ?HeroModule { return $this->module; }
    public function setModule(HeroModule $module): self { $this->module = $module; return $this; }

    public function getSlotIndex(): int { return $this->slotIndex; }
    public function setSlotIndex(int $slotIndex): self { $this->slotIndex = $slotIndex; return $this; }

    public function getUserExtension(): ?UserExtension { return $this->userExtension; }
    public function setUserExtension(?UserExtension $userExtension): self { $this->userExtension = $userExtension; return $this; }

    public function isEmpty(): bool { return $this->userExtension === null; }
}
