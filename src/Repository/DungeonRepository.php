<?php

namespace App\Repository;

use App\Entity\Dungeon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DungeonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dungeon::class);
    }

    /** @return Dungeon[] Donjons actifs triés par difficulté puis nom. */
    public function findActive(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.active = true')
            ->orderBy('d.difficulty', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Charge le donjon avec ses vagues et monstres. */
    public function findWithWaves(int $id): ?Dungeon
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.waves', 'w')->addSelect('w')
            ->leftJoin('w.waveMonsters', 'wm')->addSelect('wm')
            ->leftJoin('wm.monster', 'm')->addSelect('m')
            ->leftJoin('d.rewards', 'r')->addSelect('r')
            ->where('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
