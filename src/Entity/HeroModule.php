<?php

namespace App\Entity;

use App\Repository\HeroModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Module équipé sur un héros d'un joueur.
 * Chaque UserHero possède 3 modules (slotIndex 1-3).
 * Le level (1-3) détermine le nombre de slots d'extension disponibles
 * et la limite de rareté d'extension équipable.
 */
#[ORM\Entity(repositoryClass: HeroModuleRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_hero_module_slot', columns: ['user_hero_id', 'slot_index'])]
class HeroModule
{
    /** Nombre de slots d'extension disponibles selon le level. */
    const SLOT_COUNTS = [1 => 2, 2 => 3, 3 => 4];

    /** Nombre maximum d'extensions par rareté selon le level du module. */
    const RARITY_LIMITS = [
        1 => ['commun' => 2, 'peu_commun' => 1, 'epique' => 0, 'legendaire' => 0],
        2 => ['commun' => 3, 'peu_commun' => 2, 'epique' => 0, 'legendaire' => 0],
        3 => ['commun' => 4, 'peu_commun' => 4, 'epique' => 2, 'legendaire' => 1],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'modules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserHero $userHero = null;

    #[ORM\Column(name: 'slot_index', type: 'smallint')]
    private int $slotIndex = 1;

    #[ORM\Column(type: 'smallint')]
    private int $level = 1;

    #[ORM\OneToMany(mappedBy: 'module', targetEntity: EquippedExtension::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['slotIndex' => 'ASC'])]
    private Collection $slots;

    public function __construct()
    {
        $this->slots = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUserHero(): ?UserHero { return $this->userHero; }
    public function setUserHero(UserHero $userHero): self { $this->userHero = $userHero; return $this; }

    public function getSlotIndex(): int { return $this->slotIndex; }
    public function setSlotIndex(int $slotIndex): self { $this->slotIndex = $slotIndex; return $this; }

    public function getLevel(): int { return $this->level; }
    public function setLevel(int $level): self { $this->level = max(1, min(3, $level)); return $this; }

    /** Nombre de slots déverrouillés sur ce module. */
    public function getSlotCount(): int
    {
        return self::SLOT_COUNTS[$this->level];
    }

    /** Limite d'extensions de la rareté donnée pour ce module. */
    public function getRarityLimit(string $rarity): int
    {
        return self::RARITY_LIMITS[$this->level][$rarity] ?? 0;
    }

    /** @return Collection<int, EquippedExtension> */
    public function getSlots(): Collection { return $this->slots; }

    public function addSlot(EquippedExtension $slot): self
    {
        if (!$this->slots->contains($slot)) {
            $this->slots->add($slot);
            $slot->setModule($this);
        }
        return $this;
    }
}
