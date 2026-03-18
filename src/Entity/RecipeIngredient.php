<?php

namespace App\Entity;

use App\Repository\RecipeIngredientRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un ingrédient requis pour une recette.
 * Exactement un des champs (item, scroll, extension) ou extensionRarity est renseigné
 * selon ingredientType.
 */
#[ORM\Entity(repositoryClass: RecipeIngredientRepository::class)]
class RecipeIngredient
{
    /** coin = pièces d'or, extension_rarity = n'importe quelle extension de cette rareté */
    public const TYPES = ['coin', 'item', 'scroll', 'extension', 'extension_rarity'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ingredients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Recipe $recipe = null;

    #[ORM\Column(length: 20)]
    private string $ingredientType = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Item $item = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Scroll $scroll = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Extension $extension = null;

    /** Utilisé si ingredientType = 'extension_rarity' (commun|peu_commun|epique|legendaire). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $extensionRarity = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    public function getId(): ?int { return $this->id; }

    public function getRecipe(): ?Recipe { return $this->recipe; }
    public function setRecipe(?Recipe $recipe): self { $this->recipe = $recipe; return $this; }

    public function getIngredientType(): string { return $this->ingredientType; }
    public function setIngredientType(string $type): self { $this->ingredientType = $type; return $this; }

    public function getItem(): ?Item { return $this->item; }
    public function setItem(?Item $item): self { $this->item = $item; return $this; }

    public function getScroll(): ?Scroll { return $this->scroll; }
    public function setScroll(?Scroll $scroll): self { $this->scroll = $scroll; return $this; }

    public function getExtension(): ?Extension { return $this->extension; }
    public function setExtension(?Extension $ext): self { $this->extension = $ext; return $this; }

    public function getExtensionRarity(): ?string { return $this->extensionRarity; }
    public function setExtensionRarity(?string $rarity): self { $this->extensionRarity = $rarity; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $qty): self { $this->quantity = max(1, $qty); return $this; }
}
