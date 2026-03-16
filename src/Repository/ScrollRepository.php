<?php

namespace App\Repository;

use App\Entity\Scroll;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scroll>
 */
class ScrollRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scroll::class);
    }

    //    /**
    //     * @return Scroll[] Returns an array of Scroll objects
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

    public function save(Scroll $scroll, bool $flush = false): void
    {
        $this->getEntityManager()->persist($scroll);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Scroll $scroll, bool $flush = false): void
    {
        $this->getEntityManager()->remove($scroll);
        if ($flush) $this->getEntityManager()->flush();
    }
}
