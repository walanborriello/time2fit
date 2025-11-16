<?php

namespace App\Controller\Api;

use App\Entity\InstructorClient;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients/{id}/takeover', name: 'api_takeover', methods: ['POST'])]
#[IsGranted('ROLE_INSTRUCTOR')]
class TakeoverController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(int $id): JsonResponse
    {
        $client = $this->clientRepository->find($id);
        if (!$client) {
            return new JsonResponse(['success' => false, 'message' => 'Cliente non trovato'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $instructorProfile = $user->getInstructorProfile();
        if (!$instructorProfile) {
            return new JsonResponse(['success' => false, 'message' => 'Profilo istruttore non trovato'], Response::HTTP_FORBIDDEN);
        }

        // Check if already assigned
        $existing = $this->entityManager->getRepository(InstructorClient::class)->findOneBy([
            'instructor' => $instructorProfile,
            'client' => $client,
        ]);

        if ($existing) {
            return new JsonResponse(['success' => false, 'message' => 'Cliente già assegnato'], Response::HTTP_BAD_REQUEST);
        }

        // Deactivate other assignments
        $otherAssignments = $this->entityManager->getRepository(InstructorClient::class)->findBy([
            'client' => $client,
            'isActive' => true,
        ]);

        foreach ($otherAssignments as $assignment) {
            $assignment->setIsActive(false);
        }

        // Create new assignment
        $assignment = new InstructorClient();
        $assignment->setInstructor($instructorProfile);
        $assignment->setClient($client);
        $assignment->setIsActive(true);

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Cliente preso in gestione',
            'data' => [
                'clientId' => $client->getId(),
                'clientName' => $client->getFullName(),
            ],
        ]);
    }
}

