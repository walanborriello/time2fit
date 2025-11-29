<?php

namespace App\Service;

use App\Entity\Exercise;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class AiDescriptionService
{
    private Client $client;
    private ?string $apiKey;
    private ?AiProviderInterface $primaryProvider = null;
    private ?AiProviderInterface $fallbackProvider = null;

    public function __construct(
        ?string $openAiApiKey = null,
        ?string $huggingFaceApiKey = null,
        ?string $aiProvider = null,
        private ?LoggerInterface $logger = null
    ) {
        $this->apiKey = $openAiApiKey; // Mantenuto per retrocompatibilità
        
        // Determina quale provider usare
        $provider = $aiProvider ?? $_ENV['AI_PROVIDER'] ?? 'auto';
        
        // Crea i provider
        $openAiProvider = new OpenAIProvider($openAiApiKey, $this->logger);
        $huggingFaceProvider = new HuggingFaceProvider($huggingFaceApiKey, null, $this->logger);
        
        // Configura provider primario e fallback
        // IMPORTANTE: HuggingFace API è cambiata, molti modelli non funzionano più
        // Configurazione consigliata: OpenAI come primario, HuggingFace come fallback
        if ($provider === 'openai' && $openAiProvider->isAvailable()) {
            $this->primaryProvider = $openAiProvider;
            $this->fallbackProvider = $huggingFaceProvider;
        } elseif ($provider === 'huggingface') {
            // Se l'utente vuole HuggingFace come primario, fallback a OpenAI se disponibile
            $this->primaryProvider = $huggingFaceProvider;
            $this->fallbackProvider = $openAiProvider->isAvailable() ? $openAiProvider : null;
        } else {
            // Auto: usa OpenAI se disponibile (CONSIGLIATO), altrimenti Hugging Face
            if ($openAiProvider->isAvailable()) {
                $this->primaryProvider = $openAiProvider;
                $this->fallbackProvider = $huggingFaceProvider;
            } else {
                // Se OpenAI non è disponibile, prova HuggingFace
                $this->primaryProvider = $huggingFaceProvider;
                $this->fallbackProvider = null;
            }
        }
        
        // Mantenuto per retrocompatibilità
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 300,
        ]);
        
        $this->logger?->info('AiDescriptionService initialized with provider: ' . ($this->primaryProvider?->getName() ?? 'none'));
        if ($this->fallbackProvider) {
            $this->logger?->info('Fallback provider: ' . $this->fallbackProvider->getName());
        }
    }
    
    private function getRateLimiter(): AiRateLimiter
    {
        return AiRateLimiter::getInstance($this->logger);
    }

    /**
     * Genera una descrizione strutturata per un esercizio usando il template fisso dalla guideline.
     * 
     * @param Exercise $exercise L'entità Exercise con nome, muscolo target, ecc.
     * @param string|null $customPrompt Prompt personalizzato opzionale dall'utente
     * @param callable|null $onRetry Callback chiamato durante i retry: function(int $attempt, int $delay, string $reason)
     * @return string La descrizione generata con struttura fissa (5 sezioni)
     */
    public function generateExerciseDescription(Exercise $exercise, ?string $customPrompt = null, ?callable $onRetry = null): string
    {
        $exerciseName = $exercise->getName() ?? 'Esercizio senza nome';
        $muscleGroup = $exercise->getMuscleGroup() ?? 'non specificato';
        
        // Costruisci il prompt usando il template fisso dalla guideline
        $templatePrompt = "Genera la spiegazione dettagliata dell'esercizio di allenamento seguente:\n\n";
        $templatePrompt .= "Nome esercizio: {$exerciseName}\n";
        $templatePrompt .= "Muscoli target: {$muscleGroup}\n";
        $templatePrompt .= "Livello utente: principiante/intermedio/avanzato\n";
        
        if (!empty($customPrompt)) {
            $templatePrompt .= "Contesto aggiuntivo (opzionale):\n{$customPrompt}\n\n";
        } else {
            $templatePrompt .= "\n";
        }
        
        $templatePrompt .= "REQUISITI OBBLIGATORI:\n";
        $templatePrompt .= "- descrivi la posizione iniziale in modo dettagliato\n";
        $templatePrompt .= "- descrivi l'esecuzione passo passo\n";
        $templatePrompt .= "- indica la respirazione corretta\n";
        $templatePrompt .= "- elenca gli errori comuni da evitare\n";
        $templatePrompt .= "- elenca i muscoli coinvolti realmente\n";
        $templatePrompt .= "- NON usare frasi generiche tipo \"mantieni la postura adeguata\"\n";
        $templatePrompt .= "- NON dare consigli vaghi\n";
        $templatePrompt .= "- sii tecnico, accurato e pratico\n";
        $templatePrompt .= "- tono professionale ma chiaro\n\n";
        
        $templatePrompt .= "FORMATTAZIONE RICHIESTA (usa sempre questa struttura):\n\n";
        $templatePrompt .= "Posizione iniziale:\n";
        $templatePrompt .= "- ...\n\n";
        $templatePrompt .= "Esecuzione passo passo:\n";
        $templatePrompt .= "1. ...\n";
        $templatePrompt .= "2. ...\n";
        $templatePrompt .= "3. ...\n\n";
        $templatePrompt .= "Respirazione:\n";
        $templatePrompt .= "- ...\n\n";
        $templatePrompt .= "Errori comuni:\n";
        $templatePrompt .= "- ...\n\n";
        $templatePrompt .= "Muscoli coinvolti:\n";
        $templatePrompt .= "- ...\n";

        $this->logger?->info('Generating AI description for exercise: ' . $exerciseName);
        
        // Usa il sistema multi-provider
        if (!$this->primaryProvider) {
            $this->logger?->warning('No AI provider available, using fallback description');
            return $this->generateFallbackDescription($exerciseName, $muscleGroup);
        }
        
        // Prova prima con il provider primario
        try {
            $this->logger?->info('Trying primary provider: ' . $this->primaryProvider->getName());
            return $this->primaryProvider->generateDescription($exercise, $templatePrompt);
        } catch (\Exception $e) {
            $this->logger?->warning('Primary provider failed: ' . $e->getMessage());
            
            // Se c'è un fallback, provalo
            if ($this->fallbackProvider && $this->fallbackProvider->isAvailable()) {
                try {
                    $this->logger?->info('Trying fallback provider: ' . $this->fallbackProvider->getName());
                    return $this->fallbackProvider->generateDescription($exercise, $templatePrompt);
                } catch (\Exception $fallbackEx) {
                    $this->logger?->error('Fallback provider also failed: ' . $fallbackEx->getMessage());
                    // Continua con il fallback generico
                }
            }
            
            // Se entrambi falliscono, usa la descrizione di fallback
            $this->logger?->warning('All AI providers failed, using fallback description');
            return $this->generateFallbackDescription($exerciseName, $muscleGroup);
        }
        
    }

    /**
     * Genera una descrizione di fallback strutturata quando nessun AI provider è disponibile.
     */
    public function generateFallbackDescription(string $exerciseName, string $muscleGroup): string
    {
        $description = "Posizione iniziale:\n";
        $description .= "- Assumi la posizione corretta per eseguire {$exerciseName}.\n";
        $description .= "- Assicurati che l'attrezzatura sia posizionata correttamente.\n\n";
        
        $description .= "Esecuzione passo passo:\n";
        $description .= "1. Prepara l'attrezzatura e assumi la posizione iniziale.\n";
        $description .= "2. Esegui il movimento controllato seguendo la tecnica corretta.\n";
        $description .= "3. Completa la ripetizione e torna alla posizione iniziale.\n\n";
        
        $description .= "Respirazione:\n";
        $description .= "- Inspira durante la fase eccentrica (allungamento).\n";
        $description .= "- Espira durante la fase concentrica (contrazione).\n\n";
        
        $description .= "Errori comuni:\n";
        $description .= "- Evitare movimenti troppo rapidi o scomposti.\n";
        $description .= "- Non forzare il movimento oltre il range di movimento naturale.\n\n";
        
        $description .= "Muscoli coinvolti:\n";
        $description .= "- {$muscleGroup}\n";
        
        return $description;
    }

    /**
     * @deprecated Usa generateExerciseDescription() invece
     */
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

    /**
     * Genera o recupera una GIF per un esercizio.
     * Cerca prima su Giphy, poi come fallback genera un prompt per DALL-E.
     * 
     * @param Exercise $exercise L'entità Exercise
     * @return string|null URL della GIF o null se non disponibile
     */
    public function generateExerciseGif(Exercise $exercise): ?string
    {
        $exerciseName = $exercise->getName() ?? 'exercise';
        $muscleGroup = $exercise->getMuscleGroup();
        
        // Prova prima a cercare una GIF su Giphy (API gratuita)
        $gifUrl = $this->searchGiphyGif($exerciseName, $muscleGroup);
        if ($gifUrl) {
            return $gifUrl;
        }
        
        // Se Giphy non ha risultati, prova a generare un'immagine con DALL-E
        // (nota: DALL-E genera immagini statiche, non GIF animate)
        if (!empty($this->apiKey)) {
            $imageUrl = $this->generateExerciseImage($exercise);
            if ($imageUrl) {
                return $imageUrl;
            }
        }
        
        // Fallback: ritorna null (il controller userà un placeholder)
        return null;
    }

    /**
     * Cerca una GIF su Giphy usando l'API (richiede GIPHY_API_KEY in .env).
     * Se non disponibile, ritorna null e si usa DALL-E come fallback.
     */
    private function searchGiphyGif(string $exerciseName, ?string $muscleGroup = null): ?string
    {
        // Giphy richiede una API key. Se non disponibile, salta questa ricerca.
        // In produzione, aggiungi GIPHY_API_KEY al .env per abilitare la ricerca GIF.
        $giphyApiKey = $_ENV['GIPHY_API_KEY'] ?? null;
        if (empty($giphyApiKey)) {
            $this->logger?->debug('GIPHY_API_KEY not configured, skipping Giphy search');
            return null;
        }
        
        try {
            $searchQuery = $exerciseName;
            if ($muscleGroup) {
                $searchQuery .= ' ' . $muscleGroup . ' workout';
            } else {
                $searchQuery .= ' exercise workout';
            }
            
            $giphyClient = new Client([
                'base_uri' => 'https://api.giphy.com/v1/',
                'timeout' => 10,
            ]);
            
            $response = $giphyClient->get('gifs/search', [
                'query' => [
                    'api_key' => $giphyApiKey,
                    'q' => $searchQuery,
                    'limit' => 1,
                    'rating' => 'g', // General audience
                    'lang' => 'it',
                ],
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['data'][0]['images']['original']['url'])) {
                return $data['data'][0]['images']['original']['url'];
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger?->debug('Giphy search failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera un'immagine statica usando DALL-E (fallback se Giphy non ha risultati).
     */
    private function generateExerciseImage(Exercise $exercise): ?string
    {
        $exerciseName = $exercise->getName() ?? 'exercise';
        $muscleGroup = $exercise->getMuscleGroup();
        
        $prompt = "A detailed illustration showing how to perform the exercise: {$exerciseName}";
        if ($muscleGroup) {
            $prompt .= " targeting {$muscleGroup}";
        }
        $prompt .= ". Professional fitness illustration, clear and instructional.";

        try {
            $response = $this->client->post('images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'standard',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'][0]['url'] ?? null;
        } catch (GuzzleException $e) {
            $this->logger?->error('DALL-E image generation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @deprecated Usa generateExerciseGif() invece
     */
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

