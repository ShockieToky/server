<?php

namespace App\Repository;

use App\Entity\Hero;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hero>
 */
class HeroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hero::class);
    }

    //    /**
    //     * @return Hero[] Returns an array of Hero objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    public function save(Hero $hero, bool $flush = false): void
    {
        $this->getEntityManager()->persist($hero);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Hero $hero, bool $flush = false): void
    {
        $this->getEntityManager()->remove($hero);
        if ($flush) $this->getEntityManager()->flush();
    }
}
