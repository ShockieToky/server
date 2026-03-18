<?php

namespace App\Controller\Api;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Repository\HeroModuleRepository;
use App\Repository\UserInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Amélioration d'un module héros.
 *
 * POST /api/me/heroes/{uhId}/modules/{modId}/upgrade
 *   Coût : niveau 1→2 = 50 pierres magiques, niveau 2→3 = 150 pierres magiques
 *   Retourne le module mis à jour.
 */
#[Route('/api/me/heroes/{uhId}/modules/{modId}/upgrade', name: 'api_module_upgrade', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ModuleUpgradeController extends AbstractController
{
    private const COST = [1 => 50, 2 => 150];

    public function __construct(
        private readonly HeroModuleRepository    $moduleRepository,
        private readonly UserInventoryRepository $inventoryRepository,
        private readonly EntityManagerInterface  $em,
    ) {}

    public function __invoke(int $uhId, int $modId): JsonResponse
    {
        $user = $this->getUser();

        $module = $this->moduleRepository->find($modId);

        if (!$module) {
            return $this->json(['message' => 'Module introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le module appartient bien au héros demandé et à l'utilisateur connecté
        $userHero = $module->getUserHero();
        if ($userHero?->getId() !== $uhId || $userHero?->getUser()?->getId() !== $user?->getId()) {
            return $this->json(['message' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $currentLevel = $module->getLevel();
        if ($currentLevel >= 3) {
            return $this->json(['message' => 'Niveau maximum déjà atteint'], Response::HTTP_BAD_REQUEST);
        }

        $cost = self::COST[$currentLevel];

        $stoneEntry = $this->inventoryRepository->findMagicStones($user);
        if (!$stoneEntry || $stoneEntry->getQuantity() < $cost) {
            $has = $stoneEntry?->getQuantity() ?? 0;
            return $this->json(
                ['message' => "Pierres magiques insuffisantes (besoin : $cost, possédées : $has)"],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Déduire les pierres magiques
        $stoneEntry->setQuantity($stoneEntry->getQuantity() - $cost);
        if ($stoneEntry->getQuantity() <= 0) {
            $this->em->remove($stoneEntry);
        }

        // Monter le niveau du module
        $module->setLevel($currentLevel + 1);

        // Créer les nouveaux slots débloqués
        $newSlotCount    = HeroModule::SLOT_COUNTS[$module->getLevel()];
        $existingCount   = $module->getSlots()->count();
        for ($i = $existingCount + 1; $i <= $newSlotCount; $i++) {
            $slot = (new EquippedExtension())
                ->setSlotIndex($i);
            $module->addSlot($slot);
            $this->em->persist($slot);
        }

        $this->em->flush();

        return $this->json($this->serializeModule($module));
    }

    private function serializeModule(HeroModule $m): array
    {
        return [
            'id'        => $m->getId(),
            'slotIndex' => $m->getSlotIndex(),
            'level'     => $m->getLevel(),
            'slotCount' => $m->getSlotCount(),
            'slots'     => array_map(
                fn(EquippedExtension $s) => [
                    'id'          => $s->getId(),
                    'slotIndex'   => $s->getSlotIndex(),
                    'extension'   => $s->getExtension() ? [
                        'id'     => $s->getExtension()->getId(),
                        'stat'   => $s->getExtension()->getStat(),
                        'rarity' => $s->getExtension()->getRarity(),
                    ] : null,
                    'rolledValue' => $s->getRolledValue(),
                ],
                $m->getSlots()->toArray()
            ),
        ];
    }
}
