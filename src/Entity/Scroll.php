<?php
namespace App\Entity;

use App\Repository\ScrollRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScrollRepository::class)]
class Scroll
{
    public const TYPES = ['scroll', 'choice'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * 'scroll' = 1 hero tiré aléatoirement
     * 'choice' = 5 héros proposés, joueur en choisit 1
     */
    #[ORM\Column(length: 10)]
    private string $type = 'scroll';

    #[ORM\OneToMany(
        mappedBy: 'scroll',
        targetEntity: ScrollRate::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $rates;

    public function __construct()
    {
        $this->rates = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getRates(): Collection { return $this->rates; }

    public function addRate(ScrollRate $rate): self
    {
        if (!$this->rates->contains($rate)) {
            $this->rates->add($rate);
            $rate->setScroll($this);
        }
        return $this;
    }

    public function removeRate(ScrollRate $rate): self
    {
        $this->rates->removeElement($rate);
        return $this;
    }
}
