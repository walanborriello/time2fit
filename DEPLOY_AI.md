# Deploy AI - Istruzioni per Server

## Configurazione Variabili d'Ambiente

Sul server, assicurati di avere queste variabili nel file `.env` o `.env.prod`:

```env
# Provider AI (auto = usa OpenAI se disponibile, altrimenti HuggingFace)
AI_PROVIDER=auto

# OpenAI API Key
OPENAI_API_KEY=sk-tua-chiave-qui

# HuggingFace API Key (opzionale, come fallback)
HUGGINGFACE_API_KEY=hf_tua-chiave-qui

# Opzionale: modello HuggingFace specifico (se trovi uno che funziona)
# HUGGINGFACE_MODEL=distilgpt2
```

## Test dopo Deploy

Dopo aver caricato il codice sul server, testa con:

```bash
# Test completo provider AI
php bin/console app:test:ai-providers

# Test solo OpenAI
php bin/console app:test:openai

# Test solo HuggingFace
php bin/console app:test:huggingface
```

## Verifica Connettività

Se sul server funziona ma in locale no, potrebbe essere:
- Firewall locale che blocca le chiamate API
- Proxy/VPN che interferisce
- Configurazione di rete Docker

## Stato Attuale

- ✅ Configurazione multi-provider (OpenAI primario, HuggingFace fallback)
- ✅ Gestione errori e retry automatici
- ✅ Descrizione fallback strutturata se entrambi falliscono
- ✅ Logging dettagliato per debug

## Note

- OpenAI: richiede credito disponibile sul account
- HuggingFace: l'API Inference è cambiata, molti modelli non sono più disponibili gratuitamente
- Il sistema userà automaticamente il provider disponibile

