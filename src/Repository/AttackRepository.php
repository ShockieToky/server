<?php

namespace App\Repository;

use App\Entity\Attack;
use App\Entity\Hero;
use App\Entity\Monster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AttackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attack::class);
    }

    /** @return Attack[] */
    public function findByHero(Hero $hero): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.attackEffects', 'ae')->addSelect('ae')
            ->leftJoin('ae.effect', 'e')->addSelect('e')
            ->andWhere('a.hero = :hero')
            ->setParameter('hero', $hero)
            ->orderBy('a.slotIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Attack[] */
    public function findByMonster(Monster $monster): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.attackEffects', 'ae')->addSelect('ae')
            ->leftJoin('ae.effect', 'e')->addSelect('e')
            ->andWhere('a.monster = :monster')
            ->setParameter('monster', $monster)
            ->orderBy('a.slotIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Attack $attack, bool $flush = false): void
    {
        $this->getEntityManager()->persist($attack);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Attack $attack, bool $flush = false): void
    {
        $this->getEntityManager()->remove($attack);
        if ($flush) $this->getEntityManager()->flush();
    }
}
