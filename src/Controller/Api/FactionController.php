<?php
namespace App\Controller\Api;

use App\Entity\Faction;use App\Passive\PassiveRegistry;use App\Repository\FactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/factions', name: 'api_faction_')]
class FactionController extends AbstractController
{
    public function __construct(
        private readonly FactionRepository $factionRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $factions = $this->factionRepository->findAll();

        return $this->json(array_map($this->serialize(...), $factions));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $faction = $this->factionRepository->find($id);
        if (!$faction) {
            return $this->json(['message' => 'Faction introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($faction));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name']) || empty($data['passiveName'])) {
            return $this->json(['message' => 'name et passiveName sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $faction = new Faction();
        $this->hydrate($faction, $data);
        $this->factionRepository->save($faction, true);

        return $this->json($this->serialize($faction), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $faction = $this->factionRepository->find($id);
        if (!$faction) {
            return $this->json(['message' => 'Faction introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($faction, $data);
        $this->factionRepository->save($faction, true);

        return $this->json($this->serialize($faction));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $faction = $this->factionRepository->find($id);
        if (!$faction) {
            return $this->json(['message' => 'Faction introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->factionRepository->remove($faction, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Faction $faction, array $data): void
    {
        if (isset($data['name']))               $faction->setName(trim($data['name']));
        if (isset($data['description']))        $faction->setDescription($data['description']);
        if (isset($data['passiveName']))        $faction->setPassiveName(trim($data['passiveName']));
        if (isset($data['passiveDescription'])) $faction->setPassiveDescription($data['passiveDescription']);
    }

    private function serialize(Faction $faction): array
    {
        $passive = PassiveRegistry::get($faction->getPassiveName());
        return [
            'id'                 => $faction->getId(),
            'name'               => $faction->getName(),
            'description'        => $faction->getDescription(),
            'passiveName'        => $faction->getPassiveName(),
            'passiveDescription' => $faction->getPassiveDescription(),
            'thresholds'         => $passive?->thresholds() ?? [],
        ];
    }
}
