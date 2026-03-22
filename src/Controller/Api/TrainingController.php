<?php

namespace App\Controller\Api;

use App\Entity\EquippedExtension;
use App\Entity\HeroModule;
use App\Entity\TrainingSlot;
use App\Entity\UserExtension;
use App\Repository\ExtensionRepository;
use App\Repository\TrainingSlotRepository;
use App\Repository\UserHeroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Training Center — idle training slots.
 *
 * GET    /api/me/training             — list active slots
 * POST   /api/me/training/start       — start a training task
 * POST   /api/me/training/{id}/claim  — claim completed training
 * DELETE /api/me/training/{id}/cancel — cancel in-progress training
 */
#[Route('/api/me/training')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TrainingController extends AbstractController
{
    public function __construct(
        private readonly TrainingSlotRepository $trainingRepo,
        private readonly UserHeroRepository     $userHeroRepo,
        private readonly EntityManagerInterface $em,
        private readonly ExtensionRepository    $extensionRepo,
    ) {}

    // ── GET /api/me/training ─────────────────────────────────────────────────

    #[Route('', methods: ['GET'], name: 'api_training_list')]
    public function list(): JsonResponse
    {
        $user  = $this->getUser();
        $slots = $this->trainingRepo->findActiveByUser($user);

        return $this->json(array_map([$this, 'serialize'], $slots));
    }

    // ── POST /api/me/training/start ──────────────────────────────────────────

    #[Route('/start', methods: ['POST'], name: 'api_training_start')]
    public function start(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $slotIndex  = (int) ($data['slotIndex']  ?? 0);
        $userHeroId = (int) ($data['userHeroId'] ?? 0);
        $taskType   = (string) ($data['taskType'] ?? '');

        // ── Basic validation ─────────────────────────────────────────────────

        if ($slotIndex < 1 || $slotIndex > 4) {
            return $this->json(['message' => 'slotIndex doit être entre 1 et 4.'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($taskType, [TrainingSlot::TASK_UPGRADE_MODULES, TrainingSlot::TASK_APPLY_EXTENSIONS], true)) {
            return $this->json(['message' => 'taskType invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->trainingRepo->countActiveByUser($user) >= 4) {
            return $this->json(['message' => 'Tous les emplacements d\'entraînement sont occupés.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->trainingRepo->findActiveByUserAndSlot($user, $slotIndex) !== null) {
            return $this->json(['message' => "L'emplacement $slotIndex est déjà utilisé."], Response::HTTP_BAD_REQUEST);
        }

        // ── Hero ownership ───────────────────────────────────────────────────

        $userHero = $this->userHeroRepo->find($userHeroId);
        if (!$userHero || $userHero->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Héros introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($this->trainingRepo->findActiveByUserAndHero($user, $userHeroId) !== null) {
            return $this->json(['message' => 'Ce héros est déjà en entraînement.'], Response::HTTP_BAD_REQUEST);
        }

        // ── Task-specific validation & duration ──────────────────────────────

        $now = new \DateTimeImmutable();

        if ($taskType === TrainingSlot::TASK_UPGRADE_MODULES) {
            $minutes = $this->computeUpgradeDuration($userHero->getModules()->toArray());
            if ($minutes <= 0) {
                return $this->json(['message' => 'Les modules de ce héros sont déjà au niveau maximum.'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            // apply_extensions — user picks slot + stat/rarity; rolledValue is randomised on claim
            $slotsInput = $data['slots'] ?? [];
            if (!is_array($slotsInput) || count($slotsInput) === 0) {
                return $this->json(['message' => 'Sélectionnez au moins un emplacement.'], Response::HTTP_BAD_REQUEST);
            }

            $heroSlotMap = [];
            foreach ($userHero->getModules() as $module) {
                foreach ($module->getSlots() as $eqSlot) {
                    $heroSlotMap[$eqSlot->getId()] = ['slot' => $eqSlot, 'module' => $module];
                }
            }

            // Seed rarity usage per module from already-equipped slots
            $rarityUsage = [];
            $statsUsed   = [];  // moduleId => string[]
            foreach ($userHero->getModules() as $module) {
                $cnt  = ['commun' => 0, 'peu_commun' => 0, 'epique' => 0, 'legendaire' => 0];
                $stats = [];
                foreach ($module->getSlots() as $eqSlot) {
                    if (!$eqSlot->isEmpty()) {
                        $r = $eqSlot->getUserExtension()->getExtension()->getRarity();
                        $cnt[$r]++;
                        $stats[] = $eqSlot->getUserExtension()->getExtension()->getStat();
                    }
                }
                $rarityUsage[$module->getId()] = $cnt;
                $statsUsed[$module->getId()]   = $stats;
            }

            $usedSlotIds      = [];
            $validAssignments = [];
            foreach ($slotsInput as $asgn) {
                $slotId      = (int) ($asgn['equippedSlotId'] ?? 0);
                $extensionId = (int) ($asgn['extensionId']    ?? 0);

                if (!isset($heroSlotMap[$slotId])) {
                    return $this->json(['message' => "Slot $slotId introuvable sur ce héros."], Response::HTTP_BAD_REQUEST);
                }
                ['slot' => $eqSlot, 'module' => $module] = $heroSlotMap[$slotId];

                if (!$eqSlot->isEmpty()) {
                    return $this->json(['message' => "Le slot $slotId est déjà occupé."], Response::HTTP_BAD_REQUEST);
                }
                if ($eqSlot->getSlotIndex() > $module->getSlotCount()) {
                    return $this->json(['message' => "Le slot $slotId est verrouillé."], Response::HTTP_BAD_REQUEST);
                }
                if (in_array($slotId, $usedSlotIds, true)) {
                    return $this->json(['message' => "Le slot $slotId est sélectionné plusieurs fois."], Response::HTTP_BAD_REQUEST);
                }

                $ext = $this->extensionRepo->find($extensionId);
                if (!$ext) {
                    return $this->json(['message' => "Extension $extensionId introuvable."], Response::HTTP_BAD_REQUEST);
                }

                $rarity = $ext->getRarity();
                $limit  = $module->getRarityLimit($rarity);
                if (($rarityUsage[$module->getId()][$rarity] ?? 0) >= $limit) {
                    return $this->json([
                        'message' => "Limite de rareté « {$rarity} » atteinte pour le module {$module->getSlotIndex()}.",
                    ], Response::HTTP_BAD_REQUEST);
                }

                $stat = $ext->getStat();
                if (in_array($stat, $statsUsed[$module->getId()], true)) {
                    return $this->json([
                        'message' => "La stat « {$stat} » est déjà présente sur le module {$module->getSlotIndex()}.",
                    ], Response::HTTP_BAD_REQUEST);
                }

                $rarityUsage[$module->getId()][$rarity]++;
                $statsUsed[$module->getId()][] = $stat;
                $usedSlotIds[]      = $slotId;
                $validAssignments[] = ['equippedSlotId' => $slotId, 'extensionId' => $extensionId];
            }

            $minutes  = 20 * count($validAssignments);
            $taskData = $validAssignments;
        }

        $finishedAt   = $now->modify("+{$minutes} minutes");
        $trainingSlot = new TrainingSlot($user, $userHero, $slotIndex, $taskType, $now, $finishedAt);
        if (isset($taskData)) {
            $trainingSlot->setTaskData($taskData);
        }
        $this->em->persist($trainingSlot);
        $this->em->flush();

        return $this->json($this->serialize($trainingSlot), Response::HTTP_CREATED);
    }

    // ── POST /api/me/training/{id}/claim ─────────────────────────────────────

    #[Route('/{id}/claim', methods: ['POST'], name: 'api_training_claim')]
    public function claim(int $id): JsonResponse
    {
        $user = $this->getUser();
        $slot = $this->trainingRepo->find($id);

        if (!$slot || $slot->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Entraînement introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if ($slot->isClaimed()) {
            return $this->json(['message' => 'Cet entraînement a déjà été réclamé.'], Response::HTTP_BAD_REQUEST);
        }
        if (!$slot->isReady()) {
            $remaining = $slot->getFinishedAt()->getTimestamp() - time();
            return $this->json(
                ['message' => 'L\'entraînement n\'est pas encore terminé.', 'remainingSeconds' => $remaining],
                Response::HTTP_BAD_REQUEST
            );
        }

        $results = $this->applyTraining($slot);
        $slot->claim();
        $this->em->flush();

        return $this->json(['message' => 'Entraînement terminé !', 'results' => $results]);
    }

    // ── DELETE /api/me/training/{id}/cancel ──────────────────────────────────

    #[Route('/{id}/cancel', methods: ['DELETE'], name: 'api_training_cancel')]
    public function cancel(int $id): JsonResponse
    {
        $user = $this->getUser();
        $slot = $this->trainingRepo->find($id);

        if (!$slot || $slot->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Entraînement introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if ($slot->isClaimed()) {
            return $this->json(['message' => 'Cet entraînement est déjà terminé.'], Response::HTTP_BAD_REQUEST);
        }

        $this->em->remove($slot);
        $this->em->flush();

        return $this->json(['message' => 'Entraînement annulé.']);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /** Total minutes needed to max all modules of a hero from their current levels. */
    private function computeUpgradeDuration(array $modules): int
    {
        $total = 0;
        foreach ($modules as $module) {
            if ($module->getLevel() < 2) $total += TrainingSlot::DURATION_LEVEL_1_TO_2;
            if ($module->getLevel() < 3) $total += TrainingSlot::DURATION_LEVEL_2_TO_3;
        }
        return $total;
    }

    /** Apply the training effects when claiming, return human-readable result lines. */
    private function applyTraining(TrainingSlot $slot): array
    {
        $results  = [];
        $userHero = $slot->getUserHero();

        if ($slot->getTaskType() === TrainingSlot::TASK_UPGRADE_MODULES) {
            foreach ($userHero->getModules() as $module) {
                $prev = $module->getLevel();
                while ($module->getLevel() < 3) {
                    $newLevel = $module->getLevel() + 1;
                    $module->setLevel($newLevel);
                    // Create the newly unlocked extension slot
                    $newSlotIndex     = HeroModule::SLOT_COUNTS[$newLevel];
                    $existingIndexes  = $module->getSlots()->map(fn ($s) => $s->getSlotIndex())->toArray();
                    if (!in_array($newSlotIndex, $existingIndexes, true)) {
                        $newSlot = (new EquippedExtension())->setSlotIndex($newSlotIndex);
                        $module->addSlot($newSlot);
                        $this->em->persist($newSlot);
                    }
                }
                if ($module->getLevel() > $prev) {
                    $results[] = "Module {$module->getSlotIndex()} : niveau {$prev} → {$module->getLevel()}";
                }
            }
        } else {
            // apply_extensions — create UserExtension with random rolledValue from the chosen catalogue entry
            foreach ($slot->getTaskData() ?? [] as $asgn) {
                $eqSlot = $this->em->getRepository(EquippedExtension::class)->find($asgn['equippedSlotId']);
                $ext    = $this->extensionRepo->find($asgn['extensionId']);
                if (!$eqSlot || !$ext) continue;

                $ue = (new UserExtension())
                    ->setUser($slot->getUser())
                    ->setExtension($ext)
                    ->setRolledValue(random_int($ext->getMinValue(), $ext->getMaxValue()));
                $this->em->persist($ue);

                $eqSlot->setUserExtension($ue);
                $module    = $eqSlot->getModule();
                $results[] = sprintf(
                    'Module %d slot %d : %s (%s) +%d',
                    $module?->getSlotIndex(),
                    $eqSlot->getSlotIndex(),
                    $ext->getStat(),
                    $ext->getRarity(),
                    $ue->getRolledValue()
                );
            }
            if (empty($results)) {
                $results[] = 'Aucune extension générée.';
            }
        }

        return $results;
    }

    /** Serialize a TrainingSlot for the frontend. */
    private function serialize(TrainingSlot $slot): array
    {
        $hero    = $slot->getUserHero()->getHero();
        $modules = array_values($slot->getUserHero()->getModules()->map(fn ($m) => [
            'slotIndex' => $m->getSlotIndex(),
            'level'     => $m->getLevel(),
        ])->toArray());

        return [
            'id'         => $slot->getId(),
            'slotIndex'  => $slot->getSlotIndex(),
            'taskType'   => $slot->getTaskType(),
            'startedAt'  => $slot->getStartedAt()->format(\DateTimeInterface::ATOM),
            'finishedAt' => $slot->getFinishedAt()->format(\DateTimeInterface::ATOM),
            'claimedAt'  => $slot->getClaimedAt()?->format(\DateTimeInterface::ATOM),
            'isReady'    => $slot->isReady(),
            'hero'       => [
                'id'     => $slot->getUserHero()->getId(),
                'name'   => $hero->getName(),
                'rarity' => $hero->getRarity(),
            ],
            'modules' => $modules,
        ];
    }
}
