<?php

namespace App\Repository;

use App\Entity\ArenaAdminTeam;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArenaAdminTeam>
 */
class ArenaAdminTeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArenaAdminTeam::class);
    }

    /** @return ArenaAdminTeam[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->orderBy('t.slotIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
