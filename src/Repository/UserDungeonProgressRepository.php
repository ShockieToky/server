<?php

namespace App\Repository;

use App\Entity\Dungeon;
use App\Entity\User;
use App\Entity\UserDungeonProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserDungeonProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDungeonProgress::class);
    }

    public function findOneByUserAndDungeon(User $user, Dungeon $dungeon): ?UserDungeonProgress
    {
        return $this->findOneBy(['user' => $user, 'dungeon' => $dungeon]);
    }

    /** @return UserDungeonProgress[] */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }
}
