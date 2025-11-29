# Setup Ambiente Locale Time2Fit

## 1. Configurazione File Hosts (Windows)

Per far funzionare `time2fit.local` sul tuo computer, devi aggiungere un'entry nel file hosts di Windows.

### Passaggi:

1. **Apri il file hosts come amministratore:**
   - Premi `Win + R`
   - Digita: `notepad C:\Windows\System32\drivers\etc\hosts`
   - Clicca con il tasto destro su Notepad e seleziona "Esegui come amministratore"
   - Conferma l'apertura

2. **Aggiungi questa riga alla fine del file:**
   ```
   127.0.0.1    time2fit.local
   ```

3. **Salva il file** (Ctrl+S)

4. **Verifica:**
   - Apri un prompt dei comandi
   - Esegui: `ping time2fit.local`
   - Dovresti vedere che risponde a `127.0.0.1`

### Alternativa rapida (PowerShell come amministratore):

```powershell
Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value "`n127.0.0.1    time2fit.local"
```

## 2. Avvio Docker

```bash
docker compose up -d
```

## 3. Setup Symfony

```bash
docker exec -it gym_php bash
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console app:seed:config
php bin/console app:user:create-admin admin@time2fit.local SuperPasswordSicura!
```

## 4. Setup Frontend

```bash
cd frontend
npm install
npm run dev
```

## 5. Accesso

- **Backend API**: http://time2fit.local/api
- **Frontend SPA**: http://time2fit.local (quando buildato) o http://localhost:5173 (dev server Vite)
- **phpMyAdmin**: http://localhost:8080
- **Mailcatcher**: http://localhost:1080

## Note

- Il dev server Vite di default gira su `http://localhost:5173` e fa proxy delle API a `http://time2fit.local/api`
- Per la produzione locale, builda il frontend (`npm run build`) e copia i file in `public/app/`
- Se usi il dev server Vite, non serve copiare i file in `public/app/`


