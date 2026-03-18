<?php

namespace App\Repository;

use App\Entity\UserInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserInventory>
 */
class UserInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserInventory::class);
    }

    //    /**
    //     * @return UserInventory[] Returns an array of UserInventory objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UserInventory
    //    {\
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /** @return UserInventory[] */
    public function findByUser(\App\Entity\User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findByUserAndItem(\App\Entity\User $user, \App\Entity\Item $item): ?UserInventory
    {
        return $this->findOneBy(['user' => $user, 'item' => $item]);
    }

    public function findByUserAndScroll(\App\Entity\User $user, \App\Entity\Scroll $scroll): ?UserInventory
    {
        return $this->findOneBy(['user' => $user, 'scroll' => $scroll]);
    }

    /** Retourne l'entrée d'inventaire contenant les pierres magiques de l'utilisateur. */
    public function findMagicStones(\App\Entity\User $user): ?UserInventory
    {
        return $this->createQueryBuilder('ui')
            ->join('ui.item', 'i')
            ->andWhere('ui.user = :user')
            ->andWhere('i.effectType = :et')
            ->setParameter('user', $user)
            ->setParameter('et', 'magic_stone')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Retourne l'entrée d'inventaire contenant les pierres d'âme de l'utilisateur. */
    public function findSoulStones(\App\Entity\User $user): ?UserInventory
    {
        return $this->createQueryBuilder('ui')
            ->join('ui.item', 'i')
            ->andWhere('ui.user = :user')
            ->andWhere('i.effectType = :et')
            ->setParameter('user', $user)
            ->setParameter('et', 'soul_stone')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(UserInventory $entry, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entry);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(UserInventory $entry, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entry);
        if ($flush) $this->getEntityManager()->flush();
    }
}
