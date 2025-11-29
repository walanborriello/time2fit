<?php

namespace App\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:openai',
    description: 'Testa direttamente la connessione OpenAI',
)]
class TestOpenAICommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Test OpenAI API');
        
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        
        if (!$apiKey) {
            $io->error('OPENAI_API_KEY non configurata!');
            return Command::FAILURE;
        }
        
        $io->info('API Key: ' . substr($apiKey, 0, 15) . '...');
        
        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
        ]);
        
        $io->section('Test chiamata API');
        $io->note('Invio richiesta a OpenAI...');
        
        try {
            $response = $client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Ciao, rispondi solo con "OK" se funziona.',
                        ],
                    ],
                    'max_tokens' => 10,
                ],
            ]);
            
            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($statusCode === 200) {
                $io->success('✅ OpenAI funziona correttamente!');
                $io->text('Risposta: ' . ($data['choices'][0]['message']['content'] ?? 'N/A'));
                return Command::SUCCESS;
            } else {
                $io->error("Errore HTTP: {$statusCode}");
                $io->text('Risposta: ' . json_encode($data, JSON_PRETTY_PRINT));
                return Command::FAILURE;
            }
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 0;
            $responseBody = $response ? $response->getBody()->getContents() : '';
            $errorData = json_decode($responseBody, true);
            
            $io->error("Errore OpenAI API (HTTP {$statusCode})");
            
            if (isset($errorData['error'])) {
                $errorCode = $errorData['error']['code'] ?? 'unknown';
                $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
                
                $io->text("Codice errore: {$errorCode}");
                $io->text("Messaggio: {$errorMessage}");
                
                if ($errorCode === 'insufficient_quota' || str_contains($errorMessage, 'quota')) {
                    $io->warning('⚠️  Quota OpenAI esaurita!');
                    $io->note('Vai su https://platform.openai.com/account/billing per ricaricare il credito.');
                } elseif ($errorCode === 'invalid_api_key') {
                    $io->warning('⚠️  API Key non valida!');
                    $io->note('Verifica che la chiave API sia corretta.');
                } else {
                    $io->warning("⚠️  Errore: {$errorCode}");
                }
            } else {
                $io->text('Risposta: ' . substr($responseBody, 0, 500));
            }
            
            return Command::FAILURE;
            
        } catch (\Exception $e) {
            $io->error('Errore: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

