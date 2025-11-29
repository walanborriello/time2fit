# Deploy su OVH Cloud - Configurazione Virtualhost

## Configurazione Virtualhost Apache

Su OVH Cloud, devi configurare il virtualhost per puntare alla cartella `public/` come document root.

### Opzione 1: Pannello OVH (se disponibile)

Nel pannello di controllo OVH:
1. Vai su **Hosting** → **Multisito**
2. Seleziona il tuo dominio
3. Imposta **Cartella radice** a: `public/` (o `www/public/` se il progetto è in una sottocartella)
4. Salva

### Opzione 2: File .htaccess nella root (se non puoi modificare il virtualhost)

Se non puoi modificare il virtualhost direttamente:
1. Copia `.htaccess.prod` come `.htaccess` nella root del progetto sul server
2. Questo file reindirizzerà tutto a `public/`

**NOTA**: 
- Il file `.htaccess` nella root è già nel `.gitignore` e non verrà pushato su Git
- In locale (Docker) non serve perché usi Nginx, non Apache
- I file template (`.htaccess.prod`, `.htaccess.local`) sono nel repository per riferimento

### Opzione 3: File .htaccess in public/ (già presente)

Il file `public/.htaccess` è già configurato correttamente per:
- Routing API Symfony (`/api/*` → `index.php`)
- Routing SPA React (`/*` → `/app/index.html`)

## Verifica Configurazione

Dopo la configurazione, verifica:

1. **API Symfony**: `https://tuodominio.com/api/me` (dovrebbe rispondere con JSON)
2. **Frontend SPA**: `https://tuodominio.com/` (dovrebbe caricare l'app React)

## File .env.prod

Assicurati di:
1. Copiare `.env-prod` in `.env` sul server (o configurare le variabili d'ambiente)
2. Modificare `MAILER_DSN` con le credenziali OVH reali:
   ```env
   MAILER_DSN=smtp://username:password@smtp.ovh.net:587
   ```
3. Generare `APP_SECRET` per produzione:
   ```bash
   php bin/console secrets:generate-secret
   ```

## Build Frontend

Prima del deploy, builda il frontend:

```bash
cd frontend
npm install
npm run build
```

Poi copia i file da `frontend/dist/` in `public/app/` sul server.

## Migrazioni Database

Dopo il deploy, esegui le migrazioni:

```bash
php bin/console doctrine:migrations:migrate -n --env=prod
```

## Comandi Utili

```bash
# Clear cache produzione
php bin/console cache:clear --env=prod

# Test provider AI
php bin/console app:test:ai-providers

# Crea admin
php bin/console app:user:create-admin email@example.com password
```

