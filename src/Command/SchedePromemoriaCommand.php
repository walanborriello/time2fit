<?php

namespace App\Command;

use App\Service\ReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:schede:promemoria',
    description: 'Send reminder emails for expiring training plans',
)]
class SchedePromemoriaCommand extends Command
{
    public function __construct(private ReminderService $reminderService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->reminderService->sendExpirationReminders();

        if ($count > 0) {
            $io->success("Sent {$count} reminder email(s)");
        } else {
            $io->info('No reminders to send');
        }

        return Command::SUCCESS;
    }
}

