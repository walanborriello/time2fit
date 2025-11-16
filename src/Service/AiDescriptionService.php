<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class AiDescriptionService
{
    private Client $client;
    private ?string $apiKey;

    public function __construct(?string $apiKey, private ?LoggerInterface $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
        ]);
    }

    public function generateDescription(string $exerciseName, ?string $muscleGroup = null): ?string
    {
        if (empty($this->apiKey)) {
            $this->logger?->warning('OpenAI API key not configured, skipping AI description generation');
            return null;
        }

        $prompt = "Genera una descrizione tecnica e professionale per l'esercizio: {$exerciseName}";
        if ($muscleGroup) {
            $prompt .= " (gruppo muscolare: {$muscleGroup})";
        }
        $prompt .= ". La descrizione deve essere in italiano, chiara e concisa (max 200 parole).";

        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.7,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['choices'][0]['message']['content'] ?? null;
        } catch (GuzzleException $e) {
            $this->logger?->error('OpenAI API error: ' . $e->getMessage());
            return null;
        }
    }

    public function generateMediaPrompt(string $exerciseName, ?string $muscleGroup = null): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $prompt = "Crea una GIF animata che mostri come eseguire correttamente l'esercizio: {$exerciseName}";
        if ($muscleGroup) {
            $prompt .= " (gruppo muscolare: {$muscleGroup})";
        }

        try {
            $response = $this->client->post('images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '256x256',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'][0]['url'] ?? null;
        } catch (GuzzleException $e) {
            $this->logger?->error('OpenAI image generation error: ' . $e->getMessage());
            return null;
        }
    }
}

