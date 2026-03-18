<?php

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /** @return Recipe[] */
    public function findByCategory(string $category, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.ingredients', 'i')->addSelect('i')
            ->leftJoin('i.item',       'ii')->addSelect('ii')
            ->leftJoin('i.scroll',     'scrl')->addSelect('scrl')
            ->leftJoin('i.extension',  'ie')->addSelect('ie')
            ->leftJoin('r.resultExtension', 're')->addSelect('re')
            ->leftJoin('r.resultScroll',    'rs')->addSelect('rs')
            ->leftJoin('r.resultHero',      'rh')->addSelect('rh')
            ->where('r.category = :cat')
            ->setParameter('cat', $category)
            ->orderBy('r.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('r.active = true');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Recipe[] */
    public function findAllWithIngredients(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.ingredients', 'i')->addSelect('i')
            ->leftJoin('i.item',       'ii')->addSelect('ii')
            ->leftJoin('i.scroll',     'scrl')->addSelect('scrl')
            ->leftJoin('i.extension',  'ie')->addSelect('ie')
            ->leftJoin('r.resultExtension', 're')->addSelect('re')
            ->leftJoin('r.resultScroll',    'rs')->addSelect('rs')
            ->leftJoin('r.resultHero',      'rh')->addSelect('rh')
            ->orderBy('r.category', 'ASC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
