<?php

namespace App\Repository;

use App\Entity\StoryWaveMonster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StoryWaveMonsterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoryWaveMonster::class);
    }
}
