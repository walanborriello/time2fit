<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/appointments')]
#[IsGranted('ROLE_INSTRUCTOR_PT')]
class AppointmentController extends AbstractController
{
    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'api_appointments_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $instructorProfile = $user->getInstructorProfile();
        if (!$instructorProfile) {
            return new JsonResponse(['success' => false, 'message' => 'Profilo istruttore non trovato'], Response::HTTP_FORBIDDEN);
        }

        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        $qb = $this->appointmentRepository->createQueryBuilder('a')
            ->where('a.instructor = :instructor')
            ->setParameter('instructor', $instructorProfile)
            ->orderBy('a.startTime', 'ASC');

        if ($startDate) {
            $qb->andWhere('a.startTime >= :start')
                ->setParameter('start', new \DateTime($startDate));
        }
        if ($endDate) {
            $qb->andWhere('a.endTime <= :end')
                ->setParameter('end', new \DateTime($endDate));
        }

        $appointments = $qb->getQuery()->getResult();

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn($a) => [
                'id' => $a->getId(),
                'clientId' => $a->getClient()->getId(),
                'clientName' => $a->getClient()->getFullName(),
                'startTime' => $a->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $a->getEndTime()->format('Y-m-d H:i:s'),
                'notes' => $a->getNotes(),
            ], $appointments),
        ]);
    }

    #[Route('', name: 'api_appointments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        $instructorProfile = $user->getInstructorProfile();
        if (!$instructorProfile) {
            return new JsonResponse(['success' => false, 'message' => 'Profilo istruttore non trovato'], Response::HTTP_FORBIDDEN);
        }

        $client = $this->clientRepository->find($data['clientId'] ?? 0);
        if (!$client) {
            return new JsonResponse(['success' => false, 'message' => 'Cliente non trovato'], Response::HTTP_NOT_FOUND);
        }

        $appointment = new Appointment();
        $appointment->setInstructor($instructorProfile);
        $appointment->setClient($client);
        $appointment->setStartTime(new \DateTime($data['startTime']));
        $appointment->setEndTime(new \DateTime($data['endTime']));
        $appointment->setNotes($data['notes'] ?? null);

        $this->entityManager->persist($appointment);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'data' => [
            'id' => $appointment->getId(),
            'clientName' => $client->getFullName(),
            'startTime' => $appointment->getStartTime()->format('Y-m-d H:i:s'),
            'endTime' => $appointment->getEndTime()->format('Y-m-d H:i:s'),
        ]], Response::HTTP_CREATED);
    }
}

