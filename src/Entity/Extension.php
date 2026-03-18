<?php

namespace App\Entity;

use App\Repository\ExtensionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue des extensions disponibles.
 * Une entrée = une stat + une rareté + une plage de valeurs.
 * Il y a 8 stats × 4 raretés = 32 entrées possibles.
 */
#[ORM\Entity(repositoryClass: ExtensionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_extension_stat_rarity', columns: ['stat', 'rarity'])]
class Extension
{
    const STATS = ['HP%', 'DEF%', 'ATK%', 'TCC%', 'DC%', 'VIT+', 'PREC+', 'RES+'];

    const RARITIES = ['commun', 'peu_commun', 'epique', 'legendaire'];

    /**
     * Valeurs min/max de référence issues du tableau de conception.
     * Format: [stat][rarity] => [min, max]
     */
    const DEFAULT_RANGES = [
        'HP%'   => ['commun' => [10, 16], 'peu_commun' => [14, 22], 'epique' => [20, 25], 'legendaire' => [25, 30]],
        'DEF%'  => ['commun' => [10, 16], 'peu_commun' => [14, 22], 'epique' => [20, 25], 'legendaire' => [25, 30]],
        'ATK%'  => ['commun' => [10, 16], 'peu_commun' => [14, 22], 'epique' => [20, 25], 'legendaire' => [25, 30]],
        'TCC%'  => ['commun' => [8, 12],  'peu_commun' => [13, 18], 'epique' => [19, 24], 'legendaire' => [25, 33]],
        'DC%'   => ['commun' => [5, 15],  'peu_commun' => [15, 25], 'epique' => [25, 35], 'legendaire' => [35, 45]],
        'VIT+'  => ['commun' => [11, 16], 'peu_commun' => [15, 20], 'epique' => [18, 24], 'legendaire' => [22, 28]],
        'PREC+' => ['commun' => [5, 10],  'peu_commun' => [8, 14],  'epique' => [15, 25], 'legendaire' => [26, 33]],
        'RES+'  => ['commun' => [5, 10],  'peu_commun' => [8, 14],  'epique' => [15, 25], 'legendaire' => [26, 33]],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $stat = '';

    #[ORM\Column(length: 15)]
    private string $rarity = '';

    #[ORM\Column(type: 'smallint')]
    private int $minValue = 0;

    #[ORM\Column(type: 'smallint')]
    private int $maxValue = 0;

    public function getId(): ?int { return $this->id; }

    public function getStat(): string { return $this->stat; }
    public function setStat(string $stat): self { $this->stat = $stat; return $this; }

    public function getRarity(): string { return $this->rarity; }
    public function setRarity(string $rarity): self { $this->rarity = $rarity; return $this; }

    public function getMinValue(): int { return $this->minValue; }
    public function setMinValue(int $minValue): self { $this->minValue = $minValue; return $this; }

    public function getMaxValue(): int { return $this->maxValue; }
    public function setMaxValue(int $maxValue): self { $this->maxValue = max($this->minValue, $maxValue); return $this; }
}
