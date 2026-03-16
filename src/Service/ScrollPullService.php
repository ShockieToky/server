<?php
namespace App\Service;

use App\Entity\Hero;
use App\Entity\Scroll;
use App\Entity\ScrollRate;
use App\Repository\HeroRepository;

class ScrollPullService
{
    public function __construct(private readonly HeroRepository $heroRepository) {}

    /**
     * Execute a pull on the given scroll.
     *
     * Returns:
     *   - type 'scroll'  → ['type' => 'scroll',  'hero'   => Hero]
     *   - type 'choice'  → ['type' => 'choice',  'heroes' => Hero[5]]
     *
     * @throws \RuntimeException if no heroes are available for the drawn rarity
     */
    public function pull(Scroll $scroll): array
    {
        if ($scroll->getType() === 'choice') {
            $heroes = [];
            $usedIds = [];
            $attempts = 0;
            while (count($heroes) < 5 && $attempts < 50) {
                $attempts++;
                $candidate = $this->drawOne($scroll);
                if (!in_array($candidate->getId(), $usedIds, true)) {
                    $heroes[]  = $candidate;
                    $usedIds[] = $candidate->getId();
                }
            }
            if (count($heroes) < 5) {
                throw new \RuntimeException('Not enough distinct heroes available for a choice scroll');
            }
            return ['type' => 'choice', 'heroes' => $heroes];
        }

        return ['type' => 'scroll', 'hero' => $this->drawOne($scroll)];
    }

    private function drawOne(Scroll $scroll): Hero
    {
        $rarity = $this->pickRarity($scroll);
        $pool   = $this->heroRepository->findBy(['rarity' => $rarity]);

        if (empty($pool)) {
            // Fallback: any hero in the database
            $pool = $this->heroRepository->findAll();
            if (empty($pool)) {
                throw new \RuntimeException('No heroes available in the database');
            }
        }

        return $pool[array_rand($pool)];
    }

    /**
     * Weighted random selection of a rarity based on scroll rates.
     * Rates are treated as percentage shares (they don't need to sum to 100 exactly).
     */
    private function pickRarity(Scroll $scroll): int
    {
        $rates = $scroll->getRates()->toArray();

        if (empty($rates)) {
            return 1;
        }

        $total = array_sum(array_map(fn(ScrollRate $r) => $r->getRate(), $rates));
        $rand  = (float) mt_rand(0, PHP_INT_MAX) / PHP_INT_MAX * $total;

        $cumulative = 0.0;
        foreach ($rates as $rate) {
            $cumulative += $rate->getRate();
            if ($rand <= $cumulative) {
                return $rate->getRarity();
            }
        }

        // Safety fallback: return last defined rarity
        return end($rates)->getRarity();
    }
}
