<?php

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une recette de crafting.
 * category  = onglet UI (extension|scroll|hero|coin)
 * resultType = ce que le joueur obtient
 */
#[ORM\Entity(repositoryClass: RecipeRepository::class)]
class Recipe
{
    public const CATEGORIES   = ['extension', 'scroll', 'hero', 'coin'];
    public const RESULT_TYPES = ['extension', 'scroll', 'hero', 'coin'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name = '';

    #[ORM\Column(length: 20)]
    private string $category = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(length: 20)]
    private string $resultType = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Extension $resultExtension = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Scroll $resultScroll = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $resultHero = null;

    /** Rareté de l'extension résultat (commun|peu_commun|epique|legendaire) — utilisé quand resultType = 'extension'. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $resultExtensionRarity = null;

    /** Quantité produite (parchemins, pièces d'or). */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $resultQuantity = 1;

    #[ORM\OneToMany(
        mappedBy: 'recipe',
        targetEntity: RecipeIngredient::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $ingredients;

    public function __construct()
    {
        $this->ingredients = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): self { $this->category = $category; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function getResultType(): string { return $this->resultType; }
    public function setResultType(string $resultType): self { $this->resultType = $resultType; return $this; }

    public function getResultExtension(): ?Extension { return $this->resultExtension; }
    public function setResultExtension(?Extension $e): self { $this->resultExtension = $e; return $this; }

    public function getResultExtensionRarity(): ?string { return $this->resultExtensionRarity; }
    public function setResultExtensionRarity(?string $rarity): self { $this->resultExtensionRarity = $rarity; return $this; }

    public function getResultScroll(): ?Scroll { return $this->resultScroll; }
    public function setResultScroll(?Scroll $s): self { $this->resultScroll = $s; return $this; }

    public function getResultHero(): ?Hero { return $this->resultHero; }
    public function setResultHero(?Hero $h): self { $this->resultHero = $h; return $this; }

    public function getResultQuantity(): int { return $this->resultQuantity; }
    public function setResultQuantity(int $qty): self { $this->resultQuantity = max(1, $qty); return $this; }

    /** @return Collection<int, RecipeIngredient> */
    public function getIngredients(): Collection { return $this->ingredients; }

    public function addIngredient(RecipeIngredient $ingredient): self
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
            $ingredient->setRecipe($this);
        }
        return $this;
    }

    public function removeIngredient(RecipeIngredient $ingredient): self
    {
        $this->ingredients->removeElement($ingredient);
        return $this;
    }
}
