<?php

namespace App\Repository;

use App\Entity\GameEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameEvent>
 */
class GameEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameEvent::class);
    }

    /**
     * Retourne l'événement actuellement en cours (le plus récent si plusieurs satisfont les critères),
     * ou null s'il n'y en a pas.
     */
    public function findCurrent(): ?GameEvent
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('e')
            ->leftJoin('e.dungeons', 'd')
            ->leftJoin('e.scrolls', 's')
            ->leftJoin('e.shopItems', 'si')
            ->addSelect('d', 's', 'si')
            ->where('e.isActive = :active')
            ->andWhere('e.startAt IS NULL OR e.startAt <= :now')
            ->andWhere('e.endAt IS NULL OR e.endAt >= :now')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
