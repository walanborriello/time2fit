<?php

namespace App\Command;

use App\Entity\Exercise;
use App\Service\AiDescriptionService;
use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:all-ai-models',
    description: 'Testa tutti i modelli AI disponibili per trovare quelli funzionanti',
)]
class TestAllAiModelsCommand extends Command
{
    public function __construct(
        private AiDescriptionService $aiDescriptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Test Completo Provider AI');
        
        // 1. Test OpenAI
        $io->section('1. Test OpenAI');
        $openAiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if ($openAiKey) {
            $io->info('API Key configurata: ' . substr($openAiKey, 0, 15) . '...');
            $this->testOpenAI($io, $openAiKey);
        } else {
            $io->warning('OPENAI_API_KEY non configurata');
        }
        
        // 2. Test HuggingFace con diversi modelli
        $io->section('2. Test HuggingFace - Modelli disponibili');
        $huggingFaceKey = $_ENV['HUGGINGFACE_API_KEY'] ?? null;
        if ($huggingFaceKey) {
            $io->info('API Key configurata: ' . substr($huggingFaceKey, 0, 15) . '...');
            $this->testHuggingFaceModels($io, $huggingFaceKey);
        } else {
            $io->warning('HUGGINGFACE_API_KEY non configurata');
        }
        
        // 3. Test completo con AiDescriptionService
        $io->section('3. Test completo con AiDescriptionService');
        $testExercise = new Exercise();
        $testExercise->setName('Panca piana con bilanciere');
        $testExercise->setMuscleGroup('Pettorali');
        
        try {
            $io->note('Generazione descrizione in corso...');
            $startTime = microtime(true);
            $description = $this->aiDescriptionService->generateExerciseDescription($testExercise);
            $elapsedTime = round(microtime(true) - $startTime, 2);
            
            $isFallback = str_contains($description, 'Assumi la posizione corretta') || 
                         str_contains($description, 'Prepara l\'attrezzatura');
            
            if ($isFallback) {
                $io->error('⚠️  Descrizione FALLBACK generica');
            } else {
                $io->success("✅ Descrizione generata in {$elapsedTime}s!");
                $io->text('Primi 200 caratteri: ' . substr($description, 0, 200) . '...');
            }
        } catch (\Exception $e) {
            $io->error('Errore: ' . $e->getMessage());
        }
        
        return Command::SUCCESS;
    }
    
    private function testOpenAI(SymfonyStyle $io, string $apiKey): void
    {
        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
        ]);
        
        try {
            $response = $client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Test'],
                    ],
                    'max_tokens' => 10,
                ],
            ]);
            
            if ($response->getStatusCode() === 200) {
                $io->success('✅ OpenAI funziona correttamente!');
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $errorData = json_decode($response->getBody()->getContents(), true);
            $errorCode = $errorData['error']['code'] ?? 'unknown';
            
            if ($errorCode === 'insufficient_quota') {
                $io->error('❌ OpenAI: Quota esaurita');
            } else {
                $io->error("❌ OpenAI: {$errorCode}");
            }
        } catch (\Exception $e) {
            $io->error('❌ OpenAI: ' . $e->getMessage());
        }
    }
    
    private function testHuggingFaceModels(SymfonyStyle $io, string $apiKey): void
    {
        $modelsToTest = [
            'gpt2',
            'distilgpt2',
            'microsoft/DialoGPT-small',
            'microsoft/DialoGPT-medium',
            'google/flan-t5-small',
            'google/flan-t5-base',
            't5-small',
            't5-base',
            'EleutherAI/gpt-neo-125M',
            'EleutherAI/gpt-neo-1.3B',
            'bigscience/bloom-560m',
            'bigscience/bloom-1b1',
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ];
        
        $workingModels = [];
        
        foreach ($modelsToTest as $model) {
            $io->text("Testando: <info>{$model}</info>");
            
            // Prova router.huggingface.co
            $client = new Client([
                'base_uri' => 'https://router.huggingface.co/',
                'timeout' => 30,
            ]);
            
            $pathsToTry = [
                'hf-inference/models/' . $model,
                'models/' . $model,
            ];
            
            $success = false;
            foreach ($pathsToTry as $path) {
                try {
                    $response = $client->post($path, [
                        'headers' => $headers,
                        'json' => [
                            'inputs' => 'Test prompt',
                            'parameters' => [
                                'max_new_tokens' => 10,
                                'return_full_text' => false,
                            ],
                        ],
                    ]);
                    
                    if ($response->getStatusCode() === 200) {
                        $responseBody = $response->getBody()->getContents();
                        $data = json_decode($responseBody, true);
                        
                        // Verifica se restituisce testo o embedding
                        $isText = false;
                        if (isset($data[0]['generated_text']) || isset($data['generated_text'])) {
                            $isText = true;
                        } elseif (is_string($data) || (isset($data[0]) && is_string($data[0]))) {
                            $isText = true;
                        } elseif (isset($data[0]) && is_array($data[0]) && isset($data[0][0]) && is_numeric($data[0][0])) {
                            $isText = false; // È un embedding
                        }
                        
                        if ($isText) {
                            $io->success("  ✅ {$model} funziona con path: {$path} (genera testo)");
                            $workingModels[] = ['model' => $model, 'path' => $path, 'type' => 'text'];
                        } else {
                            $io->text("  ⚠️  {$model} funziona ma restituisce embedding, non testo");
                        }
                        $success = true;
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            if (!$success) {
                $io->text("  ❌ {$model} non funziona");
            }
        }
        
        if (!empty($workingModels)) {
            $io->success('Modelli funzionanti trovati:');
            foreach ($workingModels as $wm) {
                $io->text("  - {$wm['model']}");
            }
        } else {
            $io->warning('Nessun modello HuggingFace funziona con router.huggingface.co');
        }
    }
}

