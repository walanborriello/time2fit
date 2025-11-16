<?php

namespace App\Controller\Api;

use App\Entity\TrainingPlan;
use App\Entity\TrainingPlanExercise;
use App\Repository\ClientRepository;
use App\Repository\TrainingPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class TrainingPlanController extends AbstractController
{
    public function __construct(
        private TrainingPlanRepository $trainingPlanRepository,
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/clients/{clientId}/plans', name: 'api_plans_list', methods: ['GET'])]
    #[IsGranted('ROLE_INSTRUCTOR')]
    public function list(int $clientId): JsonResponse
    {
        $client = $this->clientRepository->find($clientId);
        if (!$client) {
            return new JsonResponse(['success' => false, 'message' => 'Cliente non trovato'], Response::HTTP_NOT_FOUND);
        }

        $plans = $client->getTrainingPlans()->toArray();
        usort($plans, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn($p) => $this->serializePlan($p), $plans),
        ]);
    }

    #[Route('/clients/{clientId}/plans/active', name: 'api_plans_active', methods: ['GET'])]
    public function getActive(int $clientId): JsonResponse
    {
        $plan = $this->trainingPlanRepository->findActiveByClient($clientId);
        if (!$plan) {
            return new JsonResponse(['success' => false, 'message' => 'Nessuna scheda attiva'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(['success' => true, 'data' => $this->serializePlan($plan)]);
    }

    #[Route('/clients/{clientId}/plans', name: 'api_plans_create', methods: ['POST'])]
    #[IsGranted('ROLE_INSTRUCTOR')]
    public function create(int $clientId, Request $request): JsonResponse
    {
        $client = $this->clientRepository->find($clientId);
        if (!$client) {
            return new JsonResponse(['success' => false, 'message' => 'Cliente non trovato'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $instructorProfile = $user->getInstructorProfile();
        if (!$instructorProfile) {
            return new JsonResponse(['success' => false, 'message' => 'Profilo istruttore non trovato'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Deactivate previous active plans
        $previousPlans = $this->trainingPlanRepository->createQueryBuilder('tp')
            ->where('tp.client = :clientId')
            ->andWhere('tp.isActive = true')
            ->setParameter('clientId', $clientId)
            ->getQuery()
            ->getResult();

        foreach ($previousPlans as $prevPlan) {
            $prevPlan->setIsActive(false);
        }

        $plan = new TrainingPlan();
        $plan->setName($data['name'] ?? 'Nuova Scheda');
        $plan->setClient($client);
        $plan->setInstructor($instructorProfile);
        $plan->setExpiresAt(new \DateTime($data['expiresAt'] ?? '+30 days'));
        $plan->setIsActive(true);

        if (isset($data['exercises']) && is_array($data['exercises'])) {
            foreach ($data['exercises'] as $index => $exData) {
                $tpe = new TrainingPlanExercise();
                $exercise = $this->entityManager->getRepository(\App\Entity\Exercise::class)->find($exData['exerciseId']);
                if ($exercise) {
                    $tpe->setExercise($exercise);
                    $tpe->setSets($exData['sets'] ?? 3);
                    $tpe->setReps($exData['reps'] ?? 10);
                    $tpe->setWeight($exData['weight'] ?? null);
                    $tpe->setRestSeconds($exData['restSeconds'] ?? null);
                    $tpe->setOrderIndex($index);
                    $plan->addTrainingPlanExercise($tpe);
                }
            }
        }

        $this->entityManager->persist($plan);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => $this->serializePlan($plan)], Response::HTTP_CREATED);
    }

    #[Route('/plans/{id}', name: 'api_plans_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $plan = $this->trainingPlanRepository->find($id);
        if (!$plan) {
            return new JsonResponse(['success' => false, 'message' => 'Scheda non trovata'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(['success' => true, 'data' => $this->serializePlan($plan)]);
    }

    private function serializePlan(TrainingPlan $plan): array
    {
        $exercises = [];
        foreach ($plan->getTrainingPlanExercises() as $tpe) {
            $exercises[] = [
                'id' => $tpe->getId(),
                'exerciseId' => $tpe->getExercise()->getId(),
                'exerciseName' => $tpe->getExercise()->getName(),
                'exerciseMediaUrl' => $tpe->getExercise()->getMediaUrl(),
                'sets' => $tpe->getSets(),
                'reps' => $tpe->getReps(),
                'weight' => $tpe->getWeight(),
                'restSeconds' => $tpe->getRestSeconds(),
                'orderIndex' => $tpe->getOrderIndex(),
            ];
        }

        return [
            'id' => $plan->getId(),
            'name' => $plan->getName(),
            'clientId' => $plan->getClient()->getId(),
            'clientName' => $plan->getClient()->getFullName(),
            'instructorId' => $plan->getInstructor()->getId(),
            'instructorName' => $plan->getInstructor()->getUser()->getFullName(),
            'expiresAt' => $plan->getExpiresAt()->format('Y-m-d'),
            'isActive' => $plan->isActive(),
            'isExpired' => $plan->isExpired(),
            'createdAt' => $plan->getCreatedAt()->format('Y-m-d H:i:s'),
            'exercises' => $exercises,
        ];
    }
}

