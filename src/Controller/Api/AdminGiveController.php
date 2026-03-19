<?php
namespace App\Controller\Api;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\UserExtension;
use App\Entity\UserHero;
use App\Entity\UserInventory;
use App\Repository\ExtensionRepository;
use App\Repository\HeroRepository;
use App\Repository\ItemRepository;
use App\Repository\ScrollRepository;
use App\Repository\UserExtensionRepository;
use App\Repository\UserHeroRepository;
use App\Repository\UserInventoryRepository;
use App\Repository\UserRepository;
use App\Repository\UserStoryProgressRepository;
use App\Repository\UserDungeonProgressRepository;
use App\Repository\ShopPurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Outils admin : listing des joueurs + distribution de contenu.
 *
 * GET  /api/admin/users         -> liste tous les utilisateurs
 * POST /api/admin/give          -> donner scroll/item/hero a un ou tous les joueurs
 *      body: {
 *        type:     'scroll'|'item'|'hero',
 *        id:       number,           (id dans le catalogue)
 *        quantity: number,           (scroll/item uniquement, defaut 1)
 *        target:   'all'|number      (userId ou 'all')
 *      }
 */
#[Route('/api/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminGiveController extends AbstractController
{
    public function __construct(
        private readonly UserRepository          $userRepository,
        private readonly HeroRepository          $heroRepository,
        private readonly ItemRepository          $itemRepository,
        private readonly ScrollRepository        $scrollRepository,
        private readonly ExtensionRepository     $extensionRepository,
        private readonly UserExtensionRepository $userExtensionRepository,
        private readonly UserHeroRepository      $userHeroRepository,
        private readonly UserInventoryRepository      $inventoryRepository,
        private readonly UserStoryProgressRepository   $storyProgressRepository,
        private readonly UserDungeonProgressRepository  $dungeonProgressRepository,
        private readonly ShopPurchaseRepository          $shopPurchaseRepository,
        private readonly EntityManagerInterface         $em,
    ) {
    }

    // ── Liste des joueurs ─────────────────────────────────────────────────────

    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(): JsonResponse
    {
        $users = $this->userRepository->findAll();
        return $this->json(array_map(
            fn(\App\Entity\User $u) => [
                'id'     => $u->getId(),
                'pseudo' => $u->getPseudo(),
                'email'  => $u->getEmail(),
                'role'   => $u->getRole(),
            ],
            $users
        ));
    }

    // ── Distribution ──────────────────────────────────────────────────────────

    #[Route('/give', name: 'give', methods: ['POST'])]
    public function give(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $type     = $data['type']   ?? null;
        $id       = isset($data['id'])  ? (int) $data['id']  : null;
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $target   = $data['target'] ?? null;

        if (!in_array($type, ['scroll', 'item', 'hero', 'extension'], true)) {
            return $this->json(['message' => 'type doit etre : scroll, item, hero ou extension'], Response::HTTP_BAD_REQUEST);
        }
        if (!$id) {
            return $this->json(['message' => 'id est requis'], Response::HTTP_BAD_REQUEST);
        }
        if ($target === null) {
            return $this->json(['message' => 'target est requis (userId ou "all")'], Response::HTTP_BAD_REQUEST);
        }

        // Charger l'entite catalogue
        [$entity, $notFoundMsg] = match ($type) {
            'hero'      => [$this->heroRepository->find($id),      'Heros introuvable'],
            'item'      => [$this->itemRepository->find($id),      'Item introuvable'],
            'scroll'    => [$this->scrollRepository->find($id),    'Parchemin introuvable'],
            'extension' => [$this->extensionRepository->find($id), 'Extension introuvable'],
        };
        if (!$entity) {
            return $this->json(['message' => $notFoundMsg], Response::HTTP_NOT_FOUND);
        }

        // Determiner la liste des joueurs cibles
        if ($target === 'all') {
            $users = $this->userRepository->findAll();
        } else {
            $user = $this->userRepository->find((int) $target);
            if (!$user) {
                return $this->json(['message' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
            }
            $users = [$user];
        }

        $given = 0;
        foreach ($users as $user) {
            if ($type === 'hero') {
                $this->giveHero($user, $entity);
            } elseif ($type === 'extension') {
                $this->giveExtension($user, $entity, $quantity);
            } else {
                $this->giveInventory($user, $type, $entity, $quantity);
            }
            $given++;
        }

        $this->em->flush();

        return $this->json([
            'given'   => $given,
            'message' => "$given joueur(s) ont recu le contenu.",
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function giveHero(\App\Entity\User $user, \App\Entity\Hero $hero): void
    {
        $userHero = new UserHero();
        $userHero->setUser($user)->setHero($hero);

        for ($moduleSlot = 1; $moduleSlot <= 3; $moduleSlot++) {
            $module = new HeroModule();
            $module->setSlotIndex($moduleSlot)->setLevel(1);
            for ($extSlot = 1; $extSlot <= 2; $extSlot++) {
                $slot = new EquippedExtension();
                $slot->setSlotIndex($extSlot);
                $module->addSlot($slot);
                $this->em->persist($slot);
            }
            $userHero->addModule($module);
            $this->em->persist($module);
        }

        $this->em->persist($userHero);
    }

    private function giveInventory(\App\Entity\User $user, string $type, mixed $entity, int $quantity): void
    {
        if ($type === 'item') {
            $entry = $this->inventoryRepository->findByUserAndItem($user, $entity);
        } else {
            $entry = $this->inventoryRepository->findByUserAndScroll($user, $entity);
        }

        if (!$entry) {
            $entry = new UserInventory();
            $entry->setUser($user);
            if ($type === 'item') $entry->setItem($entity);
            else                  $entry->setScroll($entity);
            $entry->setQuantity(0);
        }

        $entry->setQuantity($entry->getQuantity() + $quantity);
        $this->em->persist($entry);
    }

    // ── Remise à zéro d'un compte ──────────────────────────────────────────

    /**
     * POST /api/admin/reset/{userId}
     * body: { scope: ('heroes'|'inventory'|'extensions')[] }  — vide = tout
     */
    #[Route('/reset/{userId}', name: 'reset', methods: ['POST'])]
    public function reset(int $userId, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['message' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data  = json_decode($request->getContent(), true) ?? [];
        $scope = $data['scope'] ?? ['heroes', 'inventory', 'extensions', 'starter', 'story', 'dungeon', 'shop', 'gold', 'history_token'];
        if (empty($scope)) {
            $scope = ['heroes', 'inventory', 'extensions', 'starter', 'story', 'dungeon', 'shop', 'gold', 'history_token'];
        }

        $done = [];

        if (in_array('extensions', $scope, true)) {
            foreach ($this->userExtensionRepository->findByUser($user) as $ue) {
                $this->em->remove($ue);
            }
            $done[] = 'extensions';
        }

        if (in_array('heroes', $scope, true)) {
            foreach ($this->userHeroRepository->findByUser($user) as $uh) {
                $this->em->remove($uh);
            }
            $done[] = 'héros';
        }

        if (in_array('inventory', $scope, true)) {
            $criteria = ['user' => $user];
            foreach ($this->inventoryRepository->findBy($criteria) as $inv) {
                $this->em->remove($inv);
            }
            $done[] = 'inventaire';
        }

        if (in_array('starter', $scope, true)) {
            $user->setStarterDone(false);
            $done[] = 'faction de départ';
        }

        if (in_array('story', $scope, true)) {
            foreach ($this->storyProgressRepository->findAllByUser($user) as $p) {
                $this->em->remove($p);
            }
            $done[] = 'progression histoire';
        }

        if (in_array('dungeon', $scope, true)) {
            foreach ($this->dungeonProgressRepository->findByUser($user) as $p) {
                $this->em->remove($p);
            }
            $done[] = 'progression donjon';
        }

        if (in_array('shop', $scope, true)) {
            foreach ($this->shopPurchaseRepository->findBy(['user' => $user]) as $p) {
                $this->em->remove($p);
            }
            $done[] = 'limites boutique';
        }

        if (in_array('gold', $scope, true)) {
            $user->setGoldToken(0);
            $done[] = 'pièces d\'or';
        }

        if (in_array('history_token', $scope, true)) {
            $user->setHistoryToken(0);
            $done[] = 'pièces d\'histoire';
        }

        $this->em->flush();

        return $this->json([
            'message' => 'Compte remis à zéro : ' . implode(', ', $done) . '.',
        ]);
    }

    /**
     * Donne `quantity` instances de l'extension au joueur,
     * chacune avec une valeur tirée aléatoirement dans [min, max].
     */
    private function giveExtension(\App\Entity\User $user, \App\Entity\Extension $extension, int $quantity): void
    {
        $min = $extension->getMinValue();
        $max = $extension->getMaxValue();

        for ($i = 0; $i < $quantity; $i++) {
            $ue = new UserExtension();
            $ue->setUser($user)
               ->setExtension($extension)
               ->setRolledValue(($min <= $max) ? random_int($min, $max) : $min);
            $this->em->persist($ue);
        }
    }
}