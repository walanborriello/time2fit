<?php

namespace App\Service;

use App\Entity\Exercise;

interface AiProviderInterface
{
    /**
     * Genera una descrizione per un esercizio
     * 
     * @param Exercise $exercise L'esercizio per cui generare la descrizione
     * @param string $prompt Il prompt completo da inviare
     * @return string La descrizione generata
     * @throws \RuntimeException Se la generazione fallisce
     */
    public function generateDescription(Exercise $exercise, string $prompt): string;
    
    /**
     * Verifica se il provider è configurato e disponibile
     * 
     * @return bool True se disponibile, false altrimenti
     */
    public function isAvailable(): bool;
    
    /**
     * Restituisce il nome del provider
     * 
     * @return string
     */
    public function getName(): string;
}


