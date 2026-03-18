<?php

namespace App\Controller\Api;

use App\Repository\EquippedExtensionRepository;
use App\Repository\UserExtensionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Collection d'extensions du joueur connecté.
 *
 * GET /api/me/extensions
 *   Retourne toutes les UserExtension du joueur avec leur emplacement d'équipement si applicable.
 */
#[Route('/api/me/extensions', name: 'api_me_extensions_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserExtensionController extends AbstractController
{
    public function __construct(
        private readonly UserExtensionRepository     $userExtensionRepository,
        private readonly EquippedExtensionRepository $equippedExtensionRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $extensions = $this->userExtensionRepository->findByUser($user);

        // Construire une map userExtension.id => equipped_extension pour savoir où chacune est équipée
        $equippedSlots = $this->equippedExtensionRepository->findBy(
            ['userExtension' => $extensions]
        );

        $equippedMap = [];
        foreach ($equippedSlots as $slot) {
            $ueId = $slot->getUserExtension()?->getId();
            if ($ueId !== null) {
                $module   = $slot->getModule();
                $userHero = $module?->getUserHero();
                $equippedMap[$ueId] = [
                    'slotId'       => $slot->getId(),
                    'slotIndex'    => $slot->getSlotIndex(),
                    'moduleSlot'   => $module?->getSlotIndex(),
                    'moduleLevel'  => $module?->getLevel(),
                    'userHeroId'   => $userHero?->getId(),
                    'heroName'     => $userHero?->getHero()?->getName(),
                ];
            }
        }

        $data = array_map(function (\App\Entity\UserExtension $ue) use ($equippedMap) {
            $ext = $ue->getExtension();
            return [
                'id'          => $ue->getId(),
                'rolledValue' => $ue->getRolledValue(),
                'extension'   => [
                    'id'       => $ext->getId(),
                    'stat'     => $ext->getStat(),
                    'rarity'   => $ext->getRarity(),
                    'minValue' => $ext->getMinValue(),
                    'maxValue' => $ext->getMaxValue(),
                ],
                'equippedIn'  => $equippedMap[$ue->getId()] ?? null,
            ];
        }, $extensions);

        return $this->json($data);
    }
}
