<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/admin/training/complete-all
 *   Admin shortcut: instantly marks every pending training slot
 *   (not yet claimed) as finished so players can claim them right away.
 */
#[Route('/api/admin/training/complete-all', name: 'api_admin_training_complete_all', methods: ['POST'])]
#[IsGranted('ROLE_ADMIN')]
class AdminTrainingController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function __invoke(): JsonResponse
    {
        $past = new \DateTimeImmutable('-1 second');

        $count = $this->em->createQuery(
            'UPDATE App\Entity\TrainingSlot ts
             SET ts.finishedAt = :past
             WHERE ts.claimedAt IS NULL
               AND ts.finishedAt > :past'
        )->setParameter('past', $past)->execute();

        return $this->json([
            'message' => "$count entraînement(s) marqué(s) comme terminé(s).",
            'updated' => $count,
        ]);
    }
}
