<?php
namespace App\Controller\Api;

use App\Entity\Origine;use App\Passive\PassiveRegistry;use App\Repository\OrigineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/origines', name: 'api_origine_')]
class OrigineController extends AbstractController
{
    public function __construct(
        private readonly OrigineRepository $origineRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $origines = $this->origineRepository->findAll();

        return $this->json(array_map($this->serialize(...), $origines));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $origine = $this->origineRepository->find($id);
        if (!$origine) {
            return $this->json(['message' => 'Origine introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($origine));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name']) || empty($data['passiveName'])) {
            return $this->json(['message' => 'name et passiveName sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $origine = new Origine();
        $this->hydrate($origine, $data);
        $this->origineRepository->save($origine, true);

        return $this->json($this->serialize($origine), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $origine = $this->origineRepository->find($id);
        if (!$origine) {
            return $this->json(['message' => 'Origine introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($origine, $data);
        $this->origineRepository->save($origine, true);

        return $this->json($this->serialize($origine));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $origine = $this->origineRepository->find($id);
        if (!$origine) {
            return $this->json(['message' => 'Origine introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->origineRepository->remove($origine, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Origine $origine, array $data): void
    {
        if (isset($data['name']))               $origine->setName(trim($data['name']));
        if (isset($data['description']))        $origine->setDescription($data['description']);
        if (isset($data['passiveName']))        $origine->setPassiveName(trim($data['passiveName']));
        if (isset($data['passiveDescription'])) $origine->setPassiveDescription($data['passiveDescription']);
    }

    private function serialize(Origine $origine): array
    {
        $passive = PassiveRegistry::get($origine->getPassiveName());
        return [
            'id'                 => $origine->getId(),
            'name'               => $origine->getName(),
            'description'        => $origine->getDescription(),
            'passiveName'        => $origine->getPassiveName(),
            'passiveDescription' => $origine->getPassiveDescription(),
            'thresholds'         => $passive?->thresholds() ?? [],
        ];
    }
}
