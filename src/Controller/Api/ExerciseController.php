<?php

namespace App\Controller\Api;

use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use App\Service\AiDescriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/exercises')]
class ExerciseController extends AbstractController
{
    private function checkAccess(): void
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles) && !in_array('ROLE_INSTRUCTOR', $roles)) {
            throw $this->createAccessDeniedException();
        }
    }

    public function __construct(
        private ExerciseRepository $exerciseRepository,
        private EntityManagerInterface $entityManager,
        private AiDescriptionService $aiDescriptionService
    ) {
    }

    #[Route('', name: 'api_exercises_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->checkAccess();
        $exercises = $this->exerciseRepository->findAll();
        $data = array_map(fn($e) => $this->serializeExercise($e), $exercises);
        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    #[Route('/{id}', name: 'api_exercises_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $this->checkAccess();
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(['success' => true, 'data' => $this->serializeExercise($exercise)]);
    }

    #[Route('', name: 'api_exercises_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->checkAccess();
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
        $this->checkAccess();
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
        $this->checkAccess();
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($exercise);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Esercizio eliminato']);
    }

    #[Route('/generate-description', name: 'api_exercises_generate_description_generic', methods: ['POST'])]
    public function generateDescriptionGeneric(Request $request): JsonResponse
    {
        $this->checkAccess();
        $data = json_decode($request->getContent(), true);
        
        $exerciseName = $data['name'] ?? '';
        $muscleGroup = $data['muscleGroup'] ?? null;
        $customPrompt = $data['prompt'] ?? null;
        
        if (empty($exerciseName)) {
            return new JsonResponse(['success' => false, 'message' => 'Nome esercizio richiesto'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            // Crea un esercizio temporaneo per usare il metodo strutturato
            $tempExercise = new Exercise();
            $tempExercise->setName($exerciseName);
            if ($muscleGroup) {
                $tempExercise->setMuscleGroup($muscleGroup);
            }
            
            // Usa il nuovo metodo che costruisce il prompt strutturato
            $description = $this->aiDescriptionService->generateExerciseDescription($tempExercise, $customPrompt);
            
            return new JsonResponse([
                'success' => true,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Errore nella generazione descrizione: ' . $e->getMessage(),
                'description' => null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/generate-description-stream', name: 'api_exercises_generate_description_stream', methods: ['GET'])]
    public function generateDescriptionStream(int $id, Request $request): StreamedResponse
    {
        $this->checkAccess();
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }

        $customPrompt = $request->query->get('prompt') ?? $exercise->getAiPrompt() ?? null;
        return $this->generateDescriptionStreamed($exercise, $customPrompt);
    }

    #[Route('/{id}/generate-description', name: 'api_exercises_generate_description', methods: ['POST'])]
    public function generateDescription(int $id, Request $request): Response
    {
        $this->checkAccess();
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $customPrompt = $data['prompt'] ?? $exercise->getAiPrompt() ?? null;
        $useStreaming = $data['stream'] ?? false; // Opzione per streaming SSE
        
        if ($useStreaming) {
            // Usa Server-Sent Events per aggiornamenti in tempo reale
            return $this->generateDescriptionStreamed($exercise, $customPrompt);
        }
        
        // Comportamento normale con callback per retry info
        $retryInfo = [];
        $onRetry = function(int $attempt, int $delay, string $reason) use (&$retryInfo) {
            $retryInfo[] = [
                'attempt' => $attempt,
                'delay' => $delay,
                'reason' => $reason,
                'timestamp' => time()
            ];
        };
        
        try {
            // Usa il nuovo metodo che costruisce il prompt strutturato
            $description = $this->aiDescriptionService->generateExerciseDescription($exercise, $customPrompt, $onRetry);
            
            // Verifica se è una descrizione fallback (contiene pattern tipici del fallback)
            $isFallback = str_contains($description, 'Assumi la posizione corretta') || 
                          str_contains($description, 'Prepara l\'attrezzatura');
            
            // Aggiorna la descrizione dell'esercizio
            $exercise->setDescription($description);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'description' => $description,
                'retryInfo' => $retryInfo, // Informazioni sui retry effettuati
                'warning' => $isFallback ? 'Descrizione generica (OpenAI non configurato o errore API. Controlla i log per dettagli.)' : null
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Errore nella generazione descrizione: ' . $e->getMessage(),
                'description' => null,
                'retryInfo' => $retryInfo
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    private function generateDescriptionStreamed(Exercise $exercise, ?string $customPrompt): StreamedResponse
    {
        $response = new StreamedResponse(function() use ($exercise, $customPrompt) {
            // Invia header SSE
            echo "data: " . json_encode(['type' => 'start', 'message' => 'Generazione descrizione in corso...']) . "\n\n";
            ob_flush();
            flush();
            
            $description = null;
            $error = null;
            
            $onRetry = function(int $attempt, int $delay, string $reason) {
                $remaining = $delay;
                while ($remaining > 0) {
                    echo "data: " . json_encode([
                        'type' => 'retry',
                        'attempt' => $attempt,
                        'delay' => $remaining,
                        'reason' => $reason
                    ]) . "\n\n";
                    ob_flush();
                    flush();
                    sleep(1);
                    $remaining--;
                }
            };
            
            try {
                $description = $this->aiDescriptionService->generateExerciseDescription($exercise, $customPrompt, $onRetry);
                
                $exercise->setDescription($description);
                $this->entityManager->flush();
                
                echo "data: " . json_encode([
                    'type' => 'success',
                    'description' => $description
                ]) . "\n\n";
            } catch (\Exception $e) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]) . "\n\n";
            }
            
            ob_flush();
            flush();
        });
        
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disabilita buffering nginx
        
        return $response;
    }

    #[Route('/{id}/generate-gif', name: 'api_exercises_generate_gif', methods: ['POST'])]
    public function generateGif(int $id, Request $request): JsonResponse
    {
        $this->checkAccess();
        $exercise = $this->exerciseRepository->find($id);
        if (!$exercise) {
            return new JsonResponse(['success' => false, 'message' => 'Esercizio non trovato'], Response::HTTP_NOT_FOUND);
        }

        // Usa il nuovo metodo che cerca su Giphy o genera con DALL-E
        $gifUrl = $this->aiDescriptionService->generateExerciseGif($exercise);
        
        if ($gifUrl === null) {
            // Fallback: ritorna un URL placeholder
            $gifUrl = 'https://via.placeholder.com/300x300/00ff00/000000?text=' . urlencode($exercise->getName());
        }
        
        // Aggiorna il mediaUrl dell'esercizio
        $exercise->setMediaUrl($gifUrl);
        $exercise->setMediaType('GIF');
        $this->entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'mediaUrl' => $gifUrl
        ]);
    }

    private function serializeExercise(Exercise $exercise): array
    {
        return [
            'id' => $exercise->getId(),
            'name' => $exercise->getName(),
            'description' => $exercise->getDescription(),
            'aiPrompt' => $exercise->getAiPrompt(),
            'mediaUrl' => $exercise->getMediaUrl(),
            'mediaType' => $exercise->getMediaType(),
            'muscleGroup' => $exercise->getMuscleGroup(),
            'exerciseSetId' => $exercise->getExerciseSet()?->getId(),
        ];
    }
}

