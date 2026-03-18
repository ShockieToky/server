<?php

namespace App\Service;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModuleUpgradeService
{
    const MAGIC_STONE_NAME = 'Pierre magique';
    const COST = [1 => 50, 2 => 150];

    public function __construct(
        private readonly UserInventoryRepository $inventoryRepository,
        private readonly ItemRepository          $itemRepository,
        private readonly EntityManagerInterface  $em,
    ) {
    }

    /**
     * Améliore un module d'un niveau en consommant une Pierre magique.
     * Ajoute automatiquement le nouveau slot d'extension déverrouillé.
     *
     * @throws \RuntimeException
     */
    public function upgrade(HeroModule $module, User $user): void
    {
        if ($module->getLevel() >= 3) {
            throw new \RuntimeException('Le module est déjà au niveau maximum (★★★).');
        }

        $currentLevel = $module->getLevel();
        $cost = self::COST[$currentLevel];

        // Chercher "Pierre magique" dans le catalogue d'items
        $item = $this->itemRepository->findOneBy(['name' => self::MAGIC_STONE_NAME]);
        if (!$item) {
            throw new \RuntimeException(
                sprintf('L\'item "%s" n\'existe pas dans le catalogue.', self::MAGIC_STONE_NAME)
            );
        }

        // Vérifier l'inventaire du joueur
        $entry = $this->inventoryRepository->findByUserAndItem($user, $item);
        if (!$entry || $entry->getQuantity() < $cost) {
            $has = $entry?->getQuantity() ?? 0;
            throw new \RuntimeException(
                sprintf('Pierres magiques insuffisantes (besoin : %d, possédées : %d).', $cost, $has)
            );
        }

        // Consommer les pierres magiques (atomique avec l'upgrade)
        $newQty = $entry->getQuantity() - $cost;
        if ($newQty <= 0) {
            $this->em->remove($entry);
        } else {
            $entry->setQuantity($newQty);
            $this->em->persist($entry);
        }

        // Monter le level
        $newLevel = $module->getLevel() + 1;
        $module->setLevel($newLevel);

        // Ajouter le nouveau slot déverrouillé
        $newSlotIndex = HeroModule::SLOT_COUNTS[$newLevel];
        $newSlot = new EquippedExtension();
        $newSlot->setSlotIndex($newSlotIndex);
        $module->addSlot($newSlot);

        $this->em->persist($module);
        $this->em->flush();
    }
}
