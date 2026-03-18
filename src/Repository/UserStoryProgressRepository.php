<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserStoryProgress;
use App\Entity\StoryStage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserStoryProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserStoryProgress::class);
    }

    public function findOneByUserAndStage(User $user, StoryStage $stage): ?UserStoryProgress
    {
        return $this->findOneBy(['user' => $user, 'stage' => $stage]);
    }

    /** @return UserStoryProgress[] */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /** Set des stageId déjà complétés par l'user. */
    public function findCompletedStageIds(User $user): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.stage) AS stageId')
            ->where('p.user = :user')
            ->andWhere('p.completedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(fn($r) => (int) $r['stageId'], $rows);
    }
}
