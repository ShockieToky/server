<?php

namespace App\Repository;

use App\Entity\ShopPurchase;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopPurchase>
 */
class ShopPurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopPurchase::class);
    }

    /** Nombre total d'achats pour un joueur sur un article donné. */
    public function countByUserAndItem(User $user, int $shopItemId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->andWhere('p.shopItemId = :itemId')
            ->setParameter('user', $user)
            ->setParameter('itemId', $shopItemId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nombre d'achats sur la période en cours (daily = aujourd'hui, weekly = cette semaine). */
    public function countByUserAndItemInPeriod(User $user, int $shopItemId, string $period): int
    {
        $from = new \DateTime();
        if ($period === 'daily') {
            $from->setTime(0, 0, 0);
        } elseif ($period === 'weekly') {
            $dayOfWeek = (int) $from->format('N'); // 1=Mon … 7=Sun
            $from->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
        }

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->andWhere('p.shopItemId = :itemId')
            ->andWhere('p.purchasedAt >= :from')
            ->setParameter('user', $user)
            ->setParameter('itemId', $shopItemId)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Compte les achats par shopItemId pour un joueur (map id → count). */
    public function countMapForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.shopItemId, COUNT(p.id) AS cnt')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->groupBy('p.shopItemId')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['shopItemId']] = (int) $row['cnt'];
        }
        return $map;
    }
}
