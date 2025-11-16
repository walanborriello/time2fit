# guideline.md — Gestionale Palestra (FULL spec + Tech, ottimizzata per Cursor)

> **Obiettivo:** questo file è la **fonte unica di verità** per sviluppare il gestionale palestra con **Symfony**.  
> Contiene sia la **specifica funzionale** (RF/RNF, UC, workflow, regole) sia le **linee guida tecniche** per:
> - ambiente **locale**: Symfony + Docker (dev only)
> - ambiente **produzione**: **senza Docker**, ad es. hosting Aruba (PHP + MySQL).

Host locale dev: `http://gestionale.local`  
phpMyAdmin dev (Docker): `http://localhost:8080/` (user: `walan`, pass: `Palestra$`)

---

# 1. CONTESTO & SCOPO MODULO

Il gestionale è pensato per una **palestra** in cui:

- Gli **istruttori** creano e gestiscono **schede di allenamento** per i clienti.
- Ogni scheda è legata a una **tipologia di palestra** (`ISOTONICA`, `FUNZIONALE`).
- Gli esercizi hanno:
  - **nome**
  - **descrizione** (generabile con **AI** o inserita a mano)
  - **demo in GIF** (AI, upload GIF, o video convertito in GIF)
- Ogni scheda ha una **data di scadenza**, ma:
  - resta **consultabile** anche dopo la scadenza
  - la **scheda attiva** è **sempre l’ultima creata/associata** al cliente
- Gli **amministratori** ricevono una **email di promemoria** `N` giorni prima della scadenza delle schede (offset configurabile).
- I **Personal Trainer** hanno una **agenda** per appuntamenti e tracciano i **progressi** dei clienti.
- I **clienti** hanno un’area riservata in cui:
  - vedono la **scheda attiva**
  - registrano i **progressi**
  - vedono le **demo** (GIF) degli esercizi.

Backend: **Symfony (API JSON)**.  
Frontend: **SPA moderna (React con Vite)** buildata in locale e servita come statici in produzione.

---

# 2. ATTORI & RUOLI

## 2.1 Ruoli

- **Admin** (`ROLE_ADMIN`)
- **Istruttore/Amministratore** (`ROLE_INSTRUCTOR_ADMIN`) — può anche essere PT
- **Istruttore/Personal Trainer** (`ROLE_INSTRUCTOR_PT`)
- **Istruttore semplice** (`ROLE_INSTRUCTOR`)
- **Cliente** (`ROLE_CLIENT`)

## 2.2 Policy di creazione utenti

- L’**Admin iniziale** viene creato via comando CLI (`app:user:create-admin`).
- **Nessun form pubblico** per ruoli staff (Admin / Istruttori / PT).
- I **Clienti** possono **registrarsi** tramite form `/register` (con reCAPTCHA).
- Collegamento Cliente ↔ Schede:
  - L’istruttore può creare un **cliente anagrafico** con un’email.
  - Quando un cliente si registra con la **stessa email**, le schede associate a quell’email vengono collegate al suo account.

## 2.3 Permessi (sintesi)

- **Admin**:
  - Gestisce set/esercizi, istruttori, config
  - Vede tutti i clienti/schede
  - Riceve mail promemoria
- **Istruttore-Admin**:
  - Come Admin a livello operativo palestra
  - Può anche essere PT
- **Istruttore/PT**:
  - Crea anagrafiche clienti
  - Crea e assegna schede
  - Gestisce agenda
  - Traccia progressi
- **Cliente**:
  - Si registra
  - Visualizza scheda attiva/storico
  - Registra progressi

---

# 3. REQUISITI FUNZIONALI (RF)

(identici alla versione precedente, riassunti)

- RF1: Tipologie palestra (`GymType` enum).
- RF2: Set esercizi per tipologia, con media GIF (AI, upload GIF, video→GIF).
- RF3: Descrizione esercizio via AI.
- RF4: Media esercizio via AI (opzionale).
- RF5: Gestione istruttori.
- RF6: Gestione clienti (istruttore).
- RF6a: Registrazione cliente (pubblica).
- RF7: Creazione scheda allenamento.
- RF7a: Scheda attiva = ultima creata/associata.
- RF8: Promemoria scadenze (N giorni).
- RF9: Agenda PT.
- RF10: Progressi.
- RF11: Area Cliente.
- RF12: Takeover (“Prendi in gestione”).
- RF13: Configurazioni chiave-valore.

(RNF, UC, modello dati → invariati rispetto alla bozza funzionale già definita; per Cursor sono già sintetizzati nel mapping entità + task).

---

# 4. MODELLO DATI (ENTITÀ CHIAVE)

(uguale alla versione precedente: User, InstructorProfile, Client, InstructorClient, GymType, Exercise, ExerciseSet, TrainingPlan, TrainingPlanExercise, ProgressLog, Appointment, Config).

---

# 5. ARCHITETTURA GENERALE

## 5.1 Backend

- Symfony 6.4 LTS (PHP 8.3)
- Autenticazione:
  - per semplicità iniziale: sessione HTTP + cookie (SPA servita dallo stesso dominio del backend) **oppure** JWT (`Authorization: Bearer`) se si preferisce un distacco più netto.
- Rotte API:
  - prefisso `/api`
  - risposte JSON
  - validazioni via Symfony Validator
- Nessun Twig per UI utente finale, solo eventuali pagine di debug / errori.

## 5.2 Frontend SPA (React + Vite)

Struttura:

- cartella `/frontend`:
  - React + Vite
  - `src/` con pagine:
    - Login / Registrazione
    - Dashboard Cliente (scheda, progressi)
    - Dashboard Istruttore (clienti, schede, takeover)
    - Dashboard Admin (esercizi, set, istruttori, config)
    - Agenda PT
- build:
  - `cd frontend && npm run build`
  - output in `frontend/dist`
  - in produzione i file buildati vengono copiati in `/public/app` (o serviti via alias web).

In locale con Docker possiamo anche usare il dev server Vite in proxy verso Symfony.

---

# 6. API (DESIGN AD ALTO LIVELLO)

Esempi di endpoint (da implementare con controller Symfony):

- Auth:
  - `POST /api/login` → token/sessione
  - `POST /api/logout`
  - `GET /api/me` → info utente loggato + ruoli

- Esercizi & Set:
  - `GET /api/exercises`
  - `POST /api/exercises`
  - `PUT /api/exercises/{id}`
  - `DELETE /api/exercises/{id}`
  - `GET /api/exercise-sets`
  - `POST /api/exercise-sets`
  - ...

- Schede:
  - `GET /api/clients/{id}/plans`
  - `POST /api/clients/{id}/plans`
  - `GET /api/clients/{id}/plans/active`
  - `PUT /api/plans/{id}`

- Progressi:
  - `GET /api/plans/{planId}/progress`
  - `POST /api/exercises/{tpeId}/progress`

- Takeover:
  - `POST /api/clients/{id}/takeover`

Questi sono solo esempi: Cursor può derivare le firme esatte dalle entità.

---

# 7. DOCKER (SOLO AMBIENTE LOCALE)

> **Importante**: Docker viene usato **solo in locale** per lo sviluppo.  
> In produzione (es. Aruba) NON useremo Docker, ma PHP + Apache/Nginx nativi del provider.

## 7.1 Docker Compose (dev)

`docker-compose.yml` (identico a prima, con servizi: db, php, nginx, phpmyadmin, cron sidecar).  
Serve solo per avere rapidamente:

- MySQL 8
- PHP 8.3 FPM con ffmpeg
- Nginx con host `gestionale.local`
- phpMyAdmin
- cron sidecar per job `app:schede:promemoria`

## 7.2 Setup dev (riassunto)

1. `docker compose build`
2. `docker compose up -d`
3. `docker exec -it gym_php bash`
4. `composer install`
5. `php bin/console doctrine:migrations:migrate -n`
6. `php bin/console app:seed:config`
7. `php bin/console app:user:create-admin admin@gestionale.local SuperPasswordSicura!`
8. `cd frontend && npm install && npm run dev` (se usi Vite in dev)

---

# 8. PRODUZIONE SENZA DOCKER (ES. ARUBA)

Questa è la parte importante per te.  
Scenario: **hosting Aruba** (shared o VPS) con **PHP + MySQL**, ma **senza Docker**.

## 8.1 Backend Symfony in produzione

1. **Preparazione locale** (o su macchina di build):
   - Assicurati di avere il codice aggiornato (Git).
   - Esegui:
     ```bash
     composer install --no-dev --optimize-autoloader
     php bin/console cache:clear --env=prod
     ```

2. **Caricamento su Aruba**:
   - Upload via FTP/SFTP o Git deploy.
   - La directory `public/` deve essere la **docroot** del sito (o mappata come tale).
   - Il resto del progetto (src, vendor, ecc.) deve essere fuori dalla docroot o non accessibile via web.

3. **Configurazione DB**:
   - Aruba ti fornisce host, dbname, user, password.
   - Imposta `DATABASE_URL` in `.env.prod` o variabili ambiente:
     ```env
     DATABASE_URL="mysql://UTENTE:PASS@HOST:3306/NOME_DB?charset=utf8mb4"
     APP_ENV=prod
     APP_DEBUG=0
     ```

4. **Migrations in produzione**:
   - Se hai **SSH**:
     ```bash
     php bin/console doctrine:migrations:migrate -n --env=prod
     ```
   - Se NON hai SSH:
     - puoi eseguire localmente lo schema su un DB di test e poi esportare (`mysqldump`) e importare su Aruba via phpMyAdmin (non ideale, ma funziona).

5. **Mail & promemoria**:
   - Configura `MAILER_DSN` secondo le specifiche di Aruba (SMTP).
   - Il comando `app:schede:promemoria` sarà lanciato da un **cron job Aruba**, NON da Docker.

## 8.2 Frontend SPA in produzione

1. **Build in locale**:
   ```bash
   cd frontend
   npm install
   npm run build
   ```

2. **Output**:
   - Vite produce `frontend/dist` con file statici (HTML, JS, CSS).

3. **Deploy statici**:
   - Copia il contenuto di `frontend/dist` nella cartella `public/app` del progetto Symfony (o direttamente nella `public/` root a seconda di come vuoi strutturare le URL).

4. **Routing**:
   - Se usi SPA con client-side routing (es. React Router), configura `.htaccess` o Nginx/Apache di Aruba in modo che:
     - tutte le richieste non API (es. `/`, `/dashboard`, `/client/plan`) vengano servite dalla `index.html` della SPA.
     - tutte le richieste `/api/...` vengano gestite da Symfony (`index.php`).
   - Su Aruba shared (Apache), tipicamente usi un `.htaccess` tipo:
     ```apache
     RewriteEngine On

     # API Symfony
     RewriteCond %{REQUEST_URI} ^/api
     RewriteRule ^ index.php [L]

     # SPA (React/Vite)
     RewriteCond %{REQUEST_FILENAME} !-f
     RewriteCond %{REQUEST_FILENAME} !-d
     RewriteRule . /app/index.html [L]
     ```

## 8.3 Cron su Aruba (senza Docker)

- Usa il pannello Aruba → sezione **Cron** / **Operazioni pianificate**.
- Imposta un cron job che lanci:

  **Opzione 1 (CLI):**
  ```bash
  php /percorso/assoluto/al/progetto/bin/console app:schede:promemoria --env=prod
  ```

  **Opzione 2 (URL fallback)**:  
  se CLI non disponibile, puoi creare un controller tipo `/cron/schede` che esegue il servizio, e impostare il cron per chiamare via HTTP quella URL (meno elegante, ma funziona).

- L’idea è: **in produzione il cron sidecar non serve**, viene sostituito dal cron del provider.

## 8.4 ffmpeg in produzione

- Su Aruba shared **potrebbe non esserci ffmpeg** o non essere disponibile.
- Possibili strategie:
  - **Feature flag** in `Config`: abilita/disabilita la funzione `video→gif`.
  - Nel caso ffmpeg non sia disponibile:
    - consenti **solo upload GIF** o gif generate da AI.
    - gestisci l’errore lato servizio (`MediaTranscodingService`) con fallback e messaggio chiaro.

---

# 9. TASK TECNICI PRIORITARI PER CURSOR (RIASSUNTO)

Questi rimangono validi sia per dev Docker sia per deploy Aruba:

1. Implementare entità + migrazioni (vedi §4).
2. Implementare servizi (`AiDescriptionService`, `MediaTranscodingService`, `ReminderService`, `SettingsService`, ecc.).
3. Implementare command (`app:seed:config`, `app:user:create-admin`, `app:schede:promemoria`).
4. Implementare API controller (`/api/...`) per login, esercizi, set, schede, progressi, takeover.
5. Implementare SPA React in `/frontend` che consuma le API Symfony.
6. Prevedere config distinta per **dev Docker** e **prod Aruba** (DB, mail, ffmpeg opzionale).

---

# 10. CRITERI DI ACCETTAZIONE CHIAVE

- Dev locale: `docker compose up -d` + `npm run dev` → app funziona su `gestionale.local` + SPA.
- Prod Aruba:
  - il sito risponde (SPA servita)
  - le API `/api/...` rispondono correttamente
  - cliente registrato vede scheda attiva se presente, altrimenti messaggio.
  - comando promemoria (`app:schede:promemoria`) funziona lanciato da cron Aruba.

---

Con questo aggiornamento, Docker è chiaramente marcato come **solo per sviluppo locale** e la guida spiega come portare **lo stesso progetto** in produzione **senza Docker** (es. Aruba).



---

# 13. BRANDING, RESPONSIVE DESIGN & MOBILE-FIRST REQUISITI

## 13.1 Nome applicazione & logo
L’app/gestionare si chiamerà **Time2Fit**.  
Il logo sarà caricato nella root del progetto insieme a `guideline.md`, es:  
```
/logo-time2fit.png
```

Il frontend React deve caricare questo logo nel layout principale, es:
```jsx
import logo from '/logo-time2fit.png';
```

## 13.2 Mobile-first (usabilità smartphone/tablet)
Una parte rilevante degli utenti accederà tramite **smartphone** o **tablet**.  
Quindi **tutta la SPA React** deve essere sviluppata seguendo:

### Requisiti obbligatori:
- **Mobile-first CSS** (layout progettato prima per mobile).
- Sidebar → convertita in **bottom navbar** o **hamburger menu** in mobile.
- Tabelle → convertite in **cards responsive** sotto breakpoint < 768px.
- Form → layout a colonna, campi grandi, touch-friendly.
- Grafici e progressi → wrapper fluidi (`width: 100%`, `max-width`, `aspect-ratio`).
- Gestione spazi → touch-friendly (min 44px height click targets).
- Drag/drop esercizi → fallback touch su dispositivi mobili.
- Nessun overflow orizzontale tollerato.

### Breakpoint consigliati:
- **<480px**: smartphone piccoli (UI molto compatta, menu hamburger)
- **480–768px**: smartphone grandi / tablet verticali
- **>1024px**: desktop e iPad landscape

## 13.3 Stile ispirato a *FitnessOnline* (Android)
La SPA React deve replicare lo stile moderno e pulito dell’app **FitnessOnline**, con:
- card arrotondate
- colori solidi con accent (adattati alla palette Time2Fit)
- icone semplici e chiare (Material Icons o Lucide)
- molto spazio bianco
- bottom navigation persistente
- schermate:
  - Dashboard cliente → card scheda + progressi + pulsante rapido “Registra progresso”
  - Lista esercizi → card con immagine, nome, tipo (isotonica/funzionale)
  - Screen scheda → elenco esercizi con animazioni/gif
  - Screen PT → agenda in stile calendario mensile + daily view

La palette già definita si integra molto bene:
- Primario: `#379975`
- Secondario: `#E57552`
- Body text: `#444444`
- Sfondo: `#FFFFFF`
- Footer/Barra tab: `#444444` (testo bianco)

## 13.4 Linee guida UI/UX Time2Fit (da seguire nei componenti React)
- Header ridotto — preferire bottom nav su mobile
- Design coerente: **card-based**
- Bottoni primari con gradiente Time2Fit
- Componenti riutilizzabili:
  - `<T2FButton>` → gestisce gradiente + dimensioni
  - `<T2FCard>` → card responsive con padding uniforme
  - `<T2FInput>` → input con errore e label floating
  - `<T2FNavbar>` → bottom nav mobile
  - `<T2FHeader>` → versione tablet/desktop

---

# 14. BOILERPLATE SPA REACT + VITE (per Cursor)

La cartella `/frontend` deve essere generata con questa struttura:

```
frontend/
  ├─ src/
  │   ├─ api/
  │   │   └─ http.js
  │   ├─ components/
  │   │   ├─ T2FButton.jsx
  │   │   ├─ T2FCard.jsx
  │   │   ├─ T2FInput.jsx
  │   │   ├─ T2FNavbar.jsx
  │   │   └─ T2FHeader.jsx
  │   ├─ pages/
  │   │   ├─ Login.jsx
  │   │   ├─ Register.jsx
  │   │   ├─ DashboardClient.jsx
  │   │   ├─ DashboardInstructor.jsx
  │   │   ├─ DashboardAdmin.jsx
  │   │   ├─ PlanView.jsx
  │   │   ├─ ProgressForm.jsx
  │   │   └─ AgendaPT.jsx
  │   ├─ router/
  │   │   └─ index.jsx
  │   ├─ hooks/
  │   │   ├─ useAuth.js
  │   │   └─ useApi.js
  │   ├─ contexts/
  │   │   └─ AuthContext.jsx
  │   ├─ assets/
  │   │   └─ (gif/icons/logo)
  │   ├─ App.jsx
  │   └─ main.jsx
  ├─ index.html
  ├─ package.json
  └─ vite.config.js
```

## 14.1 Router di base
Esempio router React:

```jsx
import { BrowserRouter, Routes, Route } from "react-router-dom";
import Login from "../pages/Login";
import Register from "../pages/Register";
import DashboardClient from "../pages/DashboardClient";

export default function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/client/dashboard" element={<DashboardClient />} />
        <Route path="*" element={<Login />} />
      </Routes>
    </BrowserRouter>
  );
}
```

## 14.2 API client standard
File `src/api/http.js`:

```js
import axios from "axios";

const api = axios.create({
  baseURL: "/api",
  withCredentials: true
});

export default api;
```

---

# 15. DIRETTIVE PER CURSOR (SEZIONE SPECIFICA)

> Cursor deve leggere TUTTO il file e generare:  
> - Backend Symfony API (entità + controller + servizi)  
> - Frontend React SPA (mobile-first) basato su FitnessOnline e brand Time2Fit  
> - Build Vite in `/public/app`  
> - Responsive design al 100%  
> - UI/UX in card style con bottom navigation mobile  
> - Supporto progressi, schede, takeover, agenda PT  
> - Supporto media GIF e (se disponibile) video→gif

Comandi per Cursor:  
- “Leggi guideline.md e genera backend Symfony + API”  
- “Genera SPA React in /frontend secondo struttura guideline”  
- “Integra logo /logo-time2fit.png nel layout”  
- “Applica design Time2Fit + breakpoints mobile-first”  



---

# 16. PALETTE TIME2FIT (DARK THEME FINALE)

Questa palette **sostituisce tutte le indicazioni colore precedenti**.  
Tutto il frontend (SPA React + eventuali pagine Twig) deve usare questi colori:

- **Sfondo principale**: `#000000` (nero)
- **Titoli (h1, h2, h3, ecc.)**: `#00ff00` (verde acceso)
- **Testo standard (body)**: `#FFFFFF` (bianco)
- **Link `<a>`**:
  - Colore normale: `#FFFFFF` (bianco)
  - Colore `:hover`: `#00ff00`
- **Bottoni principali**: `#f9cc49`
- **Colori extra / accent**:
  - `#ed3833` (accent / error / alert)
  - `#44b5f8` (accent / info / highlight)
- **Font di base**: `font-family: "Lato", sans-serif;`

Esempio base CSS globale (da applicare nella SPA React):

```css
:root {
  --t2f-bg: #000000;
  --t2f-title: #00ff00;
  --t2f-text: #ffffff;
  --t2f-link: #ffffff;
  --t2f-link-hover: #00ff00;
  --t2f-btn-primary: #f9cc49;
  --t2f-accent-red: #ed3833;
  --t2f-accent-blue: #44b5f8;
  --t2f-radius-card: 18px;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  padding: 0;
  background-color: var(--t2f-bg);
  color: var(--t2f-text);
  font-family: "Lato", sans-serif;
}

h1, h2, h3, h4, h5, h6 {
  color: var(--t2f-title);
  font-weight: 600;
}

a {
  color: var(--t2f-link);
  text-decoration: none;
}

a:hover {
  color: var(--t2f-link-hover);
}

button, .t2f-button {
  background-color: var(--t2f-btn-primary);
  color: #000000;
  border: none;
  border-radius: 999px;
  padding: 0.75rem 1.25rem;
  font-weight: 600;
  cursor: pointer;
}

.t2f-card {
  background-color: #111111;
  border-radius: var(--t2f-radius-card);
  padding: 1rem;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
}
```

Tutte le schermate Time2Fit (dashboard, schede, agenda, ecc.) devono rispettare questa palette e usare lo stile card-based con sfondo scuro e accenti verde/ambra/rosso/blu come indicato.

---

# 17. MOCKUP GRAFICO TIME2FIT (STILE FITNESSONLINE)

Le schermate chiave devono seguire il mockup descritto di seguito.  
Questa sezione formalizza il **“🔹 2. Mockup grafico Time2Fit in stile FitnessOnline”** e va usata come guida per i componenti React.

## 17.1 Dashboard Cliente (mobile)

- Header compatto con logo Time2Fit a sinistra (`/logo-time2fit.png`) e avatar utente a destra.
- Testo:
  - `Ciao, [Nome] 👋` in verde `#00ff00`, bold.
  - Sottotitolo: `Ecco la tua scheda attiva di oggi` in bianco.
- Card “Scheda attiva” full-width, `border-radius: 18px`, shadow morbida:
  - Background: card scura (`#111111`) con bordino o glow verde/blu.
  - Titolo scheda (`Upper Body – Isotonica`) in verde `#00ff00`.
  - Info: `Scade il 12/03/2026 · 8 esercizi` in bianco / grigio chiaro.
  - Badge `ATTIVA` con sfondo `#f9cc49` e testo nero.
- Sezione `Allenamento di oggi`:
  - Lista di max 3 esercizi in card orizzontali:
    - Thumbnail GIF (56x56, tonda, bordo verde).
    - Nome esercizio (`Panca piana bilanciere` in bianco).
    - Riga `4 x 10 · 50 kg` in grigio chiaro.
- Card progressi (mini grafico):
  - Titolo: `Progressi panca piana` in verde.
  - Sottotitolo: `+10 kg negli ultimi 30 giorni`.
  - Sfondo `#111111`, grafico lineare con accento `#44b5f8` o `#f9cc49`.

- FAB “+ Progresso” in basso a destra:
  - Cerchio con sfondo `#f9cc49`, icona `+` nera.
  - Tocco → apre formulario per registrare progresso.

- Bottom navigation (sempre visibile):
  - Sfondo: `#000000` o `#111111`.
  - Icone bianche, testo label in grigio chiaro; icona attiva in `#00ff00`.
  - Tab:
    - Home
    - Scheda
    - Agenda
    - Profilo

## 17.2 Schermata Scheda (PlanView – mobile)

- Header: back `<` + titolo `Scheda attiva`, testo verde.
- Badge tipologia: pill con bordo `#44b5f8` o `#f9cc49` e testo bianco (`ISOTONICA` / `FUNZIONALE`).
- Card riassunto scheda:
  - Nome scheda, scadenza, istruttore.
  - Se scaduta ma ancora visibile, testo in rosso `#ed3833` tipo `Scheda scaduta il ...`.

- Lista esercizi in card verticali (`.t2f-card`):
  - Thumbnail GIF (72x72) a sinistra.
  - A destra:
    - Nome esercizio in bianco, bold.
    - Riga info `4 x 10 · 50 kg` in grigio chiaro.
    - Tag muscolo (pill sfondo `#111` bordo `#44b5f8`).

- Tap card esercizio → schermata dettaglio:
  - GIF grande (full width) con bordo verde.
  - Descrizione (testo bianco su sfondo nero).
  - Tab “Progressi”: lista (data, peso, note) in card piccole.

## 17.3 Schermata Agenda PT (mobile)

- Header: `Agenda PT` in verde + bottone `+` (giallo `#f9cc49`) per nuovo appuntamento.
- Tab “Oggi / Settimana / Mese” con indicator attivo verde.
- Vista “Oggi”:
  - Timeline verticale:
    - Orario grande a sinistra (bianco).
    - Card appuntamento a destra (sfondo `#111111`), con:
      - Nome cliente in bianco.
      - Descrizione in grigio chiaro.
      - Piccolo badge tipologia `ISOTONICA`/`FUNZIONALE`.

- Vista “Mese”:
  - Calendario minimal (griglia 7x6), background nero, giorni in bianco.
  - Giorni con appuntamenti segnati da un dot `#00ff00`.
  - Tocco su giorno → lista appuntamenti in pannello sotto.

## 17.4 Dashboard Istruttore (tablet / desktop)

- Layout con sfondo nero, due colonne, card scure (`#111111`):
  - Header orizzontale con logo Time2Fit e nome istruttore.
  - Sidebar sinistra (solo desktop/tablet) con voci di menu (Testo bianco, hover verde):
    - Dashboard
    - Clienti
    - Schede
    - Esercizi
    - Agenda
    - Config
  - Colonna contenuto:
    - Card “Clienti attivi”.
    - Card “Prossimi appuntamenti”.
    - Tabella schede recenti (in desktop) che diventa card-list in mobile.

Tutta l’interfaccia deve mantenere:
- **Tema dark**, card su sfondo nero.
- Verde `#00ff00` per titoli e stati positivi.
- Giallo `#f9cc49` per bottoni principali.
- Rosso `#ed3833` per errori/alert.
- Blu `#44b5f8` per info/elementi “neutri/secondari”.

---
