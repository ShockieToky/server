<?php

namespace App\Repository;

use App\Entity\AttackEffect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AttackEffectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttackEffect::class);
    }

    public function save(AttackEffect $ae, bool $flush = false): void
    {
        $this->getEntityManager()->persist($ae);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(AttackEffect $ae, bool $flush = false): void
    {
        $this->getEntityManager()->remove($ae);
        if ($flush) $this->getEntityManager()->flush();
    }
}
