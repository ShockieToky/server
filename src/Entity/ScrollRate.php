<?php
namespace App\Entity;

use App\Repository\ScrollRateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScrollRateRepository::class)]
class ScrollRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Scroll::class, inversedBy: 'rates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Scroll $scroll;

    /** 1 to 5 */
    #[ORM\Column(type: 'smallint')]
    private int $rarity = 1;

    /** Percentage share, e.g. 3.5 = 3.5% */
    #[ORM\Column(type: 'float')]
    private float $rate = 0.0;

    public function getId(): ?int { return $this->id; }

    public function getScroll(): Scroll { return $this->scroll; }
    public function setScroll(Scroll $scroll): self { $this->scroll = $scroll; return $this; }

    public function getRarity(): int { return $this->rarity; }
    public function setRarity(int $rarity): self { $this->rarity = max(1, min(5, $rarity)); return $this; }

    public function getRate(): float { return $this->rate; }
    public function setRate(float $rate): self { $this->rate = $rate; return $this; }
}
