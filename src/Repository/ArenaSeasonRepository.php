<?php

namespace App\Repository;

use App\Entity\ArenaSeason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArenaSeason>
 */
class ArenaSeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArenaSeason::class);
    }

    public function findActive(): ?ArenaSeason
    {
        return $this->findOneBy(['isActive' => true]);
    }
}
