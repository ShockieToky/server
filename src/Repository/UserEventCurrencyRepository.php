<?php

namespace App\Repository;

use App\Entity\EventCurrency;
use App\Entity\User;
use App\Entity\UserEventCurrency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserEventCurrency>
 */
class UserEventCurrencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEventCurrency::class);
    }

    public function findByUserAndCurrency(User $user, EventCurrency $currency): ?UserEventCurrency
    {
        return $this->findOneBy(['user' => $user, 'eventCurrency' => $currency]);
    }

    /**
     * Récupère ou crée l'entrée de solde pour un utilisateur et une monnaie.
     */
    public function getOrCreate(User $user, EventCurrency $currency): UserEventCurrency
    {
        $entry = $this->findByUserAndCurrency($user, $currency);
        if ($entry === null) {
            $entry = new UserEventCurrency();
            $entry->setUser($user)->setEventCurrency($currency);
            $this->getEntityManager()->persist($entry);
        }
        return $entry;
    }

    /**
     * Retourne un map [currencyId => amount] pour un joueur donné.
     * @return array<int, int>
     */
    public function amountMapForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('uec')
            ->select('IDENTITY(uec.eventCurrency) AS currency_id, uec.amount')
            ->where('uec.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['currency_id']] = (int) $row['amount'];
        }
        return $map;
    }
}
