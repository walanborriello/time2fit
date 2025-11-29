<?php

namespace App\Service;

use App\Entity\Exercise;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Provider per OpenAI API
 */
class OpenAIProvider implements AiProviderInterface
{
    private Client $client;
    private ?string $apiKey;

    public function __construct(
        ?string $apiKey,
        private ?LoggerInterface $logger = null
    ) {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 300, // 5 minuti per gestire i retry
        ]);
        
        if ($this->apiKey) {
            $this->logger?->debug('OpenAIProvider initialized with API key: ' . substr($this->apiKey, 0, 15) . '...');
        } else {
            $this->logger?->warning('OpenAIProvider initialized WITHOUT API key');
        }
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'OpenAI';
    }

    public function generateDescription(Exercise $exercise, string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('OpenAI API key non configurata');
        }

        $this->logger?->info('Generating description with OpenAI');
        
        $rateLimiter = AiRateLimiter::getInstance($this->logger);
        $rateLimiter->waitIfNeeded();
        
        $maxRetries = 3;
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    $rateLimiter->waitIfNeeded();
                }
                
                $response = $this->client->post('chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                        'max_tokens' => 1500,
                        'temperature' => 0.7,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $responseBody = $response->getBody()->getContents();
                $data = json_decode($responseBody, true);
                
                if ($statusCode === 429) {
                    if ($attempt < $maxRetries - 1) {
                        $retryAfter = null;
                        if ($response->hasHeader('Retry-After')) {
                            $retryAfter = (int) $response->getHeaderLine('Retry-After');
                        }
                        
                        $delay = $retryAfter ?? [30, 60, 120][min($attempt, 2)];
                        $delay = max($delay, 30);
                        $delay = min($delay, 300);
                        
                        $this->logger?->warning("Rate limit 429, waiting {$delay}s...");
                        $rateLimiter->handle429Error($delay);
                        $rateLimiter->waitIfNeeded();
                        continue;
                    }
                }
                
                if (isset($data['error'])) {
                    $errorCode = $data['error']['code'] ?? null;
                    $errorMessage = $data['error']['message'] ?? 'Unknown error';
                    
                    if ($errorCode === 'insufficient_quota' || str_contains($errorMessage, 'quota')) {
                        throw new \RuntimeException('Quota OpenAI esaurita. Controlla il tuo piano su https://platform.openai.com/account/billing');
                    }
                    
                    if ($errorCode === 'rate_limit_exceeded' && $attempt < $maxRetries - 1) {
                        $delay = [30, 60, 120][min($attempt, 2)];
                        $this->logger?->warning("Rate limit in error body, waiting {$delay}s...");
                        $rateLimiter->handle429Error($delay);
                        $rateLimiter->waitIfNeeded();
                        continue;
                    }
                    
                    throw new \RuntimeException('OpenAI API error: ' . $errorMessage);
                }
                
                if ($statusCode !== 200) {
                    throw new \RuntimeException("OpenAI API returned HTTP {$statusCode}");
                }
                
                $generatedDescription = $data['choices'][0]['message']['content'] ?? null;
                
                if ($generatedDescription) {
                    $this->logger?->info('OpenAI description generated successfully');
                    return trim($generatedDescription);
                }
                
                throw new \RuntimeException('OpenAI API ha restituito una risposta vuota');
                
            } catch (GuzzleException $e) {
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                $responseBody = '';
                $errorCode = null;
                
                if ($response) {
                    try {
                        $responseBody = $response->getBody()->getContents();
                        $errorData = json_decode($responseBody, true);
                        if (isset($errorData['error'])) {
                            $errorCode = $errorData['error']['code'] ?? null;
                        }
                    } catch (\Exception $bodyEx) {
                        // Ignora
                    }
                }
                
                if ($errorCode === 'insufficient_quota' || str_contains($e->getMessage(), 'quota')) {
                    throw new \RuntimeException('Quota OpenAI esaurita. Controlla il tuo piano su https://platform.openai.com/account/billing');
                }
                
                if ($statusCode === 429 && $attempt < $maxRetries - 1) {
                    $retryAfter = null;
                    if ($response && $response->hasHeader('Retry-After')) {
                        $retryAfter = (int) $response->getHeaderLine('Retry-After');
                    }
                    
                    $delay = $retryAfter ?? [30, 60, 120][min($attempt, 2)];
                    $delay = max($delay, 30);
                    $delay = min($delay, 300);
                    
                    $this->logger?->warning("Rate limit 429 in exception, waiting {$delay}s...");
                    $rateLimiter->handle429Error($delay);
                    $rateLimiter->waitIfNeeded();
                    continue;
                }
                
                if ($attempt === $maxRetries - 1) {
                    throw new \RuntimeException('Errore OpenAI API dopo ' . $maxRetries . ' tentativi: ' . $e->getMessage());
                }
            }
        }
        
        throw new \RuntimeException('Impossibile generare la descrizione con OpenAI dopo ' . $maxRetries . ' tentativi');
    }
}


