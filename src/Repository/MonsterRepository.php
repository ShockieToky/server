<?php

namespace App\Repository;

use App\Entity\Monster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MonsterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Monster::class);
    }

    /** @return Monster[] */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type], ['name' => 'ASC']);
    }

    public function save(Monster $monster, bool $flush = false): void
    {
        $this->getEntityManager()->persist($monster);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Monster $monster, bool $flush = false): void
    {
        $this->getEntityManager()->remove($monster);
        if ($flush) $this->getEntityManager()->flush();
    }
}
