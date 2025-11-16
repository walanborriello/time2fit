<?php

namespace App\Service;

use App\Entity\Config;
use App\Entity\TrainingPlan;
use App\Repository\ConfigRepository;
use App\Repository\TrainingPlanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ReminderService
{
    public function __construct(
        private TrainingPlanRepository $trainingPlanRepository,
        private UserRepository $userRepository,
        private ConfigRepository $configRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function sendExpirationReminders(): int
    {
        $daysOffset = (int) ($this->configRepository->getValue('reminder_days_offset', '7') ?? 7);
        $targetDate = new \DateTime("+{$daysOffset} days");

        $plans = $this->trainingPlanRepository->createQueryBuilder('tp')
            ->where('DATE(tp.expiresAt) = :targetDate')
            ->andWhere('tp.isActive = true')
            ->setParameter('targetDate', $targetDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $count = 0;
        $adminEmails = $this->getAdminEmails();

        foreach ($plans as $plan) {
            foreach ($adminEmails as $email) {
                try {
                    $this->sendReminderEmail($email, $plan);
                    $count++;
                } catch (\Exception $e) {
                    $this->logger?->error('Reminder email error: ' . $e->getMessage());
                }
            }
        }

        return $count;
    }

    private function getAdminEmails(): array
    {
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();

        return array_map(fn($user) => $user->getEmail(), $users);
    }

    private function sendReminderEmail(string $to, TrainingPlan $plan): void
    {
        $clientName = $plan->getClient()->getFullName() ?: $plan->getClient()->getEmail();
        $expiresAt = $plan->getExpiresAt()->format('d/m/Y');

        $email = (new Email())
            ->to($to)
            ->subject("Promemoria: Scheda in scadenza - {$clientName}")
            ->html("
                <h2>Promemoria Scadenza Scheda</h2>
                <p>La scheda <strong>{$plan->getName()}</strong> del cliente <strong>{$clientName}</strong> scade il <strong>{$expiresAt}</strong>.</p>
                <p>Cliente: {$plan->getClient()->getEmail()}</p>
                <p>Istruttore: {$plan->getInstructor()->getUser()->getFullName()}</p>
            ");

        $this->mailer->send($email);
    }
}

