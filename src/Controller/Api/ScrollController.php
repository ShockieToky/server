<?php
namespace App\Controller\Api;

use App\Entity\Scroll;
use App\Entity\ScrollRate;
use App\Repository\ScrollRepository;
use App\Service\ScrollPullService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/scrolls', name: 'api_scroll_')]
class ScrollController extends AbstractController
{
    public function __construct(
        private readonly ScrollRepository  $scrollRepository,
        private readonly ScrollPullService $pullService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map($this->serialize(...), $this->scrollRepository->findAll()));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $scroll = $this->scrollRepository->find($id);
        if (!$scroll) {
            return $this->json(['message' => 'Parchemin introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($scroll));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['type']) || !in_array($data['type'], Scroll::TYPES, true)) {
            return $this->json(['message' => 'type doit être : ' . implode(', ', Scroll::TYPES)], Response::HTTP_BAD_REQUEST);
        }

        $scroll = new Scroll();
        $this->hydrate($scroll, $data);
        $this->scrollRepository->save($scroll, true);

        return $this->json($this->serialize($scroll), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $scroll = $this->scrollRepository->find($id);
        if (!$scroll) {
            return $this->json(['message' => 'Parchemin introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($scroll, $data);
        $this->scrollRepository->save($scroll, true);

        return $this->json($this->serialize($scroll));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $scroll = $this->scrollRepository->find($id);
        if (!$scroll) {
            return $this->json(['message' => 'Parchemin introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->scrollRepository->remove($scroll, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /api/scrolls/{id}/pull
     *
     * Authenticated users can pull from a scroll.
     * - type 'scroll'  → returns { type, hero }
     * - type 'choice'  → returns { type, heroes: Hero[5] }
     */
    #[Route('/{id}/pull', name: 'pull', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function pull(int $id): JsonResponse
    {
        $scroll = $this->scrollRepository->find($id);
        if (!$scroll) {
            return $this->json(['message' => 'Parchemin introuvable'], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->pullService->pull($scroll);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($result['type'] === 'choice') {
            return $this->json([
                'type'   => 'choice',
                'heroes' => array_map($this->serializeHero(...), $result['heroes']),
            ]);
        }

        return $this->json([
            'type' => 'scroll',
            'hero' => $this->serializeHero($result['hero']),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function hydrate(Scroll $scroll, array $data): void
    {
        if (isset($data['name']))        $scroll->setName(trim($data['name']));
        if (isset($data['description'])) $scroll->setDescription($data['description']);
        if (isset($data['type']))        $scroll->setType($data['type']);

        if (isset($data['rates']) && is_array($data['rates'])) {
            // Replace all rates
            foreach ($scroll->getRates()->toArray() as $existing) {
                $scroll->removeRate($existing);
            }
            foreach ($data['rates'] as $rateData) {
                if (!isset($rateData['rarity'], $rateData['rate'])) continue;
                $scrollRate = new ScrollRate();
                $scrollRate->setRarity((int) $rateData['rarity']);
                $scrollRate->setRate((float) $rateData['rate']);
                $scroll->addRate($scrollRate);
            }
        }
    }

    private function serialize(Scroll $scroll): array
    {
        $rates = array_map(
            fn(ScrollRate $r) => ['rarity' => $r->getRarity(), 'rate' => $r->getRate()],
            $scroll->getRates()->toArray()
        );

        // Sort by rarity desc for readability
        usort($rates, fn($a, $b) => $b['rarity'] <=> $a['rarity']);

        return [
            'id'          => $scroll->getId(),
            'name'        => $scroll->getName(),
            'description' => $scroll->getDescription(),
            'type'        => $scroll->getType(),
            'rates'       => $rates,
        ];
    }

    private function serializeHero(\App\Entity\Hero $hero): array
    {
        $faction = $hero->getFaction();
        $origine = $hero->getOrigine();

        return [
            'id'          => $hero->getId(),
            'name'        => $hero->getName(),
            'description' => $hero->getDescription(),
            'rarity'      => $hero->getRarity(),
            'type'        => $hero->getType(),
            'attack'      => $hero->getAttack(),
            'defense'     => $hero->getDefense(),
            'hp'          => $hero->getHp(),
            'speed'       => $hero->getSpeed(),
            'critRate'    => $hero->getCritRate(),
            'critDamage'  => $hero->getCritDamage(),
            'accuracy'    => $hero->getAccuracy(),
            'resistance'  => $hero->getResistance(),
            'faction'     => $faction ? ['id' => $faction->getId(), 'name' => $faction->getName()] : null,
            'origine'     => $origine ? ['id' => $origine->getId(), 'name' => $origine->getName()] : null,
        ];
    }
}
