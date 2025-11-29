<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Rate limiter per OpenAI API per evitare 429 errors.
 * Serializza le richieste e rispetta i limiti di rate.
 */
class AiRateLimiter
{
    private static ?self $instance = null;
    private array $requestQueue = [];
    private ?float $lastRequestTime = null;
    private float $minInterval; // Secondi minimi tra richieste
    private int $maxRequestsPerMinute;
    private array $requestTimestamps = [];
    private string $lockFile;
    
    private function __construct(
        private ?LoggerInterface $logger = null
    ) {
        // gpt-4o-mini: ~500 RPM, ma per sicurezza usiamo 5 RPM (1 richiesta ogni 15 secondi)
        // Questo è molto conservativo per evitare rate limits persistenti
        $this->minInterval = 15.0; // Secondi tra richieste (aumentato a 15 secondi per evitare 429)
        $this->maxRequestsPerMinute = 5; // Massimo 5 richieste al minuto (molto conservativo)
        
        // Lock file per garantire che solo una richiesta alla volta venga processata
        // Usa un path assoluto per evitare problemi in Docker
        $lockDir = sys_get_temp_dir();
        if (!is_writable($lockDir)) {
            // Fallback: usa /tmp se sys_get_temp_dir() non è scrivibile
            $lockDir = '/tmp';
        }
        $this->lockFile = $lockDir . '/openai_rate_limiter.lock';
    }
    
    public static function getInstance(?LoggerInterface $logger = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($logger);
        }
        return self::$instance;
    }
    
    /**
     * Aspetta se necessario prima di fare una richiesta per rispettare i rate limits.
     * Usa un lock file per garantire che solo una richiesta alla volta venga processata.
     */
    public function waitIfNeeded(): void
    {
        // Lock file-based per garantire serializzazione anche tra processi PHP diversi
        $lockHandle = fopen($this->lockFile, 'c+');
        if (!$lockHandle) {
            $this->logger?->error('Rate limiter: cannot create lock file');
            return; // Continua comunque, ma senza lock
        }
        
        // Prova ad acquisire il lock (bloccante)
        $maxWait = 300; // Max 5 minuti di attesa
        $waited = 0;
        while (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($waited >= $maxWait) {
                $this->logger?->error('Rate limiter: timeout waiting for lock');
                fclose($lockHandle);
                throw new \RuntimeException('Rate limiter: timeout waiting for lock. Another request is in progress.');
            }
            $this->logger?->info("Rate limiter: waiting for lock (waited {$waited}s)...");
            sleep(1);
            $waited++;
        }
        
        try {
            // Leggi l'ultimo timestamp dal file
            $lastRequestTime = null;
            $requestTimestamps = [];
            
            // Leggi il contenuto del file solo se esiste e non è vuoto
            if (file_exists($this->lockFile)) {
                $fileSize = filesize($this->lockFile);
                if ($fileSize > 0) {
                    rewind($lockHandle);
                    $content = fread($lockHandle, $fileSize);
                    if ($content) {
                        $data = json_decode($content, true);
                        if ($data) {
                            $lastRequestTime = $data['lastRequestTime'] ?? null;
                            $requestTimestamps = $data['requestTimestamps'] ?? [];
                        }
                    }
                }
            }
            
            $now = microtime(true);
            
            // Pulisci timestamp più vecchi di 1 minuto
            $requestTimestamps = array_filter(
                $requestTimestamps,
                fn($timestamp) => ($now - $timestamp) < 60
            );
            
            $requestsInLastMinute = count($requestTimestamps);
            $this->logger?->info("Rate limiter: checking. Requests in last minute: {$requestsInLastMinute}/{$this->maxRequestsPerMinute}");
            
            // Se abbiamo raggiunto il limite per minuto, aspetta
            if ($requestsInLastMinute >= $this->maxRequestsPerMinute) {
                $oldestRequest = min($requestTimestamps);
                $waitTime = 60 - ($now - $oldestRequest);
                if ($waitTime > 0) {
                    $this->logger?->warning("Rate limiter: MAX REQUESTS PER MINUTE REACHED! Waiting {$waitTime}s");
                    // Rilascia il lock durante l'attesa
                    flock($lockHandle, LOCK_UN);
                    sleep((int)ceil($waitTime));
                    // Riacquista il lock
                    flock($lockHandle, LOCK_EX);
                    $now = microtime(true);
                    // Pulisci di nuovo dopo l'attesa
                    $requestTimestamps = array_filter(
                        $requestTimestamps,
                        fn($timestamp) => ($now - $timestamp) < 60
                    );
                }
            }
            
            // Aspetta l'intervallo minimo tra richieste
            if ($lastRequestTime !== null) {
                $timeSinceLastRequest = $now - $lastRequestTime;
                if ($timeSinceLastRequest < $this->minInterval) {
                    $waitTime = $this->minInterval - $timeSinceLastRequest;
                    $this->logger?->info("Rate limiter: waiting {$waitTime}s for minimum interval (last request was {$timeSinceLastRequest}s ago)");
                    // Rilascia il lock durante l'attesa
                    flock($lockHandle, LOCK_UN);
                    sleep((int)ceil($waitTime));
                    // Riacquista il lock
                    flock($lockHandle, LOCK_EX);
                    $now = microtime(true);
                }
            }
            
            // Registra questa richiesta
            $lastRequestTime = $now;
            $requestTimestamps[] = $now;
            
            // Salva nel file
            ftruncate($lockHandle, 0);
            rewind($lockHandle);
            fwrite($lockHandle, json_encode([
                'lastRequestTime' => $lastRequestTime,
                'requestTimestamps' => $requestTimestamps
            ]));
            fflush($lockHandle);
            
            $this->logger?->info("Rate limiter: ✅ REQUEST ALLOWED. Total requests in last minute: " . count($requestTimestamps) . "/{$this->maxRequestsPerMinute}");
            
        } finally {
            // Rilascia sempre il lock
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
    
    /**
     * Resetta il rate limiter (utile per test o dopo errori 429 persistenti).
     */
    public function reset(): void
    {
        $this->lastRequestTime = null;
        $this->requestTimestamps = [];
        
        // Resetta anche il file di lock
        if (file_exists($this->lockFile)) {
            $lockHandle = fopen($this->lockFile, 'c+');
            if ($lockHandle) {
                if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
                    ftruncate($lockHandle, 0);
                    rewind($lockHandle);
                    fwrite($lockHandle, json_encode([
                        'lastRequestTime' => null,
                        'requestTimestamps' => []
                    ]));
                    fflush($lockHandle);
                    flock($lockHandle, LOCK_UN);
                    $this->logger?->info('Rate limiter: reset completed');
                }
                fclose($lockHandle);
            }
        }
    }
    
    /**
     * Forza un'attesa più lunga dopo un errore 429.
     * Resetta il rate limiter e impone un'attesa minima.
     */
    public function handle429Error(int $retryAfterSeconds = 60): void
    {
        $this->logger?->warning("Rate limiter: handling 429 error, resetting and waiting {$retryAfterSeconds}s");
        $this->reset();
        
        // Aspetta il tempo indicato da OpenAI
        if ($retryAfterSeconds > 0) {
            sleep($retryAfterSeconds);
        }
    }
}

