<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->remove($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Ajoute des gold tokens à un utilisateur (montant peut être négatif pour retirer).
     */
    public function addGoldToken(User $user, int $amount, bool $flush = false): void
    {
        $user->setGoldToken(max(0, $user->getGoldToken() + $amount));
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Ajoute des history tokens à un utilisateur (montant peut être négatif pour retirer).
     */
    public function addHistoryToken(User $user, int $amount, bool $flush = false): void
    {
        $user->setHistoryToken(max(0, $user->getHistoryToken() + $amount));
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByPseudo(string $pseudo): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.pseudo = :pseudo')
            ->setParameter('pseudo', $pseudo)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche un utilisateur actif par pseudo ou email.
     */
    public function findActiveByIdentifier(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->andWhere('u.pseudo = :identifier OR u.email = :identifier')
            ->setParameter('active', true)
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne tous les utilisateurs actifs.
     *
     * @return User[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Classement par nombre de gold tokens.
     *
     * @return User[]
     */
    public function findTopByGoldToken(int $limit = 50): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.goldToken', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Classement par nombre de history tokens (par ex. tirages historiques).
     *
     * @return User[]
     */
    public function findTopByHistoryToken(int $limit = 50): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.historyToken', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs créés après une certaine date (nouveaux joueurs).
     *
     * @return User[]
     */
    public function findCreatedAfter(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs ayant un streak minimum (utile pour récompenses de fidélité).
     *
     * @return User[]
     */
    public function findByMinLoginStreak(int $minStreak): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.loginStreak >= :streak')
            ->setParameter('streak', $minStreak)
            ->getQuery()
            ->getResult();
    }
}
