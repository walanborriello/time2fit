<?php

namespace App\Command;

use App\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:config',
    description: 'Seed initial configuration values',
)]
class SeedConfigCommand extends Command
{
    public function __construct(private SettingsService $settingsService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $defaults = [
            'reminder_days_offset' => [
                'value' => '7',
                'description' => 'Numero di giorni prima della scadenza per inviare promemoria email',
            ],
            'openai_enabled' => [
                'value' => 'true',
                'description' => 'Abilita/disabilita funzionalità AI (OpenAI)',
            ],
            'cloudinary_enabled' => [
                'value' => 'false',
                'description' => 'Abilita/disabilita Cloudinary per storage media',
            ],
        ];

        foreach ($defaults as $key => $data) {
            $this->settingsService->set($key, $data['value'], $data['description']);
        }

        $io->success('Configurazioni iniziali create con successo!');

        return Command::SUCCESS;
    }
}

