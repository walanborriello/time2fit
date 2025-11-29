<?php

namespace App\Service;

use App\Entity\Exercise;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Provider per Hugging Face Inference API (gratuito)
 * 
 * Modelli disponibili gratuitamente:
 * - meta-llama/Llama-3.1-8B-Instruct (ottimo, veloce)
 * - mistralai/Mistral-7B-Instruct-v0.2 (ottimo)
 * - google/gemma-7b-it (italiano)
 * 
 * API gratuita: https://huggingface.co/inference-api
 * Endpoint: https://router.huggingface.co/hf-inference/models/ (nuovo formato)
 * Il vecchio api-inference.huggingface.co è deprecato (410 Gone)
 * Limiti: ~1000 richieste/giorno senza API key, illimitate con API key gratuita
 */
class HuggingFaceProvider implements AiProviderInterface
{
    private Client $client;
    private ?string $apiKey;
    private string $model;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        private ?LoggerInterface $logger = null
    ) {
        $this->apiKey = $apiKey;
        // NOTA CRITICA: L'API Inference di HuggingFace è cambiata drasticamente
        // - api-inference.huggingface.co è deprecato (410 Gone)
        // - router.huggingface.co supporta solo pochi modelli e molti restituiscono embedding invece di testo
        // - I modelli di generazione di testo (GPT-2, T5, ecc.) non sono più disponibili gratuitamente
        // SOLUZIONE: Usa OpenAI come primario. HuggingFace come fallback non è affidabile al momento.
        // Se vuoi usare HuggingFace, devi verificare manualmente quali modelli generano testo
        // e configurarli tramite variabile d'ambiente HUGGINGFACE_MODEL
        $this->model = $model ?? $_ENV['HUGGINGFACE_MODEL'] ?? 'distilgpt2'; // Default, probabilmente non funzionerà
        
        // Usa direttamente router.huggingface.co (api-inference è deprecato)
        // Il modello facebook/bart-large funziona con questo endpoint
        $this->client = new Client([
            'base_uri' => 'https://router.huggingface.co/',
            'timeout' => 120, // 2 minuti (i modelli gratuiti possono essere più lenti)
        ]);
        
        if ($this->apiKey) {
            $this->logger?->debug('HuggingFaceProvider initialized with API key: ' . substr($this->apiKey, 0, 10) . '...');
        } else {
            $this->logger?->info('HuggingFaceProvider initialized WITHOUT API key (using free tier)');
        }
    }

    public function isAvailable(): bool
    {
        // Nota: L'API Inference gratuita di HuggingFace è stata deprecata
        // Il nuovo endpoint router.huggingface.co potrebbe non supportare tutti i modelli
        // Restituiamo true se abbiamo API key, altrimenti false (molti modelli richiedono API key)
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'Hugging Face';
    }

    /**
     * Formatta il prompt per modelli instruct (Llama, Mistral, ecc.)
     * Usa il formato chat template appropriato per il modello
     */
    private function formatPromptForModel(string $prompt): string
    {
        // Per Llama 3.1 Instruct - formato corretto per l'API Inference
        if (str_contains($this->model, 'llama') && str_contains($this->model, 'instruct')) {
            // Llama 3.1 usa questo formato specifico
            return "<|begin_of_text|><|start_header_id|>user<|end_header_id|>\n\n{$prompt}<|eot_id|><|start_header_id|>assistant<|end_header_id|>\n\n";
        }
        
        // Per Mistral Instruct
        if (str_contains($this->model, 'mistral') && str_contains($this->model, 'instruct')) {
            return "<s>[INST] {$prompt} [/INST]";
        }
        
        // Per Gemma (italiano)
        if (str_contains($this->model, 'gemma')) {
            return "<start_of_turn>user\n{$prompt}<end_of_turn>\n<start_of_turn>model\n";
        }
        
        // Per DialoGPT e modelli conversazionali
        if (str_contains($this->model, 'dialo') || str_contains($this->model, 'gpt')) {
            // DialoGPT e GPT2 funzionano meglio con prompt semplice
            return $prompt;
        }
        
        // Per altri modelli, usa il prompt così com'è
        return $prompt;
    }

    public function generateDescription(Exercise $exercise, string $prompt): string
    {
        $this->logger?->info("Generating description with Hugging Face (model: {$this->model})");
        
        // Prova prima con il formato template, poi senza se fallisce
        try {
            return $this->tryGenerateWithTemplate($prompt);
        } catch (\Exception $e) {
            $this->logger?->warning("Template format failed, trying without template: " . $e->getMessage());
            return $this->tryGenerateWithoutTemplate($prompt);
        }
    }
    
    private function tryGenerateWithTemplate(string $prompt): string
    {
        // Formatta il prompt per il modello specifico
        $formattedPrompt = $this->formatPromptForModel($prompt);
        
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        // Aggiungi API key se disponibile (aumenta i limiti)
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        
        $maxRetries = 3;
        $baseDelay = 5; // secondi
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    $delay = $baseDelay * pow(2, $attempt); // 5s, 10s, 20s
                    $this->logger?->info("Retry attempt " . ($attempt + 1) . "/{$maxRetries} - waiting {$delay}s...");
                    sleep($delay);
                }
                
                // Usa router.huggingface.co direttamente
                $pathsToTry = [
                    'hf-inference/models/' . $this->model,  // Path principale per router.huggingface.co
                    'models/' . $this->model,                // Fallback
                ];
                
                $lastException = null;
                $response = null;
                
                foreach ($pathsToTry as $path) {
                    try {
                        $this->logger?->debug("Trying path: {$path}");
                        $response = $this->client->post($path, [
                            'headers' => $headers,
                            'json' => [
                                'inputs' => $formattedPrompt,
                                'parameters' => [
                                    'max_new_tokens' => 1000,
                                    'temperature' => 0.7,
                                    'return_full_text' => false,
                                ],
                            ],
                        ]);
                        // Se arriviamo qui, la richiesta è andata a buon fine
                        $this->logger?->info("Success with path: {$path}");
                        break;
                    } catch (GuzzleException $ex) {
                        $lastException = $ex;
                        $exResponse = $ex->getResponse();
                        $statusCode = $exResponse ? $exResponse->getStatusCode() : 0;
                        
                        if ($statusCode === 404) {
                            $this->logger?->warning("Path {$path} returned 404, trying next...");
                            // Prova il prossimo path
                            continue;
                        }
                        // Se non è un 404, rilanciamo l'eccezione
                        throw $ex;
                    }
                }
                
                // Se tutte le varianti hanno dato 404 o 410, prova router.huggingface.co
                if (!$response && !$useRouter) {
                    $this->logger?->info('api-inference.huggingface.co fallito, provo router.huggingface.co');
                    $useRouter = true;
                    $this->client = new Client([
                        'base_uri' => 'https://router.huggingface.co/',
                        'timeout' => 120,
                    ]);
                    $pathsToTry = [
                        'hf-inference/models/' . $this->model,
                        'models/' . $this->model,
                    ];
                    continue; // Riprova con router
                }
                
                if (!$response) {
                    $this->logger?->error('All path variants returned 404. The model might not be available on the free Inference API.');
                    throw $lastException ?? new \RuntimeException('Tutti i path provati hanno restituito 404. L\'API Inference gratuita potrebbe non essere più disponibile per questo modello.');
                }

                $statusCode = $response->getStatusCode();
                $responseBody = $response->getBody()->getContents();
                $data = json_decode($responseBody, true);
                
                $this->logger?->info("Hugging Face response: HTTP {$statusCode}");
                $this->logger?->debug("Hugging Face response body (first 500 chars): " . substr($responseBody, 0, 500));
                
                // Gestisci errori nella risposta
                if (isset($data['error'])) {
                    $errorMsg = $data['error'];
                    $this->logger?->error('Hugging Face API error in response: ' . $errorMsg);
                    
                    // Se il modello sta caricando
                    if (str_contains(strtolower($errorMsg), 'loading') && $attempt < $maxRetries - 1) {
                        $estimatedTime = $data['estimated_time'] ?? 30;
                        $this->logger?->info("Model is loading, estimated time: {$estimatedTime}s");
                        sleep(min($estimatedTime, 60));
                        continue;
                    }
                    
                    throw new \RuntimeException('Hugging Face API error: ' . $errorMsg);
                }
                
                // Hugging Face può restituire un array con il testo generato
                if ($statusCode === 200) {
                    // La risposta può essere in diversi formati
                    $generatedText = null;
                    
                    // Se la risposta è un array di numeri (embedding), il modello non genera testo
                    if (isset($data[0]) && is_array($data[0]) && isset($data[0][0]) && is_numeric($data[0][0])) {
                        $this->logger?->warning('Il modello restituisce embedding invece di testo. Questo modello non è adatto per la generazione di testo.');
                        throw new \RuntimeException('Il modello selezionato non genera testo ma embedding. Usa un modello di generazione di testo.');
                    }
                    
                    if (isset($data[0]['generated_text'])) {
                        $generatedText = $data[0]['generated_text'];
                    } elseif (isset($data['generated_text'])) {
                        $generatedText = $data['generated_text'];
                    } elseif (is_string($data)) {
                        $generatedText = $data;
                    } elseif (isset($data[0]) && is_string($data[0])) {
                        $generatedText = $data[0];
                    }
                    
                    if ($generatedText) {
                        $this->logger?->debug("Raw generated text (first 200 chars): " . substr($generatedText, 0, 200));
                        
                        // Rimuovi il prompt formattato se presente (controlla sia la versione formattata che quella originale)
                        $generatedText = str_replace($formattedPrompt, '', $generatedText);
                        $generatedText = str_replace($prompt, '', $generatedText);
                        
                        // Rimuovi token speciali residui
                        $generatedText = preg_replace('/<\|begin_of_text\|>/', '', $generatedText);
                        $generatedText = preg_replace('/<\|start_header_id\|>.*?<\|end_header_id\|>/', '', $generatedText);
                        $generatedText = preg_replace('/<\|eot_id\|>/', '', $generatedText);
                        $generatedText = preg_replace('/\[INST\].*?\[\/INST\]/', '', $generatedText);
                        $generatedText = preg_replace('/<start_of_turn>.*?<end_of_turn>/s', '', $generatedText);
                        $generatedText = preg_replace('/<s>|<\/s>/', '', $generatedText);
                        
                        $generatedText = trim($generatedText);
                        
                        if (!empty($generatedText)) {
                            $this->logger?->info('Hugging Face description generated successfully (' . strlen($generatedText) . ' characters)');
                            return $generatedText;
                        } else {
                            $this->logger?->warning('Hugging Face returned empty text after cleaning');
                        }
                    } else {
                        $this->logger?->warning('Hugging Face response did not contain generated_text. Response structure: ' . json_encode(array_keys($data ?? [])));
                    }
                }
                
                // Se il modello sta caricando, aspetta e riprova
                if ($statusCode === 503 && isset($data['error']) && str_contains($data['error'], 'loading')) {
                    $estimatedTime = $data['estimated_time'] ?? 30;
                    $this->logger?->info("Model is loading, estimated time: {$estimatedTime}s");
                    if ($attempt < $maxRetries - 1) {
                        sleep(min($estimatedTime, 60)); // Max 60 secondi
                        continue;
                    }
                }
                
                $this->logger?->error('Hugging Face API returned unexpected response: ' . substr($responseBody, 0, 500));
                throw new \RuntimeException('Hugging Face API ha restituito una risposta inattesa');
                
            } catch (GuzzleException $e) {
                $response = $e->getResponse();
                $statusCode = $response ? $response->getStatusCode() : 0;
                $responseBody = $response ? $response->getBody()->getContents() : '';
                
                $this->logger?->error('Hugging Face API error: ' . $e->getMessage());
                $this->logger?->error('Status code: ' . $statusCode);
                
                // Se è un errore 410 (Gone - endpoint deprecato), lancia un errore chiaro
                if ($statusCode === 410) {
                    $this->logger?->error('HuggingFace API endpoint deprecato. L\'endpoint api-inference.huggingface.co non è più supportato.');
                    $this->logger?->error('HuggingFace suggerisce di usare router.huggingface.co, ma questo endpoint potrebbe non supportare tutti i modelli.');
                    throw new \RuntimeException('HuggingFace API endpoint deprecato. L\'API Inference gratuita potrebbe non essere più disponibile per questo modello. Prova a: 1) Usare un modello diverso, 2) Verificare la documentazione HuggingFace per il nuovo formato, 3) Usare OpenAI come alternativa.');
                }
                
                // Se è un errore 503 (model loading) e abbiamo ancora tentativi, riprova
                if ($statusCode === 503 && $attempt < $maxRetries - 1) {
                    $data = json_decode($responseBody, true);
                    $estimatedTime = $data['estimated_time'] ?? 30;
                    $this->logger?->info("Model loading, waiting {$estimatedTime}s...");
                    sleep(min($estimatedTime, 60));
                    continue;
                }
                
                // Se è un errore di rate limit, aspetta e riprova
                if ($statusCode === 429 && $attempt < $maxRetries - 1) {
                    $delay = $baseDelay * pow(2, $attempt);
                    $this->logger?->warning("Rate limit 429, waiting {$delay}s...");
                    sleep($delay);
                    continue;
                }
                
                // Altri errori
                if ($attempt === $maxRetries - 1) {
                    throw new \RuntimeException('Errore Hugging Face API dopo ' . $maxRetries . ' tentativi: ' . $e->getMessage());
                }
            }
        }
        
        throw new \RuntimeException('Impossibile generare la descrizione con Hugging Face dopo ' . $maxRetries . ' tentativi');
    }
    
    private function tryGenerateWithoutTemplate(string $prompt): string
    {
        // Prova senza template (formato semplice)
        $this->logger?->info("Trying Hugging Face without template format");
        
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }
        
        $maxRetries = 2;
        $baseDelay = 5;
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    $delay = $baseDelay * pow(2, $attempt);
                    $this->logger?->info("Retry attempt " . ($attempt + 1) . "/{$maxRetries} - waiting {$delay}s...");
                    sleep($delay);
                }
                
                $response = $this->client->post($this->model, [
                    'headers' => $headers,
                    'json' => [
                        'inputs' => $prompt,
                        'parameters' => [
                            'max_new_tokens' => 1000,
                            'temperature' => 0.7,
                            'return_full_text' => false,
                        ],
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $responseBody = $response->getBody()->getContents();
                $data = json_decode($responseBody, true);
                
                if ($statusCode === 200 && isset($data[0]['generated_text'])) {
                    $generatedText = trim($data[0]['generated_text']);
                    // Rimuovi il prompt se presente
                    $generatedText = str_replace($prompt, '', $generatedText);
                    $generatedText = trim($generatedText);
                    
                    if (!empty($generatedText)) {
                        $this->logger?->info('Hugging Face description generated (without template)');
                        return $generatedText;
                    }
                }
                
                if (isset($data['error'])) {
                    throw new \RuntimeException('Hugging Face API error: ' . $data['error']);
                }
                
            } catch (GuzzleException $e) {
                if ($attempt === $maxRetries - 1) {
                    throw new \RuntimeException('Errore Hugging Face API: ' . $e->getMessage());
                }
            }
        }
        
        throw new \RuntimeException('Impossibile generare la descrizione con Hugging Face (senza template)');
    }
}

