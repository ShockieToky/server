<?php

namespace App\Repository;

use App\Entity\TrainingSlot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TrainingSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingSlot::class);
    }

    /** @return TrainingSlot[] Active (unclaimed) slots for the given user, ordered by slotIndex. */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('ts')
            ->where('ts.user = :user')
            ->andWhere('ts.claimedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('ts.slotIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('ts')
            ->select('COUNT(ts.id)')
            ->where('ts.user = :user')
            ->andWhere('ts.claimedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Returns the active slot occupying the given slotIndex for this user, or null. */
    public function findActiveByUserAndSlot(User $user, int $slotIndex): ?TrainingSlot
    {
        return $this->createQueryBuilder('ts')
            ->where('ts.user = :user')
            ->andWhere('ts.slotIndex = :si')
            ->andWhere('ts.claimedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('si', $slotIndex)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Returns the active training for a given userHero, or null if free. */
    public function findActiveByUserAndHero(User $user, int $userHeroId): ?TrainingSlot
    {
        return $this->createQueryBuilder('ts')
            ->where('ts.user = :user')
            ->andWhere('ts.userHero = :uhId')
            ->andWhere('ts.claimedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('uhId', $userHeroId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
