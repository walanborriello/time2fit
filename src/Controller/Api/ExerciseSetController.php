<?php

namespace App\Controller\Api;

use App\Entity\ExerciseSet;
use App\Repository\ExerciseSetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/exercise-sets')]
#[IsGranted('ROLE_ADMIN')]
class ExerciseSetController extends AbstractController
{
    public function __construct(
        private ExerciseSetRepository $exerciseSetRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'api_exercise_sets_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $sets = $this->exerciseSetRepository->findAll();
        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn($s) => [
                'id' => $s->getId(),
                'name' => $s->getName(),
                'gymType' => $s->getGymType()->value,
                'createdAt' => $s->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $sets),
        ]);
    }

    #[Route('', name: 'api_exercise_sets_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $set = new ExerciseSet();
        $set->setName($data['name'] ?? '');
        $set->setGymType(\App\Entity\GymType::from($data['gymType'] ?? 'ISOTONICA'));

        $this->entityManager->persist($set);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => [
            'id' => $set->getId(),
            'name' => $set->getName(),
            'gymType' => $set->getGymType()->value,
        ]], Response::HTTP_CREATED);
    }
}

