# Deploy su OVH via Git Pull

## Prerequisiti

1. **Accesso FTP/File Manager** su OVH
2. **Accesso Git** sul server (o possibilità di fare git pull)
3. **PHP 8.3+** installato
4. **Composer** installato (o `vendor/` già caricato)

## Setup Iniziale (Prima volta)

### 1. Clona il repository sul server

```bash
cd /path/to/your/project
git clone <repository-url> .
```

### 2. Carica vendor/ (se non hai composer)

Se non hai `composer install` disponibile, carica la cartella `vendor/` completa via FTP.

### 3. Crea le cartelle necessarie

Assicurati che esistano queste cartelle (creale via File Manager o FTP se non ci sono):

```
var/
  ├── cache/
  │   └── prod/  (deve essere scrivibile - permessi 755 o 777)
  └── log/       (deve essere scrivibile - permessi 755 o 777)
```

### 4. Configura .env

1. Copia `.env-prod` come `.env` nella root del progetto
2. Modifica le credenziali in `.env`:
   - `DATABASE_URL` - credenziali database OVH
   - `MAILER_DSN` - credenziali SMTP OVH
   - `APP_SECRET` - genera una chiave sicura

Per generare `APP_SECRET`:
```bash
php bin/console secrets:generate-secret
```

### 5. Configura .htaccess (se necessario)

Se il virtualhost non punta già a `public/`, copia `.htaccess.prod` come `.htaccess` nella root.

### 6. Build Frontend

```bash
cd frontend
npm install
npm run build
```

Poi copia il contenuto di `frontend/dist/` in `public/app/` sul server.

### 7. Esegui migrazioni e setup

```bash
php bin/console doctrine:migrations:migrate -n --env=prod
php bin/console cache:clear --env=prod
php bin/console app:seed:config
php bin/console app:user:create-admin admin@tuodominio.com password
```

## Deploy Aggiornamenti (via Git Pull)

### 1. Pull dal repository

```bash
git pull origin master
```

### 2. Aggiorna dipendenze (se necessario)

Se hai aggiunto nuove dipendenze in `composer.json`:
- Carica la nuova cartella `vendor/` via FTP, oppure
- Esegui `composer install --no-dev --optimize-autoloader` se disponibile

### 3. Aggiorna Frontend (se modificato)

```bash
cd frontend
npm install
npm run build
```

Poi copia il contenuto di `frontend/dist/` in `public/app/`.

### 4. Esegui migrazioni (se ci sono nuove)

```bash
php bin/console doctrine:migrations:migrate -n --env=prod
```

### 5. Pulisci cache

```bash
php bin/console cache:clear --env=prod
```

## Checklist File Necessari

Assicurati che questi file/cartelle siano presenti sul server:

- ✅ `src/` - Codice sorgente
- ✅ `config/` - Configurazione Symfony
- ✅ `public/` - Document root
- ✅ `bin/` - Console Symfony
- ✅ `migrations/` - Migrazioni database
- ✅ `templates/` - Template Twig
- ✅ `vendor/` - Dipendenze Composer
- ✅ `var/cache/prod/` - Cache (scrivibile)
- ✅ `var/log/` - Log (scrivibile)
- ✅ `composer.json` - Dipendenze
- ✅ `composer.lock` - Versioni bloccate
- ✅ `symfony.lock` - Config Flex
- ✅ `.env` - Configurazione (copiato da `.env-prod`)
- ✅ `.htaccess` - Apache rewrite (se necessario)
- ✅ `public/app/` - Frontend buildato

## Permessi Cartelle

Assicurati che queste cartelle siano scrivibili:

- `var/cache/prod/` → 755 o 777
- `var/log/` → 755 o 777
- `public/uploads/` → 755 o 777

## Troubleshooting

### Errore "Cache directory not writable"
```bash
chmod -R 755 var/cache var/log
```

### Errore "Class not found"
- Verifica che `vendor/` sia completo
- Esegui: `php bin/console cache:clear --env=prod`

### Frontend non carica
- Verifica che `public/app/index.html` esista
- Verifica che `public/.htaccess` sia presente e corretto

### API non funziona
- Verifica che `public/.htaccess` contenga le regole per `/api`
- Verifica che `APP_ENV=prod` in `.env`

