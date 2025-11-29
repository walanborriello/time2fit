<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients')]
class ClientController extends AbstractController
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
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'api_clients_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->checkAccess();
        $user = $this->getUser();
        $instructorProfile = $user->getInstructorProfile();
        
        if ($user->getRoles() === ['ROLE_ADMIN'] || in_array('ROLE_ADMIN', $user->getRoles())) {
            $clients = $this->clientRepository->findAll();
        } else {
            $clients = [];
            if ($instructorProfile) {
                foreach ($instructorProfile->getInstructorClients() as $ic) {
                    if ($ic->isActive()) {
                        $clients[] = $ic->getClient();
                    }
                }
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn($c) => [
                'id' => $c->getId(),
                'email' => $c->getEmail(),
                'firstName' => $c->getFirstName(),
                'lastName' => $c->getLastName(),
                'fullName' => $c->getFullName(),
                'phone' => $c->getPhone(),
                'hasUser' => $c->getUser() !== null,
            ], $clients),
        ]);
    }

    #[Route('', name: 'api_clients_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->checkAccess();
        $data = json_decode($request->getContent(), true);
        
        $existing = $this->clientRepository->findByEmail($data['email'] ?? '');
        if ($existing) {
            return new JsonResponse(['success' => false, 'message' => 'Cliente già esistente'], Response::HTTP_BAD_REQUEST);
        }

        $client = new Client();
        $client->setEmail($data['email'] ?? '');
        $client->setFirstName($data['firstName'] ?? null);
        $client->setLastName($data['lastName'] ?? null);
        $client->setPhone($data['phone'] ?? null);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => [
            'id' => $client->getId(),
            'email' => $client->getEmail(),
            'fullName' => $client->getFullName(),
        ]], Response::HTTP_CREATED);
    }
}

