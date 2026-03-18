<?php

namespace App\Repository;

use App\Entity\DungeonWaveMonster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DungeonWaveMonsterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DungeonWaveMonster::class);
    }
}
