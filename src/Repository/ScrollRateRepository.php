<?php

namespace App\Repository;

use App\Entity\ScrollRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScrollRate>
 */
class ScrollRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScrollRate::class);
    }

    //    /**
    //     * @return ScrollRate[] Returns an array of ScrollRate objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    public function save(ScrollRate $rate, bool $flush = false): void
    {
        $this->getEntityManager()->persist($rate);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(ScrollRate $rate, bool $flush = false): void
    {
        $this->getEntityManager()->remove($rate);
        if ($flush) $this->getEntityManager()->flush();
    }
}
