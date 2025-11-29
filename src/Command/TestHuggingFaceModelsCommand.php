<?php

namespace App\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:huggingface-models',
    description: 'Test diversi modelli HuggingFace per trovare uno che funziona',
)]
class TestHuggingFaceModelsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Test modelli HuggingFace');
        
        $apiKey = $_ENV['HUGGINGFACE_API_KEY'] ?? null;
        $testPrompt = "Genera una breve descrizione dell'esercizio: Panca piana con bilanciere";
        
        // Lista di modelli da testare
        $modelsToTest = [
            'distilgpt2',
            'gpt2',
            'microsoft/DialoGPT-small',
            'microsoft/DialoGPT-medium',
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }
        
        $io->section('Testando modelli...');
        
        $workingModels = [];
        
        foreach ($modelsToTest as $model) {
            $io->text("Testando: <info>{$model}</info>");
            
            // Prova entrambi gli endpoint
            $endpointsToTry = [
                ['base' => 'https://api-inference.huggingface.co/', 'paths' => ['models/' . $model]],
                ['base' => 'https://router.huggingface.co/', 'paths' => [
                    'hf-inference/models/' . $model,
                    'models/' . $model,
                ]],
            ];
            
            $success = false;
            $lastError = null;
            
            foreach ($endpointsToTry as $endpointConfig) {
                $client = new Client([
                    'base_uri' => $endpointConfig['base'],
                    'timeout' => 30,
                ]);
                
                foreach ($endpointConfig['paths'] as $path) {
                    try {
                        $response = $client->post($path, [
                            'headers' => $headers,
                            'json' => [
                                'inputs' => $testPrompt,
                                'parameters' => [
                                    'max_new_tokens' => 50,
                                    'temperature' => 0.7,
                                    'return_full_text' => false,
                                ],
                            ],
                        ]);
                        
                        if ($response->getStatusCode() === 200) {
                            $data = json_decode($response->getBody()->getContents(), true);
                            $io->success("✅ {$model} funziona con endpoint: {$endpointConfig['base']} e path: {$path}");
                            $workingModels[] = [
                                'model' => $model, 
                                'base_uri' => $endpointConfig['base'],
                                'path' => $path
                            ];
                            $success = true;
                            break 2; // Esce da entrambi i loop
                        }
                    } catch (\Exception $e) {
                        $lastError = $e->getMessage();
                        // Se è 410 Gone, salta questo endpoint
                        if (str_contains($e->getMessage(), '410')) {
                            break; // Passa al prossimo endpoint
                        }
                        continue;
                    }
                }
            }
            
            if (!$success) {
                $io->error("❌ {$model} non funziona: " . substr($lastError, 0, 100));
            }
        }
        
        $io->section('Risultati');
        
        if (empty($workingModels)) {
            $io->error('Nessun modello funziona!');
            $io->note('L\'API Inference di HuggingFace è cambiata. Prova a:');
            $io->listing([
                'Verificare la chiave API HuggingFace',
                'Usare OpenAI come alternativa principale',
                'Controllare la documentazione HuggingFace per modelli disponibili',
            ]);
            return Command::FAILURE;
        }
        
        $io->success('Modelli funzionanti trovati:');
        foreach ($workingModels as $wm) {
            $io->text("  - {$wm['model']} (endpoint: {$wm['base_uri']}, path: {$wm['path']})");
        }
        
        $io->note('Aggiorna il modello in HuggingFaceProvider.php con uno di questi.');
        
        return Command::SUCCESS;
    }
}
