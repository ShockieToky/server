<?php

namespace App\Repository;

use App\Entity\PromoCode;
use App\Entity\PromoCodeClaim;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoCodeClaim>
 */
class PromoCodeClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoCodeClaim::class);
    }

    public function hasUserClaimed(User $user, PromoCode $promoCode): bool
    {
        return $this->findOneBy(['user' => $user, 'promoCode' => $promoCode]) !== null;
    }

    public function countByPromoCode(PromoCode $promoCode): int
    {
        return $this->count(['promoCode' => $promoCode]);
    }
}
