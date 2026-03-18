<?php

namespace App\Entity;

use App\Repository\UserHeroRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserHeroRepository::class)]
class UserHero
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
    private ?Hero $hero = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $acquiredAt;

    #[ORM\OneToMany(mappedBy: 'userHero', targetEntity: HeroModule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['slotIndex' => 'ASC'])]
    private Collection $modules;

    public function __construct()
    {
        $this->acquiredAt = new \DateTime();
        $this->modules    = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getHero(): ?Hero { return $this->hero; }
    public function setHero(Hero $hero): self { $this->hero = $hero; return $this; }

    public function getAcquiredAt(): \DateTimeInterface { return $this->acquiredAt; }

    /** @return Collection<int, HeroModule> */
    public function getModules(): Collection { return $this->modules; }

    public function addModule(HeroModule $module): self
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
            $module->setUserHero($this);
        }
        return $this;
    }
}
