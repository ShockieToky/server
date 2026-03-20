<?php

namespace App\Repository;

use App\Entity\ArenaSeason;
use App\Entity\ArenaSeasonPlayer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArenaSeasonPlayer>
 */
class ArenaSeasonPlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArenaSeasonPlayer::class);
    }

    public function findByUserAndSeason(User $user, ArenaSeason $season): ?ArenaSeasonPlayer
    {
        return $this->findOneBy(['user' => $user, 'season' => $season]);
    }

    /**
     * Trouve ou crée l'entrée de saison pour un joueur.
     * Persiste automatiquement la nouvelle entrée si nécessaire.
     */
    public function findOrCreate(User $user, ArenaSeason $season, EntityManagerInterface $em): ArenaSeasonPlayer
    {
        $entry = $this->findByUserAndSeason($user, $season);
        if ($entry === null) {
            $entry = (new ArenaSeasonPlayer())
                ->setUser($user)
                ->setSeason($season);
            $em->persist($entry);
        }
        return $entry;
    }

    /**
     * Classement de la saison : wins DESC, losses ASC.
     * Retourne max $limit entrées.
     *
     * @return ArenaSeasonPlayer[]
     */
    public function findRanking(ArenaSeason $season, int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->where('p.season = :season')
            ->setParameter('season', $season)
            ->orderBy('p.wins',   'DESC')
            ->addOrderBy('p.losses', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
