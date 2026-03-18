<?php

namespace App\Repository;

use App\Entity\Effect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EffectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Effect::class);
    }

    public function findByDurationType(string $durationType): array
    {
        return $this->findBy(['durationType' => $durationType]);
    }

    public function findByPolarity(string $polarity): array
    {
        return $this->findBy(['polarity' => $polarity]);
    }

    public function findByName(string $name): ?Effect
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function save(Effect $effect, bool $flush = false): void
    {
        $this->getEntityManager()->persist($effect);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Effect $effect, bool $flush = false): void
    {
        $this->getEntityManager()->remove($effect);
        if ($flush) $this->getEntityManager()->flush();
    }
}
