<?php

namespace App\Controller\Api;

use App\Repository\RecipeRepository;
use App\Service\CraftingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET  /api/recipes?category=...  → recettes actives (par catégorie)
 * POST /api/craft/{recipeId}      → exécuter un craft
 * GET  /api/me/profile            → pseudo + goldToken du joueur connecté
 */
#[Route('/api', name: 'api_crafting_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CraftingController extends AbstractController
{
    public function __construct(
        private readonly RecipeRepository $recipeRepository,
        private readonly CraftingService  $craftingService,
    ) {}

    #[Route('/recipes', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $category = $request->query->get('category', '');
        if (!in_array($category, \App\Entity\Recipe::CATEGORIES, true)) {
            return $this->json(['message' => 'Catégorie invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $recipes = $this->recipeRepository->findByCategory($category);
        return $this->json(array_map($this->serializeRecipe(...), $recipes));
    }

    #[Route('/craft/{recipeId}', name: 'craft', methods: ['POST'])]
    public function craft(int $recipeId): JsonResponse
    {
        $user   = $this->getUser();
        $recipe = $this->recipeRepository->find($recipeId);

        if (!$recipe || !$recipe->isActive()) {
            return $this->json(['message' => 'Recette introuvable ou inactive.'], Response::HTTP_NOT_FOUND);
        }

        $error = $this->craftingService->check($user, $recipe);
        if ($error !== null) {
            return $this->json(['message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->craftingService->execute($user, $recipe);
        return $this->json(['message' => 'Craft réussi !', 'result' => $result]);
    }

    #[Route('/me/profile', name: 'me_profile', methods: ['GET'])]
    public function meProfile(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json([
            'pseudo'       => $user->getPseudo(),
            'goldToken'    => $user->getGoldToken(),
            'historyToken' => $user->getHistoryToken(),
        ]);
    }

    // ── Serialisation ──────────────────────────────────────────────────────────

    private function serializeRecipe(\App\Entity\Recipe $r): array
    {
        return [
            'id'             => $r->getId(),
            'name'           => $r->getName(),
            'category'       => $r->getCategory(),
            'resultType'     => $r->getResultType(),
            'result'         => $this->serializeResult($r),
            'resultQuantity' => $r->getResultQuantity(),
            'ingredients'    => array_map(
                $this->serializeIngredient(...),
                $r->getIngredients()->toArray()
            ),
        ];
    }

    private function serializeResult(\App\Entity\Recipe $r): ?array
    {
        switch ($r->getResultType()) {
            case 'extension':
                $rarity = $r->getResultExtensionRarity();
                return $rarity ? ['rarity' => $rarity] : null;
            case 'scroll':
                $s = $r->getResultScroll();
                return $s ? ['id' => $s->getId(), 'name' => $s->getName()] : null;
            case 'hero':
                $h = $r->getResultHero();
                return $h ? ['id' => $h->getId(), 'name' => $h->getName(), 'rarity' => $h->getRarity()] : null;
            default:
                return null;
        }
    }

    private function serializeIngredient(\App\Entity\RecipeIngredient $ing): array
    {
        $item = $ing->getItem();
        $scroll = $ing->getScroll();
        $ext = $ing->getExtension();
        return [
            'id'             => $ing->getId(),
            'ingredientType' => $ing->getIngredientType(),
            'quantity'       => $ing->getQuantity(),
            'item'           => $item   ? ['id' => $item->getId(),   'name' => $item->getName()]                                         : null,
            'scroll'         => $scroll ? ['id' => $scroll->getId(), 'name' => $scroll->getName()]                                       : null,
            'extension'      => $ext    ? ['id' => $ext->getId(),    'stat' => $ext->getStat(), 'rarity' => $ext->getRarity()]           : null,
            'extensionRarity' => $ing->getExtensionRarity(),
        ];
    }
}
