<?php
namespace App\Controller\Api;

use App\Repository\EquippedExtensionRepository;
use App\Repository\UserExtensionRepository;
use App\Repository\UserHeroRepository;
use App\Repository\HeroModuleRepository;
use App\Service\ExtensionEquipService;
use App\Service\ModuleUpgradeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des modules et extensions d un heros possede.
 *
 * POST   /api/me/heroes/{uhId}/modules/{moduleId}/upgrade              -> ameliorer module
 * POST   /api/me/heroes/{uhId}/modules/{moduleId}/slots/{slotIdx}/equip   -> equipper extension
 * DELETE /api/me/heroes/{uhId}/modules/{moduleId}/slots/{slotIdx}/unequip -> retirer extension
 */
#[Route('/api/me/heroes', name: 'api_hero_module_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class HeroModuleController extends AbstractController
{
    public function __construct(
        private readonly UserHeroRepository          $userHeroRepository,
        private readonly HeroModuleRepository        $heroModuleRepository,
        private readonly UserExtensionRepository     $userExtensionRepository,
        private readonly EquippedExtensionRepository $equippedExtensionRepository,
        private readonly ModuleUpgradeService        $upgradeService,
        private readonly ExtensionEquipService       $equipService,
    ) {
    }

    #[Route('/{uhId}/modules/{moduleId}/upgrade', name: 'upgrade', methods: ['POST'])]
    public function upgrade(int $uhId, int $moduleId): JsonResponse
    {
        [$module, $error] = $this->resolveModule($uhId, $moduleId);
        if ($error) return $error;

        try {
            $this->upgradeService->upgrade($module, $this->getUser());
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeModule($module));
    }

    #[Route('/{uhId}/modules/{moduleId}/slots/{slotIdx}/equip', name: 'equip', methods: ['POST'])]
    public function equip(int $uhId, int $moduleId, int $slotIdx, Request $request): JsonResponse
    {
        [$module, $error] = $this->resolveModule($uhId, $moduleId);
        if ($error) return $error;

        $data = json_decode($request->getContent(), true) ?? [];
        $userExtension = $this->userExtensionRepository->find((int) ($data['userExtensionId'] ?? 0));
        if (!$userExtension) {
            return $this->json(['message' => 'Extension introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'extension appartient bien au joueur connecté
        if ($userExtension->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Extension introuvable'], Response::HTTP_NOT_FOUND);
        }

        $slot = $this->findSlot($module, $slotIdx);
        if (!$slot) {
            return $this->json(['message' => 'Slot introuvable'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->equipService->equip($slot, $userExtension);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeModule($module));
    }

    #[Route('/{uhId}/modules/{moduleId}/slots/{slotIdx}/unequip', name: 'unequip', methods: ['DELETE'])]
    public function unequip(int $uhId, int $moduleId, int $slotIdx): JsonResponse
    {
        [$module, $error] = $this->resolveModule($uhId, $moduleId);
        if ($error) return $error;

        $slot = $this->findSlot($module, $slotIdx);
        if (!$slot) {
            return $this->json(['message' => 'Slot introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($slot->isEmpty()) {
            return $this->json(['message' => 'Ce slot est deja vide'], Response::HTTP_BAD_REQUEST);
        }

        $this->equipService->unequip($slot);
        return $this->json($this->serializeModule($module));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Verifie la chaine de propriete : slot -> userHero -> user connecte.
     * @return array{0: \App\Entity\HeroModule|null, 1: JsonResponse|null}
     */
    private function resolveModule(int $uhId, int $moduleId): array
    {
        $userHero = $this->userHeroRepository->find($uhId);
        if (!$userHero || $userHero->getUser()?->getId() !== $this->getUser()?->getId()) {
            return [null, $this->json(['message' => 'Heros introuvable'], Response::HTTP_NOT_FOUND)];
        }

        $module = $this->heroModuleRepository->find($moduleId);
        if (!$module || $module->getUserHero()?->getId() !== $uhId) {
            return [null, $this->json(['message' => 'Module introuvable'], Response::HTTP_NOT_FOUND)];
        }

        return [$module, null];
    }

    private function findSlot(\App\Entity\HeroModule $module, int $slotIdx): ?\App\Entity\EquippedExtension
    {
        foreach ($module->getSlots() as $slot) {
            if ($slot->getSlotIndex() === $slotIdx) return $slot;
        }
        return null;
    }

    private function serializeModule(\App\Entity\HeroModule $m): array
    {
        return [
            'id'           => $m->getId(),
            'slotIndex'    => $m->getSlotIndex(),
            'level'        => $m->getLevel(),
            'slotCount'    => $m->getSlotCount(),
            'rarityLimits' => \App\Entity\HeroModule::RARITY_LIMITS[$m->getLevel()],
            'slots'        => array_map(
                fn(\App\Entity\EquippedExtension $s) => [
                    'id'            => $s->getId(),
                    'slotIndex'     => $s->getSlotIndex(),
                    'locked'        => $s->getSlotIndex() > $m->getSlotCount(),
                    'userExtension' => $s->getUserExtension() ? [
                        'id'          => $s->getUserExtension()->getId(),
                        'rolledValue' => $s->getUserExtension()->getRolledValue(),
                        'extension'   => [
                            'id'       => $s->getUserExtension()->getExtension()->getId(),
                            'stat'     => $s->getUserExtension()->getExtension()->getStat(),
                            'rarity'   => $s->getUserExtension()->getExtension()->getRarity(),
                            'minValue' => $s->getUserExtension()->getExtension()->getMinValue(),
                            'maxValue' => $s->getUserExtension()->getExtension()->getMaxValue(),
                        ],
                    ] : null,
                ],
                $m->getSlots()->toArray()
            ),
        ];
    }
}