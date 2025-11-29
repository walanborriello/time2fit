# Time2Fit - Gestionale Palestra

Sistema completo di gestione palestra con backend Symfony 6.4 (API JSON) e frontend React + Vite.

## 🚀 Setup Sviluppo Locale

### Prerequisiti
- Docker e Docker Compose
- Node.js 18+ e npm

### 0. Configurazione File Hosts (Windows)

**IMPORTANTE**: Prima di avviare Docker, configura il file hosts per far funzionare `time2fit.local`.

Apri PowerShell come **Amministratore** ed esegui:
```powershell
Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value "`n127.0.0.1    time2fit.local"
```

Oppure modifica manualmente `C:\Windows\System32\drivers\etc\hosts` aggiungendo:
```
127.0.0.1    time2fit.local
```

Verifica con: `ping time2fit.local` (dovrebbe rispondere a 127.0.0.1)

Vedi `SETUP_LOCALE.md` per dettagli completi.

### 1. Avvia i container Docker

```bash
docker compose build
docker compose up -d
```

### 2. Installa le dipendenze Symfony

```bash
docker exec -it gym_php bash
composer install
```

### 3. Esegui le migrazioni

```bash
php bin/console doctrine:migrations:migrate -n
```

### 4. Seed configurazioni iniziali

```bash
php bin/console app:seed:config
```

### 5. Crea admin iniziale

```bash
php bin/console app:user:create-admin admin@time2fit.local SuperPasswordSicura!
```

### 6. Setup Frontend

```bash
cd frontend
npm install
npm run dev
```

## 📁 Struttura Progetto

```
t2f/
├── docker/              # Configurazione Docker
├── frontend/            # SPA React + Vite
│   ├── src/
│   │   ├── api/         # Client API
│   │   ├── components/  # Componenti riutilizzabili
│   │   ├── pages/       # Pagine React
│   │   ├── router/      # Routing
│   │   ├── contexts/    # Context React
│   │   └── hooks/       # Custom hooks
│   └── dist/            # Build produzione
├── src/                 # Backend Symfony
│   ├── Command/         # CLI commands
│   ├── Controller/      # API Controllers
│   ├── Entity/          # Entità Doctrine
│   ├── Repository/      # Repository Doctrine
│   ├── Security/        # Handlers autenticazione
│   └── Service/         # Servizi business logic
├── config/              # Configurazione Symfony
├── migrations/          # Migrazioni database
└── public/              # Document root
```

## 🔐 Autenticazione

- **Metodo**: Sessione HTTP + Cookie (stesso dominio)
- **Endpoint**: `/api/login`, `/api/logout`, `/api/me`
- **Registrazione**: `/api/register` (solo clienti)

## 🎨 Tema Time2Fit

- **Sfondo**: `#000000` (nero)
- **Titoli**: `#00ff00` (verde)
- **Testo**: `#FFFFFF` (bianco)
- **Bottoni**: `#f9cc49` (giallo)
- **Font**: Lato, sans-serif

## 📦 Build Produzione

### Frontend

```bash
cd frontend
npm run build
```

I file vengono generati in `frontend/dist/`. Copiarli in `public/app/` per il deploy.

### Backend

```bash
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
```

## 🌐 Deploy Produzione (Aruba)

1. Upload file Symfony (escluso `node_modules`, `frontend/node_modules`)
2. Configura `DATABASE_URL` in `.env.prod`
3. Esegui migrazioni: `php bin/console doctrine:migrations:migrate -n --env=prod`
4. Copia `frontend/dist/*` in `public/app/`
5. Configura `.htaccess` per routing SPA
6. Imposta cron per `app:schede:promemoria`

## 📝 Comandi Utili

- `php bin/console app:seed:config` - Seed configurazioni
- `php bin/console app:user:create-admin <email> <password>` - Crea admin
- `php bin/console app:schede:promemoria` - Invia promemoria scadenze

## 🔧 Configurazione

### AI Descriptions - Opzionale

Il sistema supporta **più provider AI** per generare descrizioni esercizi:

#### Opzione 1: Hugging Face (GRATUITO - Consigliato) ⭐

**Completamente gratuito**, nessun limite di quota:
1. Vai su https://huggingface.co/join
2. Crea un account gratuito
3. Vai su https://huggingface.co/settings/tokens
4. Crea un nuovo token (read permission)
5. Aggiungi in `.env`:
   ```
   AI_PROVIDER=huggingface
   HUGGINGFACE_API_KEY=hf_tua-chiave-qui
   ```

**Limiti gratuiti**: ~1000 richieste/giorno senza API key, illimitate con API key gratuita.

#### Opzione 2: OpenAI (A pagamento)

Per usare OpenAI:
1. Crea un account su https://platform.openai.com
2. Vai su https://platform.openai.com/api-keys
3. Crea una nuova API key
4. Aggiungi in `.env`:
   ```
   AI_PROVIDER=openai
   OPENAI_API_KEY=sk-tua-chiave-qui
   ```

#### Opzione 3: Auto (Default)

Il sistema sceglie automaticamente:
- Se OpenAI è configurato → usa OpenAI
- Altrimenti → usa Hugging Face (gratuito)

Aggiungi in `.env`:
```
AI_PROVIDER=auto
OPENAI_API_KEY=sk-tua-chiave-qui  # Opzionale
HUGGINGFACE_API_KEY=hf_tua-chiave-qui  # Opzionale
```

**Nota**: Se nessun provider è configurato, il sistema userà descrizioni di fallback automatiche.

### Cloudinary (Media Storage)

Imposta in `.env`:
- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`
- `USE_CLOUDINARY=true`

### Mail (Mailcatcher dev / SMTP prod)

- **Dev**: Mailcatcher su `http://localhost:1080`
- **Prod**: Configura `MAILER_DSN` in `.env.prod`

## 📚 API Endpoints

- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/me` - Info utente
- `POST /api/register` - Registrazione cliente
- `GET /api/exercises` - Lista esercizi
- `GET /api/clients/{id}/plans/active` - Scheda attiva
- `POST /api/exercises/{tpeId}/progress` - Registra progresso
- `POST /api/clients/{id}/takeover` - Takeover cliente

Vedi `src/Controller/Api/` per tutti gli endpoint.

## 🐛 Troubleshooting

- **Errore connessione DB**: Verifica che il container `gym_db` sia attivo
- **404 API**: Verifica che Nginx sia configurato correttamente
- **CORS errors**: Verifica `config/packages/cors.yaml`
- **Sessioni non funzionano**: Verifica `config/packages/framework.yaml`

## 📄 Licenza

Proprietario - Time2Fit

