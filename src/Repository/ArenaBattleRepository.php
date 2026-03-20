<?php

namespace App\Repository;

use App\Entity\ArenaBattle;
use App\Entity\ArenaSeason;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArenaBattle>
 */
class ArenaBattleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArenaBattle::class);
    }

    /**
     * Historique des combats d'un attaquant dans une saison.
     *
     * @return ArenaBattle[]
     */
    public function findByAttackerAndSeason(User $attacker, ArenaSeason $season, int $limit = 50): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.defender', 'def')->addSelect('def')
            ->where('b.attacker = :attacker')
            ->andWhere('b.season = :season')
            ->setParameter('attacker', $attacker)
            ->setParameter('season',   $season)
            ->orderBy('b.foughtAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique des défenses subies par un joueur dans une saison.
     *
     * @return ArenaBattle[]
     */
    public function findByDefenderAndSeason(User $defender, ArenaSeason $season, int $limit = 50): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.attacker', 'atk')->addSelect('atk')
            ->where('b.defender = :defender')
            ->andWhere('b.season = :season')
            ->setParameter('defender', $defender)
            ->setParameter('season',   $season)
            ->orderBy('b.foughtAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
