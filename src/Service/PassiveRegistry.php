<?php

namespace App\Service;

use App\Passive\PassiveInterface;

/**
 * Registre central de tous les passifs du jeu.
 *
 * Symfony injecte automatiquement tous les services taggés
 * "app.passive" grâce à l'autowiring par interface.
 * Pour enregistrer un nouveau passif, il suffit de créer une classe
 * implémentant PassiveInterface — aucune configuration supplémentaire.
 */
class PassiveRegistry
{
    /** @var array<string, PassiveInterface> slug → passif */
    private array $passives = [];

    /**
     * @param iterable<PassiveInterface> $passives
     */
    public function __construct(iterable $passives)
    {
        foreach ($passives as $passive) {
            $this->passives[$passive->getSlug()] = $passive;
        }
    }

    public function get(string $slug): ?PassiveInterface
    {
        return $this->passives[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return isset($this->passives[$slug]);
    }

    /** @return array<string, PassiveInterface> */
    public function all(): array
    {
        return $this->passives;
    }
}
