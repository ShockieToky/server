<?php
namespace App\Entity;

use App\Repository\HeroRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeroRepository::class)]
class Hero
{
    public const TYPES = ['attack', 'defense', 'support'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Faction::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Faction $faction = null;

    #[ORM\ManyToOne(targetEntity: Origine::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Origine $origine = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** 1 to 5 */
    #[ORM\Column(type: 'smallint')]
    private int $rarity = 1;

    /** attack | defense | support */
    #[ORM\Column(length: 10)]
    private string $type = 'attack';

    #[ORM\Column]
    private int $attack = 0;

    #[ORM\Column]
    private int $defense = 0;

    #[ORM\Column]
    private int $hp = 0;

    #[ORM\Column]
    private int $speed = 0;

    /** Percentage, e.g. 15 = 15% */
    #[ORM\Column]
    private int $critRate = 0;

    /** Percentage, e.g. 150 = 150% */
    #[ORM\Column]
    private int $critDamage = 0;

    #[ORM\Column]
    private int $accuracy = 0;

    #[ORM\Column]
    private int $resistance = 0;

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getFaction(): ?Faction { return $this->faction; }
    public function setFaction(?Faction $faction): self { $this->faction = $faction; return $this; }

    public function getOrigine(): ?Origine { return $this->origine; }
    public function setOrigine(?Origine $origine): self { $this->origine = $origine; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getRarity(): int { return $this->rarity; }
    public function setRarity(int $rarity): self { $this->rarity = max(1, min(5, $rarity)); return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getAttack(): int { return $this->attack; }
    public function setAttack(int $attack): self { $this->attack = $attack; return $this; }

    public function getDefense(): int { return $this->defense; }
    public function setDefense(int $defense): self { $this->defense = $defense; return $this; }

    public function getHp(): int { return $this->hp; }
    public function setHp(int $hp): self { $this->hp = $hp; return $this; }

    public function getSpeed(): int { return $this->speed; }
    public function setSpeed(int $speed): self { $this->speed = $speed; return $this; }

    public function getCritRate(): int { return $this->critRate; }
    public function setCritRate(int $critRate): self { $this->critRate = $critRate; return $this; }

    public function getCritDamage(): int { return $this->critDamage; }
    public function setCritDamage(int $critDamage): self { $this->critDamage = $critDamage; return $this; }

    public function getAccuracy(): int { return $this->accuracy; }
    public function setAccuracy(int $accuracy): self { $this->accuracy = $accuracy; return $this; }

    public function getResistance(): int { return $this->resistance; }
    public function setResistance(int $resistance): self { $this->resistance = $resistance; return $this; }
}
