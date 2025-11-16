<?php

namespace App\Controller\Api;

use App\Entity\ProgressLog;
use App\Repository\ProgressLogRepository;
use App\Repository\TrainingPlanExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ProgressController extends AbstractController
{
    public function __construct(
        private ProgressLogRepository $progressLogRepository,
        private TrainingPlanExerciseRepository $tpeRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/plans/{planId}/progress', name: 'api_progress_list', methods: ['GET'])]
    public function list(int $planId): JsonResponse
    {
        $plan = $this->entityManager->getRepository(\App\Entity\TrainingPlan::class)->find($planId);
        if (!$plan) {
            return new JsonResponse(['success' => false, 'message' => 'Scheda non trovata'], Response::HTTP_NOT_FOUND);
        }

        $logs = [];
        foreach ($plan->getTrainingPlanExercises() as $tpe) {
            foreach ($tpe->getProgressLogs() as $log) {
                $logs[] = $this->serializeLog($log);
            }
        }

        usort($logs, fn($a, $b) => $b['loggedAt'] <=> $a['loggedAt']);

        return new JsonResponse(['success' => true, 'data' => $logs]);
    }

    #[Route('/exercises/{tpeId}/progress', name: 'api_progress_create', methods: ['POST'])]
    public function create(int $tpeId, Request $request): JsonResponse
    {
        $tpe = $this->tpeRepository->find($tpeId);
        if (!$tpe) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio scheda non trovato'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $log = new ProgressLog();
        $log->setTrainingPlanExercise($tpe);
        $log->setSets($data['sets'] ?? $tpe->getSets());
        $log->setReps($data['reps'] ?? $tpe->getReps());
        $log->setWeight($data['weight'] ?? $tpe->getWeight());
        $log->setNotes($data['notes'] ?? null);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => $this->serializeLog($log)], Response::HTTP_CREATED);
    }

    private function serializeLog(ProgressLog $log): array
    {
        return [
            'id' => $log->getId(),
            'trainingPlanExerciseId' => $log->getTrainingPlanExercise()->getId(),
            'exerciseName' => $log->getTrainingPlanExercise()->getExercise()->getName(),
            'sets' => $log->getSets(),
            'reps' => $log->getReps(),
            'weight' => $log->getWeight(),
            'notes' => $log->getNotes(),
            'loggedAt' => $log->getLoggedAt()->format('Y-m-d H:i:s'),
        ];
    }
}

