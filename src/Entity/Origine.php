<?php

namespace App\Entity;

use App\Repository\OrigineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrigineRepository::class)]
class Origine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Slug du passif associé à cette origine.
     * Doit correspondre à une entrée dans PassiveRegistry.
     * Ex: "heritage_nomade"
     */
    #[ORM\Column(length: 100)]
    private string $passiveName = '';

    /**
     * Description textuelle affichée au joueur.
     */
    #[ORM\Column(type: 'text')]
    private string $passiveDescription = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPassiveName(): string
    {
        return $this->passiveName;
    }

    public function setPassiveName(string $passiveName): self
    {
        $this->passiveName = $passiveName;
        return $this;
    }

    public function getPassiveDescription(): string
    {
        return $this->passiveDescription;
    }

    public function setPassiveDescription(string $passiveDescription): self
    {
        $this->passiveDescription = $passiveDescription;
        return $this;
    }
}


