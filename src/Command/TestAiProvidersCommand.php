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
    name: 'app:test:ai-providers',
    description: 'Testa entrambi i provider AI (OpenAI e HuggingFace)',
)]
class TestAiProvidersCommand extends Command
{
    public function __construct(
        private AiDescriptionService $aiDescriptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Test Provider AI');
        
        // Verifica configurazione
        $io->section('1. Configurazione');
        
        $openAiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        $huggingFaceKey = $_ENV['HUGGINGFACE_API_KEY'] ?? null;
        $aiProvider = $_ENV['AI_PROVIDER'] ?? 'auto';
        
        $io->table(
            ['Parametro', 'Valore'],
            [
                ['AI_PROVIDER', $aiProvider],
                ['OPENAI_API_KEY', $openAiKey ? substr($openAiKey, 0, 15) . '...' : 'NON CONFIGURATA'],
                ['HUGGINGFACE_API_KEY', $huggingFaceKey ? substr($huggingFaceKey, 0, 15) . '...' : 'NON CONFIGURATA'],
            ]
        );
        
        // Crea esercizio di test
        $io->section('2. Test generazione descrizione');
        $testExercise = new Exercise();
        $testExercise->setName('Panca piana con bilanciere');
        $testExercise->setMuscleGroup('Pettorali');
        
        $io->info("Esercizio: {$testExercise->getName()}");
        $io->info("Muscolo target: {$testExercise->getMuscleGroup()}");
        
        $io->note('Generazione descrizione in corso...');
        
        try {
            $startTime = microtime(true);
            $description = $this->aiDescriptionService->generateExerciseDescription($testExercise);
            $elapsedTime = round(microtime(true) - $startTime, 2);
            
            // Verifica se è una descrizione fallback
            $isFallback = str_contains($description, 'Assumi la posizione corretta') || 
                         str_contains($description, 'Prepara l\'attrezzatura');
            
            if ($isFallback) {
                $io->error('⚠️  La risposta è una descrizione FALLBACK generica!');
                $io->warning('Nessun provider AI ha funzionato correttamente.');
            } else {
                $io->success("✅ Descrizione generata con successo in {$elapsedTime}s!");
            }
            
            $io->section('3. Risultato');
            $io->text('Descrizione generata (' . strlen($description) . ' caratteri):');
            $io->block($description, null, 'fg=cyan', ' ', 4);
            
            // Analisi struttura
            $io->section('4. Analisi struttura');
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
                $io->success('Test completato con successo!');
                return Command::SUCCESS;
            } else {
                $io->error('Test fallito: nessun provider AI funziona');
                $io->note('Suggerimenti:');
                $io->listing([
                    'Verifica che OPENAI_API_KEY sia configurata correttamente',
                    'Verifica che HUGGINGFACE_API_KEY sia configurata correttamente',
                    'Controlla i log in var/log/ per dettagli sugli errori',
                    'Se OpenAI ha quota esaurita, ricarica il credito',
                ]);
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

