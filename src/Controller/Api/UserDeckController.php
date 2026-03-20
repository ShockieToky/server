<?php

namespace App\Controller\Api;

use App\Entity\UserDeck;
use App\Repository\UserDeckRepository;
use App\Repository\UserHeroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Decks pré-enregistrés d'un utilisateur.
 *
 * GET    /api/me/decks         → liste des decks
 * POST   /api/me/decks         → créer un deck { name, heroIds:[id1..4], leadFactionId?, leadOrigineId? }
 * PUT    /api/me/decks/{id}    → modifier un deck
 * DELETE /api/me/decks/{id}    → supprimer un deck
 */
#[Route('/api/me/decks', name: 'api_user_deck_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserDeckController extends AbstractController
{
    public function __construct(
        private readonly UserDeckRepository  $deckRepository,
        private readonly UserHeroRepository  $userHeroRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $decks = $this->deckRepository->findByUser($this->getUser());
        return $this->json(array_map($this->serialize(...), $decks));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return $this->json(['message' => 'Le nom est requis'], Response::HTTP_BAD_REQUEST);
        }

        $deck = new UserDeck();
        $deck->setUser($this->getUser());
        $this->applyData($deck, $data);

        $this->em->persist($deck);
        $this->em->flush();

        return $this->json($this->serialize($deck), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $deck = $this->deckRepository->find($id);
        if (!$deck || $deck->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Deck introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->applyData($deck, $data);
        $deck->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $this->json($this->serialize($deck));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $deck = $this->deckRepository->find($id);
        if (!$deck || $deck->getUser()?->getId() !== $this->getUser()?->getId()) {
            return $this->json(['message' => 'Deck introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($deck);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function applyData(UserDeck $deck, array $data): void
    {
        if (isset($data['name'])) {
            $deck->setName(substr((string) $data['name'], 0, 50));
        }

        $heroIds = array_slice(array_values(array_filter((array) ($data['heroIds'] ?? []))), 0, 4);
        $slots   = ['setHero1', 'setHero2', 'setHero3', 'setHero4'];

        foreach ($slots as $i => $setter) {
            if (isset($heroIds[$i])) {
                $uh = $this->userHeroRepository->find((int) $heroIds[$i]);
                // only allow user's own heroes
                if ($uh && $uh->getUser()?->getId() === $this->getUser()?->getId()) {
                    $deck->$setter($uh);
                } else {
                    $deck->$setter(null);
                }
            } else {
                $deck->$setter(null);
            }
        }

        $deck->setLeadFactionId(isset($data['leadFactionId']) && $data['leadFactionId'] ? (int) $data['leadFactionId'] : null);
        $deck->setLeadOrigineId(isset($data['leadOrigineId']) && $data['leadOrigineId'] ? (int) $data['leadOrigineId'] : null);
    }

    private function serializeHero(?object $userHero): ?array
    {
        if (!$userHero) return null;
        $hero    = $userHero->getHero();
        $faction = $hero?->getFaction();
        $origine = $hero?->getOrigine();
        return [
            'id'     => $userHero->getId(),
            'hero'   => [
                'id'      => $hero?->getId(),
                'name'    => $hero?->getName(),
                'rarity'  => $hero?->getRarity(),
                'type'    => $hero?->getType(),
                'faction' => $faction ? ['id' => $faction->getId(), 'name' => $faction->getName()] : null,
                'origine' => $origine ? ['id' => $origine->getId(), 'name' => $origine->getName()] : null,
            ],
        ];
    }

    private function serialize(UserDeck $deck): array
    {
        return [
            'id'            => $deck->getId(),
            'name'          => $deck->getName(),
            'hero1'         => $this->serializeHero($deck->getHero1()),
            'hero2'         => $this->serializeHero($deck->getHero2()),
            'hero3'         => $this->serializeHero($deck->getHero3()),
            'hero4'         => $this->serializeHero($deck->getHero4()),
            'leadFactionId' => $deck->getLeadFactionId(),
            'leadOrigineId' => $deck->getLeadOrigineId(),
            'createdAt'     => $deck->getCreatedAt()->format(\DateTime::ATOM),
            'updatedAt'     => $deck->getUpdatedAt()->format(\DateTime::ATOM),
        ];
    }
}
