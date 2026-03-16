<?php
namespace App\Controller\Api;

use App\Entity\Item;
use App\Repository\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/items', name: 'api_item_')]
class ItemController extends AbstractController
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map($this->serialize(...), $this->itemRepository->findAll()));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $item = $this->itemRepository->find($id);
        if (!$item) {
            return $this->json(['message' => 'Item introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($item));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }

        $item = new Item();
        $this->hydrate($item, $data);
        $this->itemRepository->save($item, true);

        return $this->json($this->serialize($item), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->itemRepository->find($id);
        if (!$item) {
            return $this->json(['message' => 'Item introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($item, $data);
        $this->itemRepository->save($item, true);

        return $this->json($this->serialize($item));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $item = $this->itemRepository->find($id);
        if (!$item) {
            return $this->json(['message' => 'Item introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->itemRepository->remove($item, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Item $item, array $data): void
    {
        if (isset($data['name']))        $item->setName(trim($data['name']));
        if (isset($data['description'])) $item->setDescription($data['description']);
    }

    private function serialize(Item $item): array
    {
        return [
            'id'          => $item->getId(),
            'name'        => $item->getName(),
            'description' => $item->getDescription(),
        ];
    }
}