<?php

namespace App\Service;

use App\Entity\EquippedExtension;
use App\Entity\UserExtension;
use Doctrine\ORM\EntityManagerInterface;

class ExtensionEquipService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Équipe une UserExtension dans un slot de module.
     * La valeur est déjà fixée sur la UserExtension (tirée à l'acquisition).
     *
     * @throws \RuntimeException si slot verrouillé, déjà équipée ailleurs, ou limite de rareté atteinte
     */
    public function equip(EquippedExtension $slot, UserExtension $userExtension): void
    {
        $module    = $slot->getModule();
        $extension = $userExtension->getExtension();

        // Vérifier que le slot est déverrouillé
        if ($slot->getSlotIndex() > $module->getSlotCount()) {
            throw new \RuntimeException(
                sprintf('Le slot %d est verrouillé. Améliorez le module pour le déverrouiller.', $slot->getSlotIndex())
            );
        }

        // Vérifier les limites de rareté sur ce module
        $rarity = $extension->getRarity();
        $limit  = $module->getRarityLimit($rarity);

        if ($limit === 0) {
            $rarityLabel = match ($rarity) {
                'epique'     => 'épique',
                'legendaire' => 'légendaire',
                default      => $rarity,
            };
            throw new \RuntimeException(
                sprintf(
                    'Les extensions de rareté "%s" ne sont pas autorisées sur un module ★%s.',
                    $rarityLabel,
                    str_repeat('★', $module->getLevel())
                )
            );
        }

        $currentCount = 0;
        foreach ($module->getSlots() as $s) {
            if ($s->getId() !== $slot->getId()
                && $s->getUserExtension() !== null
                && $s->getUserExtension()->getExtension()->getRarity() === $rarity
            ) {
                $currentCount++;
            }
        }

        if ($currentCount >= $limit) {
            throw new \RuntimeException(
                sprintf('Limite de rareté "%s" atteinte pour ce module (max %d).', $rarity, $limit)
            );
        }

        // Vérifier qu'aucun autre slot du module n'a déjà cette stat (unicité par stat)
        $stat = $extension->getStat();
        foreach ($module->getSlots() as $s) {
            if ($s->getId() !== $slot->getId()
                && $s->getUserExtension() !== null
                && $s->getUserExtension()->getExtension()->getStat() === $stat
            ) {
                throw new \RuntimeException(
                    sprintf('La stat "%s" est déjà présente sur ce module. Chaque stat doit être unique par module.', $stat)
                );
            }
        }

        $slot->setUserExtension($userExtension);
        $this->em->flush();
    }

    /**
     * Retire l'extension d'un slot (slot redevient vide, UserExtension retourne dans la collection).
     */
    public function unequip(EquippedExtension $slot): void
    {
        $slot->setUserExtension(null);
        $this->em->flush();
    }
}
