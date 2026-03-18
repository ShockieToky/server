<?php
namespace App\Controller\Api;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\UserHero;
use App\Repository\HeroRepository;
use App\Repository\UserHeroRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Collection de heros d un utilisateur.
 *
 * GET    /api/me/heroes          -> liste des heros possedes (avec modules)
 * POST   /api/me/heroes          -> ajouter un heros (body: {heroId})
 * DELETE /api/me/heroes/{uhId}   -> retirer un heros
 */
#[Route('/api/me/heroes', name: 'api_user_hero_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserHeroController extends AbstractController
{
    public function __construct(
        private readonly UserHeroRepository $userHeroRepository,
        private readonly HeroRepository     $heroRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $entries = $this->userHeroRepository->findByUser($this->getUser());
        return $this->json(array_map($this->serialize(...), $entries));
    }

    #[Route('', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['heroId'])) {
            return $this->json(['message' => 'heroId est requis'], Response::HTTP_BAD_REQUEST);
        }

        $hero = $this->heroRepository->find((int) $data['heroId']);
        if (!$hero) {
            return $this->json(['message' => 'Heros introuvable'], Response::HTTP_NOT_FOUND);
        }

        $userHero = new UserHero();
        $userHero->setUser($this->getUser())->setHero($hero);

        // Creer 3 modules (level 1) avec leurs 2 slots d extension initiaux
        for ($moduleSlot = 1; $moduleSlot <= 3; $moduleSlot++) {
            $module = new HeroModule();
            $module->setSlotIndex($moduleSlot)->setLevel(1);

            for ($extSlot = 1; $extSlot <= 2; $extSlot++) {
                $slot = new EquippedExtension();
                $slot->setSlotIndex($extSlot);
                $module->addSlot($slot);
            }

            $userHero->addModule($module);
        }

        $this->userHeroRepository->save($userHero, true);

        return $this->json($this->serialize($userHero), Response::HTTP_CREATED);
    }

    #[Route('/{uhId}', name: 'remove', methods: ['DELETE'])]
    public function remove(int $uhId): JsonResponse
    {
        $userHero = $this->userHeroRepository->find($uhId);
        if (!$userHero || $userHero->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Heros introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->userHeroRepository->remove($userHero, true);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Admin : voir la collection d un joueur ───────────────────────────────

    #[Route('/admin/{userId}', name: 'admin_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminList(int $userId): JsonResponse
    {
        $entries = $this->userHeroRepository->createQueryBuilder('uh')
            ->andWhere('IDENTITY(uh.user) = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()->getResult();

        return $this->json(array_map($this->serialize(...), $entries));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function serialize(UserHero $uh): array
    {
        $hero    = $uh->getHero();
        $faction = $hero?->getFaction();
        $origine = $hero?->getOrigine();

        return [
            'id'          => $uh->getId(),
            'acquiredAt'  => $uh->getAcquiredAt()->format(\DateTime::ATOM),
            'hero'        => [
                'id'          => $hero?->getId(),
                'name'        => $hero?->getName(),
                'description' => $hero?->getDescription(),
                'rarity'      => $hero?->getRarity(),
                'type'        => $hero?->getType(),
                'attack'      => $hero?->getAttack(),
                'defense'     => $hero?->getDefense(),
                'hp'          => $hero?->getHp(),
                'speed'       => $hero?->getSpeed(),
                'critRate'    => $hero?->getCritRate(),
                'critDamage'  => $hero?->getCritDamage(),
                'accuracy'    => $hero?->getAccuracy(),
                'resistance'  => $hero?->getResistance(),
                'faction' => $faction ? ['id' => $faction->getId(), 'name' => $faction->getName()] : null,
                'origine' => $origine ? ['id' => $origine->getId(), 'name' => $origine->getName()] : null,
            ],
            'modules' => array_map(
                fn(HeroModule $m) => [
                    'id'           => $m->getId(),
                    'slotIndex'    => $m->getSlotIndex(),
                    'level'        => $m->getLevel(),
                    'slotCount'    => $m->getSlotCount(),
                    'rarityLimits' => HeroModule::RARITY_LIMITS[$m->getLevel()],
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
                ],
                $uh->getModules()->toArray()
            ),
        ];
    }
}