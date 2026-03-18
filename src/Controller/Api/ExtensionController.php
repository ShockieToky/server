<?php
namespace App\Controller\Api;

use App\Entity\Extension;
use App\Repository\ExtensionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion du catalogue d extensions.
 *
 * GET    /api/extensions          -> liste (authentifie)
 * POST   /api/extensions          -> creer (admin)
 * PATCH  /api/extensions/{id}     -> modifier (admin)
 * DELETE /api/extensions/{id}     -> supprimer (admin)
 * POST   /api/extensions/seed     -> initialiser les 32 entrees par defaut (admin)
 */
#[Route('/api/extensions', name: 'api_extension_')]
class ExtensionController extends AbstractController
{
    public function __construct(
        private readonly ExtensionRepository $extensionRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(): JsonResponse
    {
        $all = $this->extensionRepository->findBy([], ['stat' => 'ASC', 'rarity' => 'ASC']);
        return $this->json(array_map($this->serialize(...), $all));
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function get(int $id): JsonResponse
    {
        $ext = $this->extensionRepository->find($id);
        if (!$ext) {
            return $this->json(['message' => 'Extension introuvable'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serialize($ext));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $error = $this->validateData($data);
        if ($error) return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);

        $ext = new Extension();
        $this->hydrate($ext, $data);
        $this->extensionRepository->save($ext, true);

        return $this->json($this->serialize($ext), Response::HTTP_CREATED);
    }

    #[Route('/seed', name: 'seed', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function seed(): JsonResponse
    {
        $created = 0;
        foreach (Extension::DEFAULT_RANGES as $stat => $rarities) {
            foreach ($rarities as $rarity => [$min, $max]) {
                $existing = $this->extensionRepository->findOneBy(['stat' => $stat, 'rarity' => $rarity]);
                if ($existing) continue;

                $ext = new Extension();
                $ext->setStat($stat)->setRarity($rarity)->setMinValue($min)->setMaxValue($max);
                $this->extensionRepository->save($ext);
                $created++;
            }
        }

        // Flush unique après tous les persist
        if ($created > 0) {
            $this->extensionRepository->getEntityManager()->flush();
        }

        return $this->json(['created' => $created, 'message' => "$created extension(s) creee(s)."]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(int $id, Request $request): JsonResponse
    {
        $ext = $this->extensionRepository->find($id);
        if (!$ext) {
            return $this->json(['message' => 'Extension introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($ext, $data);
        $this->extensionRepository->save($ext, true);

        return $this->json($this->serialize($ext));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $ext = $this->extensionRepository->find($id);
        if (!$ext) {
            return $this->json(['message' => 'Extension introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->extensionRepository->remove($ext, true);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validateData(array $data): ?string
    {
        if (empty($data['stat']) || !in_array($data['stat'], Extension::STATS, true)) {
            return 'stat invalide. Valeurs acceptees : ' . implode(', ', Extension::STATS);
        }
        if (empty($data['rarity']) || !in_array($data['rarity'], Extension::RARITIES, true)) {
            return 'rarity invalide. Valeurs acceptees : ' . implode(', ', Extension::RARITIES);
        }
        if (!isset($data['minValue'], $data['maxValue'])) {
            return 'minValue et maxValue sont requis.';
        }
        return null;
    }

    private function hydrate(Extension $ext, array $data): void
    {
        if (isset($data['stat']))      $ext->setStat($data['stat']);
        if (isset($data['rarity']))    $ext->setRarity($data['rarity']);
        if (isset($data['minValue']))  $ext->setMinValue((int) $data['minValue']);
        if (isset($data['maxValue']))  $ext->setMaxValue((int) $data['maxValue']);
    }

    private function serialize(Extension $ext): array
    {
        return [
            'id'       => $ext->getId(),
            'stat'     => $ext->getStat(),
            'rarity'   => $ext->getRarity(),
            'minValue' => $ext->getMinValue(),
            'maxValue' => $ext->getMaxValue(),
        ];
    }
}