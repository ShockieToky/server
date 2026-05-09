<?php

namespace App\Entity;

use App\Repository\UserHeroRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserHeroRepository::class)]
class UserHero
{
    public const MAX_LEVEL = 35;
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

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $level = 1;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $xp = 0;

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

    public function getLevel(): int { return $this->level; }
    public function setLevel(int $level): self { $this->level = max(1, min(self::MAX_LEVEL, $level)); return $this; }

    public function getXp(): int { return $this->xp; }

    /**
     * XP requis pour passer du niveau $level au niveau $level+1.
     * Formule : 100 × level  → total pour atteindre le niveau 35 depuis 1 = 59 500 XP.
     */
    public static function xpToNextLevel(int $level): int
    {
        if ($level >= self::MAX_LEVEL) {
            return 0;
        }
        return 100 * $level;
    }

    /**
     * Ajoute de l'XP et monte automatiquement de niveau si le seuil est atteint.
     *
     * @return array{leveled_up: bool, level: int, xp: int, xp_to_next: int}
     */
    public function addXp(int $amount): array
    {
        if ($this->level >= self::MAX_LEVEL) {
            return ['leveled_up' => false, 'level' => $this->level, 'xp' => $this->xp, 'xp_to_next' => 0];
        }

        $this->xp += max(0, $amount);
        $leveledUp = false;

        while ($this->level < self::MAX_LEVEL && $this->xp >= self::xpToNextLevel($this->level)) {
            $this->xp -= self::xpToNextLevel($this->level);
            $this->level++;
            $leveledUp = true;
        }

        return [
            'leveled_up'  => $leveledUp,
            'level'       => $this->level,
            'xp'          => $this->xp,
            'xp_to_next'  => self::xpToNextLevel($this->level),
        ];
    }

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
