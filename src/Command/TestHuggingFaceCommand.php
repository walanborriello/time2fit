<?php

namespace App\Command;

use App\Entity\Exercise;
use App\Service\AiDescriptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:huggingface',
    description: 'Test HuggingFace AI configuration and API connection',
)]
class TestHuggingFaceCommand extends Command
{
    public function __construct(
        private AiDescriptionService $aiDescriptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Test configurazione HuggingFace AI');
        
        // Verifica variabili d'ambiente
        $io->section('1. Verifica configurazione');
        $huggingFaceKey = $_ENV['HUGGINGFACE_API_KEY'] ?? null;
        $aiProvider = $_ENV['AI_PROVIDER'] ?? 'auto';
        
        if ($huggingFaceKey) {
            $io->success('HUGGINGFACE_API_KEY configurata: ' . substr($huggingFaceKey, 0, 10) . '...');
        } else {
            $io->warning('HUGGINGFACE_API_KEY non configurata (funzionerà con free tier)');
        }
        
        $io->info("AI_PROVIDER: {$aiProvider}");
        
        // Crea un esercizio di test
        $io->section('2. Creazione esercizio di test');
        $testExercise = new Exercise();
        $testExercise->setName('Panca piana con bilanciere');
        $testExercise->setMuscleGroup('Pettorali');
        
        $io->info("Esercizio: {$testExercise->getName()}");
        $io->info("Muscolo target: {$testExercise->getMuscleGroup()}");
        
        // Test generazione descrizione
        $io->section('3. Test generazione descrizione');
        $io->note('Questo potrebbe richiedere alcuni secondi...');
        
        try {
            $startTime = microtime(true);
            $description = $this->aiDescriptionService->generateExerciseDescription($testExercise);
            $elapsedTime = round(microtime(true) - $startTime, 2);
            
            // Verifica se è una descrizione fallback
            $isFallback = str_contains($description, 'Assumi la posizione corretta') || 
                         str_contains($description, 'Prepara l\'attrezzatura');
            
            if ($isFallback) {
                $io->error('⚠️  La risposta è una descrizione FALLBACK generica!');
                $io->warning('Questo significa che l\'API HuggingFace non ha risposto correttamente.');
                $io->note('Controlla i log in var/log/ per dettagli sugli errori.');
            } else {
                $io->success("✅ Descrizione generata con successo in {$elapsedTime}s!");
            }
            
            $io->section('4. Risultato');
            $io->text('Descrizione generata (' . strlen($description) . ' caratteri):');
            $io->block($description, null, 'fg=cyan', ' ', 4);
            
            // Analisi struttura
            $io->section('5. Analisi struttura');
            $hasPosition = str_contains($description, 'Posizione iniziale');
            $hasExecution = str_contains($description, 'Esecuzione passo passo') || str_contains($description, 'Esecuzione');
            $hasBreathing = str_contains($description, 'Respirazione');
            $hasErrors = str_contains($description, 'Errori comuni');
            $hasMuscles = str_contains($description, 'Muscoli coinvolti');
            
            $io->table(
                ['Sezione', 'Presente'],
                [
                    ['Posizione iniziale', $hasPosition ? '✅' : '❌'],
                    ['Esecuzione passo passo', $hasExecution ? '✅' : '❌'],
                    ['Respirazione', $hasBreathing ? '✅' : '❌'],
                    ['Errori comuni', $hasErrors ? '✅' : '❌'],
                    ['Muscoli coinvolti', $hasMuscles ? '✅' : '❌'],
                ]
            );
            
            if (!$isFallback) {
                return Command::SUCCESS;
            } else {
                $io->error('Test fallito: API non risponde correttamente');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $io->error('Errore durante il test: ' . $e->getMessage());
            $io->note('Stack trace:');
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}


