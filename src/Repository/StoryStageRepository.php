<?php

namespace App\Repository;

use App\Entity\StoryStage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StoryStageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoryStage::class);
    }

    /** @return StoryStage[] Toutes les étapes actives, triées par numéro. */
    public function findActive(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.active = true')
            ->orderBy('s.stageNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Charge l'étape avec ses vagues et les monstres associés. */
    public function findWithWaves(int $id): ?StoryStage
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.waves', 'w')->addSelect('w')
            ->leftJoin('w.waveMonsters', 'wm')->addSelect('wm')
            ->leftJoin('wm.monster', 'm')->addSelect('m')
            ->leftJoin('s.rewards', 'r')->addSelect('r')
            ->leftJoin('r.item', 'ri')->addSelect('ri')
            ->leftJoin('r.scroll', 'rs')->addSelect('rs')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
