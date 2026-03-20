<?php

namespace App\Controller\Api;

use App\Entity\ArenaAdminTeam;
use App\Entity\ArenaSeason;
use App\Repository\ArenaAdminTeamRepository;
use App\Repository\ArenaSeasonRepository;
use App\Repository\HeroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des saisons et équipes bot d'arène.
 * Restreint aux ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
class ArenaAdminController extends AbstractController
{
    private const MAX_ADMIN_TEAMS = 5;

    public function __construct(
        private readonly ArenaSeasonRepository   $seasonRepository,
        private readonly ArenaAdminTeamRepository $adminTeamRepository,
        private readonly HeroRepository          $heroRepository,
        private readonly EntityManagerInterface  $em,
    ) {}

    // ── Saisons ───────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/arena/seasons
     * Liste toutes les saisons (actives et terminées).
     */
    #[Route('/api/admin/arena/seasons', name: 'api_admin_arena_seasons_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $seasons = $this->seasonRepository->findBy([], ['startedAt' => 'DESC']);

        return $this->json(array_map(fn(ArenaSeason $s) => [
            'id'        => $s->getId(),
            'name'      => $s->getName(),
            'isActive'  => $s->isActive(),
            'startedAt' => $s->getStartedAt()->format('Y-m-d H:i'),
            'endsAt'    => $s->getEndsAt()?->format('Y-m-d'),
        ], $seasons));
    }

    /**
     * POST /api/admin/arena/seasons
     * Crée une nouvelle saison (et désactive la saison actuelle le cas échéant).
     *
     * Body JSON : { "name": "Saison 2", "endsAt": "2026-04-15" }
     */
    #[Route('/api/admin/arena/seasons', name: 'api_admin_arena_seasons_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body  = json_decode($request->getContent(), true) ?? [];
        $name  = trim((string) ($body['name'] ?? ''));
        $endsAt = isset($body['endsAt']) ? \DateTimeImmutable::createFromFormat('Y-m-d', $body['endsAt']) : null;

        if ($name === '') {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }

        // Désactiver la saison actuelle si nécessaire
        $current = $this->seasonRepository->findActive();
        if ($current) {
            $current->setIsActive(false);
        }

        $season = (new ArenaSeason())
            ->setName($name)
            ->setEndsAt($endsAt ?: null);

        $this->em->persist($season);
        $this->em->flush();

        return $this->json([
            'id'        => $season->getId(),
            'name'      => $season->getName(),
            'isActive'  => $season->isActive(),
            'startedAt' => $season->getStartedAt()->format('Y-m-d H:i'),
            'endsAt'    => $season->getEndsAt()?->format('Y-m-d'),
        ], Response::HTTP_CREATED);
    }

    /**
     * POST /api/admin/arena/seasons/{id}/close
     * Clôture une saison.
     * Body optionnel : { "createNext": true, "nextName": "Saison 3", "nextEndsAt": "2026-05-01" }
     */
    #[Route('/api/admin/arena/seasons/{id}/close', name: 'api_admin_arena_seasons_close', methods: ['POST'])]
    public function close(int $id, Request $request): JsonResponse
    {
        $season = $this->seasonRepository->find($id);
        if (!$season) {
            return $this->json(['message' => 'Saison introuvable'], Response::HTTP_NOT_FOUND);
        }

        $season->setIsActive(false);

        $body      = json_decode($request->getContent(), true) ?? [];
        $nextSeason = null;

        if (!empty($body['createNext'])) {
            $nextName  = trim((string) ($body['nextName'] ?? ''));
            $nextEndsAt = isset($body['nextEndsAt'])
                ? \DateTimeImmutable::createFromFormat('Y-m-d', $body['nextEndsAt'])
                : null;

            if ($nextName === '') {
                $nextName = 'Prochaine saison';
            }

            $nextSeason = (new ArenaSeason())
                ->setName($nextName)
                ->setEndsAt($nextEndsAt ?: null);
            $this->em->persist($nextSeason);
        }

        $this->em->flush();

        return $this->json([
            'closed'     => $season->getId(),
            'nextSeason' => $nextSeason ? [
                'id'       => $nextSeason->getId(),
                'name'     => $nextSeason->getName(),
                'endsAt'   => $nextSeason->getEndsAt()?->format('Y-m-d'),
                'isActive' => $nextSeason->isActive(),
            ] : null,
        ]);
    }

    // ── Équipes bot ───────────────────────────────────────────────────────────

    /**
     * GET /api/admin/arena/teams
     * Liste toutes les équipes bot (actives et inactives).
     */
    #[Route('/api/admin/arena/teams', name: 'api_admin_arena_teams_list', methods: ['GET'])]
    public function teamsList(): JsonResponse
    {
        $teams = $this->adminTeamRepository->findBy([], ['slotIndex' => 'ASC']);

        return $this->json(array_map([$this, 'serializeAdminTeam'], $teams));
    }

    /**
     * POST /api/admin/arena/teams
     * Crée une équipe bot (max 5 au total).
     *
     * Body JSON :
     * {
     *   "name": "Équipe Acier",
     *   "slotIndex": 1,
     *   "hero1Id": 12, "hero2Id": 7, "hero3Id": null, "hero4Id": null,
     *   "leadFactionId": 3, "leadOrigineId": null
     * }
     */
    #[Route('/api/admin/arena/teams', name: 'api_admin_arena_teams_create', methods: ['POST'])]
    public function teamsCreate(Request $request): JsonResponse
    {
        if ($this->adminTeamRepository->countAll() >= self::MAX_ADMIN_TEAMS) {
            return $this->json(
                ['message' => 'Limite de ' . self::MAX_ADMIN_TEAMS . ' équipes bot atteinte'],
                Response::HTTP_CONFLICT,
            );
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            return $this->json(['message' => 'name est requis'], Response::HTTP_BAD_REQUEST);
        }

        $team = (new ArenaAdminTeam())
            ->setName($name)
            ->setSlotIndex(max(1, min(5, (int) ($body['slotIndex'] ?? 1))))
            ->setLeadFactionId(isset($body['leadFactionId']) ? (int) $body['leadFactionId'] : null)
            ->setLeadOrigineId(isset($body['leadOrigineId']) ? (int) $body['leadOrigineId'] : null);

        foreach (['hero1Id' => 'setHero1', 'hero2Id' => 'setHero2', 'hero3Id' => 'setHero3', 'hero4Id' => 'setHero4'] as $key => $setter) {
            $heroId = isset($body[$key]) && $body[$key] !== null ? (int) $body[$key] : null;
            $hero   = $heroId ? $this->heroRepository->find($heroId) : null;
            $team->$setter($hero);
        }

        $this->em->persist($team);
        $this->em->flush();

        return $this->json($this->serializeAdminTeam($team), Response::HTTP_CREATED);
    }

    /**
     * PUT /api/admin/arena/teams/{id}
     * Met à jour une équipe bot (héros, nom, leads, isActive).
     */
    #[Route('/api/admin/arena/teams/{id}', name: 'api_admin_arena_teams_update', methods: ['PUT'])]
    public function teamsUpdate(int $id, Request $request): JsonResponse
    {
        $team = $this->adminTeamRepository->find($id);
        if (!$team) {
            return $this->json(['message' => 'Équipe introuvable'], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true) ?? [];

        if (isset($body['name'])) {
            $name = trim((string) $body['name']);
            if ($name !== '') { $team->setName($name); }
        }
        if (isset($body['slotIndex'])) {
            $team->setSlotIndex(max(1, min(5, (int) $body['slotIndex'])));
        }
        if (array_key_exists('leadFactionId', $body)) {
            $team->setLeadFactionId($body['leadFactionId'] !== null ? (int) $body['leadFactionId'] : null);
        }
        if (array_key_exists('leadOrigineId', $body)) {
            $team->setLeadOrigineId($body['leadOrigineId'] !== null ? (int) $body['leadOrigineId'] : null);
        }
        if (isset($body['isActive'])) {
            $team->setIsActive((bool) $body['isActive']);
        }

        foreach (['hero1Id' => 'setHero1', 'hero2Id' => 'setHero2', 'hero3Id' => 'setHero3', 'hero4Id' => 'setHero4'] as $key => $setter) {
            if (!array_key_exists($key, $body)) continue;
            $heroId = $body[$key] !== null ? (int) $body[$key] : null;
            $hero   = $heroId ? $this->heroRepository->find($heroId) : null;
            $team->$setter($hero);
        }

        $this->em->flush();

        return $this->json($this->serializeAdminTeam($team));
    }

    /**
     * DELETE /api/admin/arena/teams/{id}
     * Supprime une équipe bot.
     */
    #[Route('/api/admin/arena/teams/{id}', name: 'api_admin_arena_teams_delete', methods: ['DELETE'])]
    public function teamsDelete(int $id): JsonResponse
    {
        $team = $this->adminTeamRepository->find($id);
        if (!$team) {
            return $this->json(['message' => 'Équipe introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($team);
        $this->em->flush();

        return $this->json(['deleted' => $id]);
    }

    // ── Serialisation ─────────────────────────────────────────────────────────

    private function serializeAdminTeam(ArenaAdminTeam $team): array
    {
        $heroes = array_map(fn($h) => [
            'id'       => $h->getId(),
            'name'     => $h->getName(),
            'rarity'   => $h->getRarity(),
            'faction'  => $h->getFaction()?->getName(),
            'origine'  => $h->getOrigine()?->getName(),
        ], $team->getHeroes());

        return [
            'id'            => $team->getId(),
            'name'          => $team->getName(),
            'slotIndex'     => $team->getSlotIndex(),
            'isActive'      => $team->isActive(),
            'isEmpty'       => $team->isEmpty(),
            'leadFactionId' => $team->getLeadFactionId(),
            'leadOrigineId' => $team->getLeadOrigineId(),
            'heroes'        => $heroes,
            'hero1Id'       => $team->getHero1()?->getId(),
            'hero2Id'       => $team->getHero2()?->getId(),
            'hero3Id'       => $team->getHero3()?->getId(),
            'hero4Id'       => $team->getHero4()?->getId(),
        ];
    }
}
