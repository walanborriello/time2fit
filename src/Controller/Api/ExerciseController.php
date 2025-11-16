<?php

namespace App\Controller\Api;

use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/exercises')]
#[IsGranted('ROLE_INSTRUCTOR')]
class ExerciseController extends AbstractController
{
    public function __construct(
        private ExerciseRepository $exerciseRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'api_exercises_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $exercises = $this->exerciseRepository->findAll();
        $data = array_map(fn($e) => $this->serializeExercise($e), $exercises);
        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route('/{id}', name: 'api_exercises_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(['success' => true, 'data' => $this->serializeExercise($exercise)]);
    }

    #[Route('', name: 'api_exercises_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $exercise = new Exercise();
        $exercise->setName($data['name'] ?? '');
        $exercise->setDescription($data['description'] ?? null);
        $exercise->setMediaUrl($data['mediaUrl'] ?? null);
        $exercise->setMediaType($data['mediaType'] ?? null);
        $exercise->setMuscleGroup($data['muscleGroup'] ?? null);

        if (isset($data['exerciseSetId'])) {
            $exerciseSet = $this->entityManager->getRepository(\App\Entity\ExerciseSet::class)->find($data['exerciseSetId']);
            if ($exerciseSet) {
                $exercise->setExerciseSet($exerciseSet);
            }
        }

        $this->entityManager->persist($exercise);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => $this->serializeExercise($exercise)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_exercises_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) $exercise->setName($data['name']);
        if (isset($data['description'])) $exercise->setDescription($data['description']);
        if (isset($data['mediaUrl'])) $exercise->setMediaUrl($data['mediaUrl']);
        if (isset($data['mediaType'])) $exercise->setMediaType($data['mediaType']);
        if (isset($data['muscleGroup'])) $exercise->setMuscleGroup($data['muscleGroup']);

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => $this->serializeExercise($exercise)]);
    }

    #[Route('/{id}', name: 'api_exercises_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($exercise);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Esercizio eliminato']);
    }

    private function serializeExercise(Exercise $exercise): array
    {
        return [
            'id' => $exercise->getId(),
            'name' => $exercise->getName(),
            'description' => $exercise->getDescription(),
            'mediaUrl' => $exercise->getMediaUrl(),
            'mediaType' => $exercise->getMediaType(),
            'muscleGroup' => $exercise->getMuscleGroup(),
            'exerciseSetId' => $exercise->getExerciseSet()?->getId(),
        ];
    }
}

