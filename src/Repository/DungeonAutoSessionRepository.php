<?php

namespace App\Repository;

use App\Entity\DungeonAutoSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DungeonAutoSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DungeonAutoSession::class);
    }

    /**
     * Retourne la session active (non réclamée et non terminée) d'un utilisateur.
     */
    public function findActiveForUser(User $user): ?DungeonAutoSession
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.isClaimed = false')
            ->setParameter('user', $user)
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
