<?php

namespace App\Repository;

use App\Entity\Extension;
use App\Entity\User;
use App\Entity\UserExtension;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserExtensionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserExtension::class);
    }

    /** @return UserExtension[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ue')
            ->join('ue.extension', 'e')
            ->addSelect('e')
            ->where('ue.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.rarity', 'DESC')
            ->addOrderBy('e.stat', 'ASC')
            ->addOrderBy('ue.rolledValue', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les UserExtensions NON équipées d'un joueur.
     *
     * @param Extension|null $extension  Si renseignée, filtre sur cette extension du catalogue.
     * @param string|null    $rarity     Si renseignée, filtre sur la rareté (commun|peu_commun|epique|legendaire).
     * @param int            $limit      Nombre maximum de résultats.
     *
     * @return UserExtension[]
     */
    public function findUnequipped(
        User $user,
        ?Extension $extension = null,
        ?string $rarity = null,
        int $limit = PHP_INT_MAX
    ): array {
        $qb = $this->createQueryBuilder('ue')
            ->join('ue.extension', 'e')
            ->addSelect('e')
            ->leftJoin('App\Entity\EquippedExtension', 'eq', 'WITH', 'eq.userExtension = ue')
            ->where('ue.user = :user')
            ->andWhere('eq.id IS NULL')
            ->setParameter('user', $user);

        if ($extension !== null) {
            $qb->andWhere('ue.extension = :ext')->setParameter('ext', $extension);
        }

        if ($rarity !== null) {
            $qb->andWhere('e.rarity = :rarity')->setParameter('rarity', $rarity);
        }

        if ($limit < PHP_INT_MAX) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
