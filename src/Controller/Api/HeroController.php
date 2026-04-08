<?php
namespace App\Controller\Api;

use App\Entity\Hero;
use App\Repository\FactionRepository;
use App\Repository\HeroRepository;
use App\Repository\OrigineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/heroes', name: 'api_hero_')]
class HeroController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository    $heroRepository,
        private readonly FactionRepository $factionRepository,
        private readonly OrigineRepository $origineRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map($this->serialize(...), $this->heroRepository->findAll()));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $hero = $this->heroRepository->find($id);
        if (!$hero) {
            return $this->json(['message' => 'Héros introuvable'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($hero));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['name'])) {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['type']) || !in_array($data['type'], Hero::TYPES, true)) {
            return $this->json(['message' => 'type doit être : ' . implode(', ', Hero::TYPES)], Response::HTTP_BAD_REQUEST);
        }

        $hero = new Hero();
        $this->hydrate($hero, $data);
        $this->heroRepository->save($hero, true);

        return $this->json($this->serialize($hero), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $hero = $this->heroRepository->find($id);
        if (!$hero) {
            return $this->json(['message' => 'Héros introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($hero, $data);
        $this->heroRepository->save($hero, true);

        return $this->json($this->serialize($hero));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $hero = $this->heroRepository->find($id);
        if (!$hero) {
            return $this->json(['message' => 'Héros introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->heroRepository->remove($hero, true);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Hero $hero, array $data): void
    {
        if (isset($data['name']))        $hero->setName(trim($data['name']));
        if (isset($data['description'])) $hero->setDescription($data['description']);
        if (isset($data['rarity']))      $hero->setRarity((int) $data['rarity']);
        if (isset($data['type']))        $hero->setType($data['type']);
        if (isset($data['attack']))      $hero->setAttack((int) $data['attack']);
        if (isset($data['defense']))     $hero->setDefense((int) $data['defense']);
        if (isset($data['hp']))          $hero->setHp((int) $data['hp']);
        if (isset($data['speed']))       $hero->setSpeed((int) $data['speed']);
        if (isset($data['critRate']))    $hero->setCritRate((int) $data['critRate']);
        if (isset($data['critDamage']))  $hero->setCritDamage((int) $data['critDamage']);
        if (isset($data['accuracy']))    $hero->setAccuracy((int) $data['accuracy']);
        if (isset($data['resistance']))  $hero->setResistance((int) $data['resistance']);        if (array_key_exists('scrollObtainable', $data)) $hero->setScrollObtainable((bool) $data['scrollObtainable']);
        if (array_key_exists('factionId', $data)) {
            $hero->setFaction($data['factionId'] ? $this->factionRepository->find((int) $data['factionId']) : null);
        }
        if (array_key_exists('origineId', $data)) {
            $hero->setOrigine($data['origineId'] ? $this->origineRepository->find((int) $data['origineId']) : null);
        }
    }

    private function serialize(Hero $hero): array
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
            'scrollObtainable' => $hero->isScrollObtainable(),
            'faction'     => $faction ? ['id' => $faction->getId(), 'name' => $faction->getName()] : null,
            'origine'     => $origine ? ['id' => $origine->getId(), 'name' => $origine->getName()] : null,
        ];
    }
}
