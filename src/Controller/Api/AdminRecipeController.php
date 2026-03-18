<?php

namespace App\Controller\Api;

use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Repository\ExtensionRepository;
use App\Repository\HeroRepository;
use App\Repository\ItemRepository;
use App\Repository\RecipeRepository;
use App\Repository\ScrollRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD admin pour les recettes de crafting.
 *
 * GET    /api/admin/recipes        → liste toutes les recettes (avec ingrédients)
 * POST   /api/admin/recipes        → créer une recette
 * PUT    /api/admin/recipes/{id}   → modifier une recette (remplace tous les ingrédients)
 * DELETE /api/admin/recipes/{id}   → supprimer une recette
 */
#[Route('/api/admin/recipes', name: 'api_admin_recipes_')]
#[IsGranted('ROLE_ADMIN')]
class AdminRecipeController extends AbstractController
{
    public function __construct(
        private readonly RecipeRepository    $recipeRepository,
        private readonly ExtensionRepository $extensionRepository,
        private readonly ScrollRepository    $scrollRepository,
        private readonly HeroRepository      $heroRepository,
        private readonly ItemRepository      $itemRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(
            array_map($this->serialize(...), $this->recipeRepository->findAllWithIngredients())
        );
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $recipe = new Recipe();
        $error  = $this->hydrate($recipe, $data);
        if ($error) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($recipe);
        $this->em->flush();

        return $this->json($this->serialize($recipe), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $recipe = $this->recipeRepository->find($id);
        if (!$recipe) {
            return $this->json(['message' => 'Recette introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Supprimer les anciens ingrédients (orphanRemoval s'en occupe au flush)
        foreach ($recipe->getIngredients()->toArray() as $ing) {
            $recipe->removeIngredient($ing);
        }

        $data  = json_decode($request->getContent(), true) ?? [];
        $error = $this->hydrate($recipe, $data);
        if ($error) {
            return $this->json(['message' => $error], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();
        return $this->json($this->serialize($recipe));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $recipe = $this->recipeRepository->find($id);
        if (!$recipe) {
            return $this->json(['message' => 'Recette introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($recipe);
        $this->em->flush();
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function hydrate(Recipe $recipe, array $data): ?string
    {
        $name       = trim($data['name'] ?? '');
        $category   = $data['category']   ?? '';
        $resultType = $data['resultType'] ?? '';

        if (!$name) return 'Le nom est requis.';
        if (!in_array($category, Recipe::CATEGORIES, true))     return 'Catégorie invalide.';
        if (!in_array($resultType, Recipe::RESULT_TYPES, true)) return 'Type de résultat invalide.';

        $recipe->setName($name)
               ->setCategory($category)
               ->setActive((bool) ($data['active'] ?? true))
               ->setResultType($resultType)
               ->setResultQuantity(max(1, (int) ($data['resultQuantity'] ?? 1)))
               ->setResultExtension(null)
               ->setResultExtensionRarity(null)
               ->setResultScroll(null)
               ->setResultHero(null);

        // Résultat
        switch ($resultType) {
            case 'extension':
                $rarity = $data['resultExtensionRarity'] ?? '';
                if (!in_array($rarity, \App\Entity\Extension::RARITIES, true)) {
                    return 'Rareté du résultat invalide.';
                }
                $recipe->setResultExtensionRarity($rarity);
                break;
            case 'scroll':
                $s = $this->scrollRepository->find((int) ($data['resultScrollId'] ?? 0));
                if (!$s) return 'Parchemin résultat introuvable.';
                $recipe->setResultScroll($s);
                break;
            case 'hero':
                $h = $this->heroRepository->find((int) ($data['resultHeroId'] ?? 0));
                if (!$h) return 'Héros résultat introuvable.';
                $recipe->setResultHero($h);
                break;
            // coin : pas de FK
        }

        // Ingrédients
        foreach ($data['ingredients'] ?? [] as $ingData) {
            $ing  = new RecipeIngredient();
            $type = $ingData['ingredientType'] ?? '';
            if (!in_array($type, RecipeIngredient::TYPES, true)) {
                return "Type d'ingrédient invalide : $type";
            }
            $ing->setIngredientType($type)
                ->setQuantity(max(1, (int) ($ingData['quantity'] ?? 1)));

            switch ($type) {
                case 'item':
                    $item = $this->itemRepository->find((int) ($ingData['itemId'] ?? 0));
                    if (!$item) return 'Item ingrédient introuvable.';
                    $ing->setItem($item);
                    break;
                case 'scroll':
                    $scroll = $this->scrollRepository->find((int) ($ingData['scrollId'] ?? 0));
                    if (!$scroll) return 'Parchemin ingrédient introuvable.';
                    $ing->setScroll($scroll);
                    break;
                case 'extension':
                    $ext = $this->extensionRepository->find((int) ($ingData['extensionId'] ?? 0));
                    if (!$ext) return 'Extension ingrédient introuvable.';
                    $ing->setExtension($ext);
                    break;
                case 'extension_rarity':
                    $rarity = $ingData['extensionRarity'] ?? '';
                    if (!in_array($rarity, \App\Entity\Extension::RARITIES, true)) {
                        return "Rareté invalide : $rarity";
                    }
                    $ing->setExtensionRarity($rarity);
                    break;
                // coin : pas de FK
            }

            $recipe->addIngredient($ing);
            $this->em->persist($ing);
        }

        return null;
    }

    private function serialize(Recipe $r): array
    {
        $result = null;
        switch ($r->getResultType()) {
            case 'extension':
                $rarity = $r->getResultExtensionRarity();
                $result = $rarity ? ['rarity' => $rarity] : null;
                break;
            case 'scroll':
                $s = $r->getResultScroll();
                $result = $s ? ['id' => $s->getId(), 'name' => $s->getName()] : null;
                break;
            case 'hero':
                $h = $r->getResultHero();
                $result = $h ? ['id' => $h->getId(), 'name' => $h->getName(), 'rarity' => $h->getRarity()] : null;
                break;
        }

        return [
            'id'             => $r->getId(),
            'name'           => $r->getName(),
            'category'       => $r->getCategory(),
            'active'         => $r->isActive(),
            'resultType'     => $r->getResultType(),
            'resultQuantity' => $r->getResultQuantity(),
            'result'         => $result,
            'ingredients'    => $r->getIngredients()->map(fn(\App\Entity\RecipeIngredient $ing) => [
                'id'             => $ing->getId(),
                'ingredientType' => $ing->getIngredientType(),
                'quantity'       => $ing->getQuantity(),
                'item'           => ($i = $ing->getItem())   ? ['id' => $i->getId(),   'name' => $i->getName()]                                                               : null,
                'scroll'         => ($s = $ing->getScroll()) ? ['id' => $s->getId(),   'name' => $s->getName()]                                                               : null,
                'extension'      => ($e = $ing->getExtension()) ? ['id' => $e->getId(), 'stat' => $e->getStat(), 'rarity' => $e->getRarity()]                                 : null,
                'extensionRarity' => $ing->getExtensionRarity(),
            ])->toArray(),
        ];
    }
}
