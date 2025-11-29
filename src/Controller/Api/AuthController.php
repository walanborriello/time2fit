<?php

namespace App\Controller\Api;

use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository
    ) {
    }
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(AuthenticationUtils $authenticationUtils): JsonResponse
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error) {
            return new JsonResponse([
                'success' => false,
                'message' => $error->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Credenziali non valide',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => 'Logout effettuato con successo',
        ]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Non autenticato',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $userData = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
            ];

            // Add client info if user is a client
            // Use repository to safely check for client
            if (in_array('ROLE_CLIENT', $user->getRoles())) {
                try {
                    $client = $this->clientRepository->findOneBy(['user' => $user]);
                    if ($client) {
                        $userData['clientId'] = $client->getId();
                    }
                } catch (\Exception $e) {
                    // Client might not exist yet, ignore
                }
            }

            return new JsonResponse([
                'success' => true,
                'user' => $userData,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

