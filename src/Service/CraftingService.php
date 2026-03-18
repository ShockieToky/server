<?php

namespace App\Service;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\User;
use App\Entity\UserExtension;
use App\Entity\UserHero;
use App\Entity\UserInventory;
use App\Repository\ExtensionRepository;
use App\Repository\UserExtensionRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CraftingService
{
    public function __construct(
        private readonly UserInventoryRepository $inventoryRepository,
        private readonly UserExtensionRepository $extensionRepository,
        private readonly ExtensionRepository     $extensionCatalogueRepository,
        private readonly EntityManagerInterface  $em,
    ) {}

    /**
     * Vérifie si l'utilisateur peut exécuter la recette.
     * Retourne null si tout est OK, sinon un message d'erreur lisible.
     */
    public function check(User $user, Recipe $recipe): ?string
    {
        foreach ($recipe->getIngredients() as $ing) {
            $error = $this->checkIngredient($user, $ing);
            if ($error !== null) {
                return $error;
            }
        }
        return null;
    }

    /**
     * Consomme les ingrédients et crédite le résultat.
     * Appeler check() avant pour valider la disponibilité.
     * Retourne un tableau décrivant le résultat (pour la réponse JSON).
     */
    public function execute(User $user, Recipe $recipe): array
    {
        foreach ($recipe->getIngredients() as $ing) {
            $this->consume($user, $ing);
        }
        return $this->giveResult($user, $recipe);
    }

    // ── Vérification ───────────────────────────────────────────────────────────

    private function checkIngredient(User $user, RecipeIngredient $ing): ?string
    {
        $qty  = $ing->getQuantity();
        $type = $ing->getIngredientType();

        switch ($type) {
            case 'coin':
                if ($user->getGoldToken() < $qty) {
                    return sprintf(
                        "Pièces insuffisantes (besoin : %d, disponible : %d).",
                        $qty, $user->getGoldToken()
                    );
                }
                break;

            case 'item':
                $entry = $this->inventoryRepository->findByUserAndItem($user, $ing->getItem());
                $have  = $entry?->getQuantity() ?? 0;
                if ($have < $qty) {
                    return sprintf(
                        "Item insuffisant : %s (besoin : %d, disponible : %d).",
                        $ing->getItem()->getName(), $qty, $have
                    );
                }
                break;

            case 'scroll':
                $entry = $this->inventoryRepository->findByUserAndScroll($user, $ing->getScroll());
                $have  = $entry?->getQuantity() ?? 0;
                if ($have < $qty) {
                    return sprintf(
                        "Parchemin insuffisant : %s (besoin : %d, disponible : %d).",
                        $ing->getScroll()->getName(), $qty, $have
                    );
                }
                break;

            case 'extension':
                $have = count($this->extensionRepository->findUnequipped($user, $ing->getExtension()));
                if ($have < $qty) {
                    return sprintf(
                        "Extension insuffisante (besoin : %d non-équipée(s), disponible : %d).",
                        $qty, $have
                    );
                }
                break;

            case 'extension_rarity':
                $have = count($this->extensionRepository->findUnequipped($user, null, $ing->getExtensionRarity()));
                if ($have < $qty) {
                    return sprintf(
                        "Extensions de rareté %s insuffisantes (besoin : %d, disponible : %d).",
                        $ing->getExtensionRarity(), $qty, $have
                    );
                }
                break;
        }

        return null;
    }

    // ── Consommation ───────────────────────────────────────────────────────────

    private function consume(User $user, RecipeIngredient $ing): void
    {
        $qty  = $ing->getQuantity();
        $type = $ing->getIngredientType();

        switch ($type) {
            case 'coin':
                $user->setGoldToken($user->getGoldToken() - $qty);
                break;

            case 'item':
                $entry = $this->inventoryRepository->findByUserAndItem($user, $ing->getItem());
                $entry->setQuantity($entry->getQuantity() - $qty);
                if ($entry->getQuantity() <= 0) {
                    $this->em->remove($entry);
                }
                break;

            case 'scroll':
                $entry = $this->inventoryRepository->findByUserAndScroll($user, $ing->getScroll());
                $entry->setQuantity($entry->getQuantity() - $qty);
                if ($entry->getQuantity() <= 0) {
                    $this->em->remove($entry);
                }
                break;

            case 'extension':
                foreach ($this->extensionRepository->findUnequipped($user, $ing->getExtension(), null, $qty) as $ue) {
                    $this->em->remove($ue);
                }
                break;

            case 'extension_rarity':
                foreach ($this->extensionRepository->findUnequipped($user, null, $ing->getExtensionRarity(), $qty) as $ue) {
                    $this->em->remove($ue);
                }
                break;
        }
    }

    // ── Résultat ───────────────────────────────────────────────────────────────

    private function giveResult(User $user, Recipe $recipe): array
    {
        switch ($recipe->getResultType()) {
            case 'extension':
                $rarity  = $recipe->getResultExtensionRarity();
                $choices = $this->extensionCatalogueRepository->findByRarity($rarity ?? '');
                if (empty($choices)) {
                    throw new \RuntimeException("Aucune extension de raret\u00e9 \"$rarity\" dans le catalogue.");
                }
                $ext = $choices[array_rand($choices)];
                $ue  = new UserExtension();
                $ue->setUser($user)
                   ->setExtension($ext)
                   ->setRolledValue(random_int($ext->getMinValue(), $ext->getMaxValue()));
                $this->em->persist($ue);
                $this->em->flush();
                return [
                    'type'      => 'extension',
                    'extension' => [
                        'id'          => $ue->getId(),
                        'rolledValue' => $ue->getRolledValue(),
                        'extension'   => [
                            'id'     => $ext->getId(),
                            'stat'   => $ext->getStat(),
                            'rarity' => $ext->getRarity(),
                        ],
                    ],
                ];

            case 'scroll':
                $scroll = $recipe->getResultScroll();
                $qty    = $recipe->getResultQuantity();
                $entry  = $this->inventoryRepository->findByUserAndScroll($user, $scroll);
                if (!$entry) {
                    $entry = new UserInventory();
                    $entry->setUser($user)->setScroll($scroll)->setQuantity(0);
                    $this->em->persist($entry);
                }
                $entry->setQuantity($entry->getQuantity() + $qty);
                $this->em->flush();
                return ['type' => 'scroll', 'scroll' => ['name' => $scroll->getName()], 'quantity' => $qty];

            case 'hero':
                $hero = $recipe->getResultHero();
                $this->createUserHero($user, $hero);
                $this->em->flush();
                return ['type' => 'hero', 'hero' => ['name' => $hero->getName(), 'rarity' => $hero->getRarity()]];

            case 'coin':
                $qty = $recipe->getResultQuantity();
                $user->setGoldToken($user->getGoldToken() + $qty);
                $this->em->flush();
                return ['type' => 'coin', 'quantity' => $qty];
        }

        return [];
    }

    private function createUserHero(User $user, \App\Entity\Hero $hero): UserHero
    {
        $uh = new UserHero();
        $uh->setUser($user)->setHero($hero);

        for ($slot = 1; $slot <= 3; $slot++) {
            $module = new HeroModule();
            $module->setSlotIndex($slot)->setLevel(1);
            for ($ext = 1; $ext <= 2; $ext++) {
                $s = new EquippedExtension();
                $s->setSlotIndex($ext);
                $module->addSlot($s);
                $this->em->persist($s);
            }
            $uh->addModule($module);
            $this->em->persist($module);
        }

        $this->em->persist($uh);
        return $uh;
    }
}
