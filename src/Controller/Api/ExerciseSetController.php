<?php

namespace App\Controller\Api;

use App\Entity\Exercise;
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
            'data' => array_map(fn($s) => $this->serializeSet($s), $sets),
        ]);
    }

    #[Route('/{id}', name: 'api_exercise_sets_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $set = $this->exerciseSetRepository->find($id);
        if (!$set) {
            return new JsonResponse(['success' => false, 'message' => 'Set non trovato'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(['success' => true, 'data' => $this->serializeSet($set)]);
    }

    #[Route('', name: 'api_exercise_sets_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $set = new ExerciseSet();
        $set->setName($data['name'] ?? '');
        $set->setGymType(\App\Entity\GymType::from($data['gymType'] ?? 'ISOTONICA'));
        $set->setDescription($data['description'] ?? null);

        // Gestione esercizi inline
        if (isset($data['exercises']) && is_array($data['exercises'])) {
            foreach ($data['exercises'] as $exerciseData) {
                $exercise = new Exercise();
                $exercise->setName($exerciseData['name'] ?? '');
                $exercise->setDescription($exerciseData['description'] ?? null);
                $exercise->setAiPrompt($exerciseData['aiPrompt'] ?? null);
                $exercise->setMediaUrl($exerciseData['mediaUrl'] ?? null);
                $exercise->setMediaType($exerciseData['mediaType'] ?? null);
                $exercise->setMuscleGroup($exerciseData['muscleGroup'] ?? null);
                $exercise->setExerciseSet($set);
                $this->entityManager->persist($exercise);
            }
        }

        $this->entityManager->persist($set);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => $this->serializeSet($set)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_exercise_sets_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $set = $this->exerciseSetRepository->find($id);
        if (!$set) {
            return new JsonResponse(['success' => false, 'message' => 'Set non trovato'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) {
            $set->setName($data['name']);
        }
        if (isset($data['gymType'])) {
            $set->setGymType(\App\Entity\GymType::from($data['gymType']));
        }
        if (isset($data['description'])) {
            $set->setDescription($data['description']);
        }

        // Gestione esercizi inline: create/update/delete
        if (isset($data['exercises']) && is_array($data['exercises'])) {
            $existingExerciseIds = [];
            
            foreach ($data['exercises'] as $exerciseData) {
                $exerciseId = $exerciseData['id'] ?? null;
                
                if ($exerciseId) {
                    // Update esercizio esistente
                    $exercise = $this->entityManager->getRepository(Exercise::class)->find($exerciseId);
                    if ($exercise && $exercise->getExerciseSet() === $set) {
                        if (isset($exerciseData['name'])) $exercise->setName($exerciseData['name']);
                        if (isset($exerciseData['description'])) $exercise->setDescription($exerciseData['description']);
                        if (isset($exerciseData['aiPrompt'])) $exercise->setAiPrompt($exerciseData['aiPrompt']);
                        if (isset($exerciseData['mediaUrl'])) $exercise->setMediaUrl($exerciseData['mediaUrl']);
                        if (isset($exerciseData['mediaType'])) $exercise->setMediaType($exerciseData['mediaType']);
                        if (isset($exerciseData['muscleGroup'])) $exercise->setMuscleGroup($exerciseData['muscleGroup']);
                        $existingExerciseIds[] = $exerciseId;
                    }
                } else {
                    // Create nuovo esercizio
                    $exercise = new Exercise();
                    $exercise->setName($exerciseData['name'] ?? '');
                    $exercise->setDescription($exerciseData['description'] ?? null);
                    $exercise->setAiPrompt($exerciseData['aiPrompt'] ?? null);
                    $exercise->setMediaUrl($exerciseData['mediaUrl'] ?? null);
                    $exercise->setMediaType($exerciseData['mediaType'] ?? null);
                    $exercise->setMuscleGroup($exerciseData['muscleGroup'] ?? null);
                    $exercise->setExerciseSet($set);
                    $this->entityManager->persist($exercise);
                }
            }
            
            // Delete esercizi rimossi
            foreach ($set->getExercises() as $exercise) {
                if (!in_array($exercise->getId(), $existingExerciseIds)) {
                    $this->entityManager->remove($exercise);
                }
            }
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => $this->serializeSet($set)]);
    }

    #[Route('/{id}', name: 'api_exercise_sets_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $set = $this->exerciseSetRepository->find($id);
        if (!$set) {
            return new JsonResponse(['success' => false, 'message' => 'Set non trovato'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($set);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Set eliminato con successo']);
    }

    private function serializeSet(ExerciseSet $set): array
    {
        return [
            'id' => $set->getId(),
            'name' => $set->getName(),
            'gymType' => $set->getGymType()->value,
            'description' => $set->getDescription(),
            'createdAt' => $set->getCreatedAt()->format('Y-m-d H:i:s'),
            'exercises' => array_map(fn($e) => [
                'id' => $e->getId(),
                'name' => $e->getName(),
                'description' => $e->getDescription(),
                'aiPrompt' => $e->getAiPrompt(),
                'mediaUrl' => $e->getMediaUrl(),
                'mediaType' => $e->getMediaType(),
                'muscleGroup' => $e->getMuscleGroup(),
            ], $set->getExercises()->toArray()),
        ];
    }
}

