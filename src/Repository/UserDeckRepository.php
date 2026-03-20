<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserDeck;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDeck>
 */
class UserDeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDeck::class);
    }

    /** @return UserDeck[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.hero1', 'h1')->addSelect('h1')
            ->leftJoin('h1.hero', 'hh1')->addSelect('hh1')
            ->leftJoin('hh1.faction', 'f1')->addSelect('f1')
            ->leftJoin('hh1.origine', 'o1')->addSelect('o1')
            ->leftJoin('d.hero2', 'h2')->addSelect('h2')
            ->leftJoin('h2.hero', 'hh2')->addSelect('hh2')
            ->leftJoin('hh2.faction', 'f2')->addSelect('f2')
            ->leftJoin('hh2.origine', 'o2')->addSelect('o2')
            ->leftJoin('d.hero3', 'h3')->addSelect('h3')
            ->leftJoin('h3.hero', 'hh3')->addSelect('hh3')
            ->leftJoin('hh3.faction', 'f3')->addSelect('f3')
            ->leftJoin('hh3.origine', 'o3')->addSelect('o3')
            ->leftJoin('d.hero4', 'h4')->addSelect('h4')
            ->leftJoin('h4.hero', 'hh4')->addSelect('hh4')
            ->leftJoin('hh4.faction', 'f4')->addSelect('f4')
            ->leftJoin('hh4.origine', 'o4')->addSelect('o4')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
