<?php

namespace App\Repository;

use App\Entity\Extension;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExtensionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Extension::class);
    }

    /** @return Extension[] */
    public function findByStat(string $stat): array
    {
        return $this->findBy(['stat' => $stat]);
    }

    /** @return Extension[] */
    public function findByRarity(string $rarity): array
    {
        return $this->findBy(['rarity' => $rarity]);
    }

    public function save(Extension $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Extension $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) $this->getEntityManager()->flush();
    }
}
