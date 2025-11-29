# guideline.md — Time2Fit (FULL spec + Tech, ottimizzata per Cursor)

> **Obiettivo:** questo file è la **fonte unica di verità** per sviluppare il gestionale/SPA **Time2Fit** con **Symfony** (backend API) e **React + Vite** (frontend SPA).  
> Contiene sia la **specifica funzionale** (RF/RNF, UC, workflow, regole) sia le **linee guida tecniche** per:
> - ambiente **locale**: Symfony + Docker (solo DEV)
> - ambiente **produzione**: **senza Docker**, es. hosting Aruba (PHP + MySQL) con SPA buildata.

Host locale dev: `http://gestionale.local`  
phpMyAdmin dev (Docker): `http://localhost:8080/` (user: `walan`, pass: `Palestra$`)

---

# 1. CONTESTO & SCOPO MODULO

Time2Fit è un gestionale per **palestre** con forte focus su:

- Gestione **schede di allenamento** per clienti (isotoniche e funzionali).
- Gestione **istruttori** e Personal Trainer (PT).
- Tracciamento **progressi** (peso, serie, ripetizioni, ecc.).
- Agenda PT per appuntamenti con i clienti.
- Uso di **AI** per descrizioni esercizi e generazione GIF dimostrative.
- UX **mobile-first**, app stile **FitnessOnline**.

Ogni scheda di allenamento:

- è legata a una **tipologia di palestra** (`ISOTONICA`, `FUNZIONALE`)
- contiene un **Set di Esercizi** (ExerciseSet)
- ha **data inizio** e **data scadenza**
- resta consultabile anche dopo la scadenza
- la **scheda attiva** è sempre l’ultima creata/associata per un cliente

Gli amministratori ricevono una **email di promemoria** `N` giorni prima della scadenza delle schede (offset configurabile).

Backend: **Symfony 6.4 (API JSON)**.  
Frontend: **SPA React + Vite** (tema dark Time2Fit, mobile-first) buildata in locale e servita come statici.

---

# 2. ATTORI & RUOLI

## 2.1 Ruoli e permessi

- **Admin** (`ROLE_ADMIN`)
  - Gestisce set di esercizi, esercizi, istruttori, configurazioni.
  - Vede tutti i clienti, schede, progressi.
  - Riceve email promemoria scadenze.

- **Istruttore/Amministratore** (`ROLE_INSTRUCTOR_ADMIN`)
  - Come Admin per la parte operativa palestra.
  - Può anche essere Personal Trainer (PT).

- **Istruttore/Personal Trainer** (`ROLE_INSTRUCTOR_PT`)
  - Gestisce i propri clienti.
  - Crea e assegna schede.
  - Gestisce agenda e progressi.

- **Istruttore semplice** (`ROLE_INSTRUCTOR`)
  - Crea anagrafiche clienti.
  - Crea e assegna schede ai propri clienti.
  - Può prendere in gestione un cliente (takeover).

- **Cliente** (`ROLE_CLIENT`)
  - Si registra (solo questo ruolo ha self-signup).
  - Visualizza scheda attiva e storico.
  - Registra progressi.
  - Visualizza demo esercizi.

## 2.2 Policy di creazione utenti

- L’**Admin iniziale** si crea solo via comando CLI: `app:user:create-admin`.
- **Nessun form pubblico** per ruoli staff (Admin / Istruttori / PT).
- I **Clienti** possono registrarsi tramite form `/register` (con reCAPTCHA).
- Collegamento Cliente ↔ Schede:
  - L’istruttore può creare un **cliente anagrafico** con un’email.
  - Quando un cliente si registra con la **stessa email**, le schede e i dati già associati a quell’email vengono collegati all’account cliente.

---

# 3. REQUISITI FUNZIONALI (RF)

Elenco principale (riassunto):

- **RF1 – Tipologie palestra**
  - Tipologie fisse, enum `GymType`: `ISOTONICA`, `FUNZIONALE`.

- **RF2 – Set di esercizi (ExerciseSet + Exercise)**  
  - Creazione/gestione di **set di esercizi per tipologia palestra**.
  - Ogni Set ha un nome, tipologia, descrizione opzionale.
  - Gli esercizi di un Set sono gestiti **inline nella stessa pagina** (vedi override §19).

- **RF3 – Descrizione esercizio via AI**
  - Per ogni esercizio esiste un campo descrizione e un campo script/prompt.
  - Bottone **“Genera descrizione”**:
    - invia un prompt al backend AI,
    - sostituisce la descrizione con quella generata da AI.

- **RF4 – Media esercizio (GIF/video) via AI o upload**
  - Ogni esercizio ha una GIF/clip dimostrativa.
  - Opzioni:
    - upload manuale GIF/video,
    - registrazione video dal browser (se possibile),
    - bottone **“Genera GIF esercizio”** che legge la descrizione, chiama un servizio AI/web e genera/aggancia una GIF coerente.

- **RF5 – Gestione istruttori**
  - Admin/Istruttore-Admin: CRUD istruttori con:
    - nome, cognome, data nascita, data assunzione
    - avatar (upload o generato AI)
    - flag `isPersonalTrainer`
  - Istruttore-Admin può gestire altri istruttori.

- **RF6 – Gestione clienti (via istruttori)**
  - Istruttori/PT possono creare clienti anagrafici (associati a loro).
  - I clienti possono essere “presi in gestione” da altri istruttori tramite pulsante `Prendi in gestione` (takeover).

- **RF6a – Registrazione cliente (pubblica)**
  - Form `/register` per CLIENTI con:
    - email, password, nome, cognome, privacy, reCAPTCHA.
  - Alla prima autenticazione, eventuali schede associate alla stessa email vengono collegate.

- **RF7 – Schede di allenamento (TrainingPlan)**
  - Istruttori/PT creano schede con:
    - cliente, tipologia (`GymType`),
    - set di esercizi (`ExerciseSet`),
    - data inizio, data scadenza,
    - eventuali note.
  - Schede legate a **cliente** + **istruttore**.

- **RF7a – Scheda attiva**
  - La **scheda attiva** per un cliente è **sempre l’ultima scheda creata/associata** (isAttiva=true).
  - Tutte le schede precedenti diventano `isAttiva=false` ma rimangono nello storico.

- **RF8 – Promemoria scadenze**
  - Email a Admin (e/o Istruttore-Admin) `N` giorni prima della scadenza delle schede.
  - `N` configurabile in `Config` (es. `scheda.scadenza.email_offset_giorni`).

- **RF9 – Agenda PT**
  - Calendario appuntamenti tra istruttore/PT e clienti (giorno, settimana, mese).
  - Clic su appuntamento → apre scheda cliente e possibilità di registrare progressi.

- **RF10 – Progressi (ProgressLog)**
  - Cliente/PT possono registrare progressi per esercizio:
    - data, peso in kg, serie/ripetizioni/tempo effettivi, note.
  - Visualizzazione grafica (linea, card, ecc.).

- **RF11 – Area Cliente**
  - Il cliente loggato vede:
    - scheda attiva
    - storico schede
    - progressi
  - Se nessuna scheda: messaggio “Nessuna scheda attiva”.

- **RF12 – Takeover (“Prendi in gestione”)**
  - Un istruttore può prendere in gestione un cliente (anche se era di altro istruttore).
  - Viene aggiornato lo storico di associazione istruttore-cliente (`InstructorClient`).

- **RF13 – Configurazioni (Config key-value)**
  - Chiave/valore con parametri globali (es. AI endpoint/token, offset email, feature flag ffmpeg/video→gif).

---

# 4. REQUISITI NON FUNZIONALI (RNF)

- **RNF1 – Sicurezza**
  - Password con algoritmo `argon2id`.
  - Ruoli/permessi gestiti via Security Symfony (voti/role-hierarchy se necessario).
  - reCAPTCHA per registrazione cliente.
  - Rate limiting su login.

- **RNF2 – UX/UI**
  - SPA React responsiva, mobile-first, tema dark Time2Fit.
  - Navigazione bottom nav su mobile, sidebar su tablet/desktop.
  - Card-based, ispirata a app **FitnessOnline**.

- **RNF3 – Performance**
  - Paginazione per liste grandi.
  - Lazy-loading media.
  - Limiti su dimensione video/GIF (es. <= 25MB, <= 10s).

- **RNF4 – Logging & Audit**
  - Monolog per `app` e `security`.
  - Campi `createdAt`, `updatedAt`, `createdBy`, `updatedBy` su entità chiave.

- **RNF5 – Manutenibilità**
  - Tutte le modifiche schema DB tramite **Doctrine Migrations**.
  - Servizi separati per AI, media transcoding, reminder, settings.

---

# 5. USE CASE (UC) – SINTESI

- **UC1 – Gestire set di esercizi (Admin/Istruttore-Admin/PT)**  
  Creazione/modifica set di esercizi con esercizi inline (vedi §19).

- **UC2 – Generare descrizioni/media AI (Istruttore/PT/Admin)**  
  Uso bottoni “Genera descrizione” e “Genera GIF esercizio”.

- **UC3 – Gestire istruttori (Admin/Istruttore-Admin)**  
  CRUD istruttori con flag PT.

- **UC4 – Creare clienti anagrafici (Istruttore/PT)**  
  Creazione cliente e associazione a istruttore.

- **UC4a – Registrazione cliente (pubblica)**  
  Self-signup cliente con reCAPTCHA.

- **UC5 – Creare e assegnare schede (Istruttore/PT)**  
  Creazione scheda con set di esercizi e scadenza; scheda attiva = ultima.

- **UC6 – Promemoria scadenze (Sistema)**  
  Invio email `N` giorni prima della scadenza.

- **UC7 – Gestire agenda PT**  
  Appuntamenti con clienti, viste giornaliera/settimanale/mensile.

- **UC8 – Registrare progressi (Cliente/PT)**  
  Log dei progressi per esercizio.

- **UC9 – Visualizzare scheda e progressi (Cliente)**  
  Accesso a scheda attiva e storico.

- **UC10 – Takeover cliente (Istruttore/PT)**  
  Prendere in gestione un cliente non proprio.

---

# 6. MODELLO DATI (ENTITÀ CHIAVE)

Sintesi entità principali (nomi indicativi, ma suggeriti):

- `User`
  - `id, email (unique), password, roles (json), isActive, createdAt, updatedAt`

- `InstructorProfile`
  - `id, user (OneToOne User)`, `nome`, `cognome`, `dataNascita`, `dataAssunzione`  
  - `isPersonalTrainer (bool)`, `avatarUrl`, `avatarAiPrompt`

- `Client`
  - `id, user? (nullable OneToOne User)`, `email`, `nome`, `cognome`, `dataNascita?`, `createdAt`

- `InstructorClient`
  - `id, instructor (InstructorProfile)`, `client (Client)`  
  - `stato (ASSOCIATO|STORICO)`, `dataInizio`, `dataFine?`, `takenOverBy?`

- `GymType` (enum)
  - `ISOTONICA`, `FUNZIONALE` (rappresentato come enum PHP o string in DB).

- `Exercise`
  - `id`
  - `nome`, `descrizione`
  - `gymType (GymType)`
  - `mediaType (GIF|VIDEO)` (principalmente GIF)
  - `mediaUrl`
  - `aiPrompt?`, `aiProvider?`
  - `createdBy (User)`, `createdAt`, `updatedAt`

- `ExerciseSet`
  - `id`
  - `nome`
  - `descrizione?`
  - `gymType (GymType)`
  - relazione con `Exercise` (ManyToMany o entità ponte `ExerciseInSet`)

- `ExerciseInSet` (consigliata)
  - `id`
  - `exerciseSet`, `exercise`
  - `ordine`, `serie?`, `ripetizioni?`, `tempo?`, `note?`

- `TrainingPlan`
  - `id`
  - `client`, `instructor`
  - `gymType`
  - `exerciseSet`
  - `dataInizio`, `dataScadenza`
  - `isAttiva (bool)`
  - `stato (ATTIVO|SCADUTO|ARCHIVIATO)`
  - `note?`

- `TrainingPlanExercise`
  - `id`
  - `trainingPlan`, `exercise`
  - `ordine`
  - parametri previsti (serie, ripetizioni, tempo target, ecc.).

- `ProgressLog`
  - `id`
  - `trainingPlanExercise`
  - `autore (CLIENTE|PT)`
  - `data`
  - `pesoKg?`, `ripetizioniEff?`, `tempoEff?`, `note?`

- `Appointment`
  - `id`
  - `instructor`, `client`
  - `inizio`, `fine`
  - `titolo`, `note?`, `location?`

- `Config`
  - `id`
  - `chiave (unique)`
  - `valore (string)`
  - `descrizione?`

---

# 7. ARCHITETTURA GENERALE

## 7.1 Backend

- **Symfony 6.4 LTS** su PHP 8.3.
- Autenticazione:
  - Variante A: sessione + cookie (SPA e API stesso dominio).
  - Variante B: JWT (`Authorization: Bearer <token>`).
- Rotte API:
  - prefisso `/api`
  - tutte le risposte JSON (Symfony Serializer / JsonResponse).
- Validazioni: Symfony Validator + DTO o Request classes dove utile.
- Error handling: eccezioni tradotte in JSON con codici HTTP corretti.

## 7.2 Frontend SPA (React + Vite)

- Directory `frontend/` con progetto React + Vite.
- Routing lato client con React Router.
- Consumo API via axios client (`src/api/http.js`).
- Gestione auth via `AuthContext` + `useAuth`.
- Tema dark Time2Fit (vedi §16).
- Layout mobile-first, ispirato a app **FitnessOnline**.

---

# 8. API (DESIGN AD ALTO LIVELLO)

Esempi endpoint (non esaustivi, ma guida per implementazione):

- **Auth**
  - `POST /api/login` → login utente, set cookie/sessione o restituisce JWT.
  - `POST /api/logout`
  - `GET /api/me` → info utente loggato + ruoli.

- **Clienti**
  - `GET /api/clients` (lista, filtrabile).
  - `POST /api/clients` (creazione da istruttore).
  - `GET /api/clients/{id}`.
  - `POST /api/clients/{id}/takeover`.

- **Istruttori**
  - `GET /api/instructors`
  - `POST /api/instructors`
  - `PUT /api/instructors/{id}`
  - `DELETE /api/instructors/{id}`

- **ExerciseSet + Exercise (vedi override §19)**
  - `GET /api/exercise-sets`
  - `GET /api/exercise-sets/{id}`
  - `POST /api/exercise-sets`  
    → crea Set + esercizi inline.
  - `PUT /api/exercise-sets/{id}`  
    → aggiorna Set + esercizi inline (create/update/delete).
  - `DELETE /api/exercise-sets/{id}`

  - **AI & media**
    - `POST /api/exercises/{id}/generate-description`
    - `POST /api/exercises/{id}/generate-gif`

- **Schede (TrainingPlan)**
  - `GET /api/clients/{id}/plans`
  - `GET /api/clients/{id}/plans/active`
  - `POST /api/clients/{id}/plans`
  - `PUT /api/plans/{id}`
  - `GET /api/plans/{id}`

- **Progressi**
  - `GET /api/plans/{planId}/progress`
  - `POST /api/training-plan-exercises/{id}/progress`

- **Agenda PT**
  - `GET /api/appointments`
  - `POST /api/appointments`
  - `PUT /api/appointments/{id}`
  - `DELETE /api/appointments/{id}`

- **Config**
  - `GET /api/config/{key}`
  - `PUT /api/config/{key}` (per Admin/Istr-Admin).

---

# 9. DOCKER (SOLO AMBIENTE LOCALE)

> Docker viene usato **solo in locale (DEV)**.  
> In produzione (es. Aruba) il progetto gira senza Docker.

## 9.1 Docker Compose (dev)

Servizi principali:

- `db`: MySQL 8 (DB `gestionale`, user `walan`, pass `Palestra$`)
- `php`: PHP 8.3-fpm con estensioni necessarie (intl, pdo_mysql, ffmpeg, ecc.)
- `nginx`: reverse proxy su `gestionale.local` → Symfony.
- `phpmyadmin`: gestione DB in dev (`localhost:8080`).
- `cron`: sidecar con cron che esegue `app:schede:promemoria` ogni giorno.

## 9.2 Setup dev

1. Aggiungi a `/etc/hosts`:
   ```
   127.0.0.1   gestionale.local
   ```
2. `docker compose build`
3. `docker compose up -d`
4. `docker exec -it gym_php bash`
5. `composer install`
6. `.env`:
   ```env
   DATABASE_URL="mysql://walan:Palestra%24@db:3306/gestionale?charset=utf8mb4"
   MAILER_DSN=null://localhost
   ```
7. `php bin/console doctrine:migrations:migrate -n`
8. `php bin/console app:seed:config`
9. `php bin/console app:user:create-admin admin@gestionale.local SuperPasswordSicura!`
10. `cd frontend && npm install && npm run dev`

---

# 10. PRODUZIONE SENZA DOCKER (ES. ARUBA)

## 10.1 Backend Symfony su Aruba

1. Build locale prod:
   ```bash
   composer install --no-dev --optimize-autoloader
   php bin/console cache:clear --env=prod
   ```
2. Upload progetto su hosting (FTP/SFTP o Git).
3. Config docroot su `public/`.
4. Configurazione DB (`.env.prod` o variabili ambiente):
   ```env
   DATABASE_URL="mysql://UTENTE:PASS@HOST:3306/NOME_DB?charset=utf8mb4"
   APP_ENV=prod
   APP_DEBUG=0
   MAILER_DSN=smtp://...
   ```
5. Migrations:
   - Se SSH: `php bin/console doctrine:migrations:migrate -n --env=prod`
   - Altrimenti: esportare schema e importare via phpMyAdmin.

## 10.2 Frontend SPA in produzione

1. Build SPA:
   ```bash
   cd frontend
   npm install
   npm run build
   ```
2. Copia contenuto `frontend/dist` in `public/app` (o docroot scelto).
3. Configurare routing (Apache `.htaccess` esempio):

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

## 10.3 Cron su Aruba

- Usare pannello Aruba → Cron.
- Eseguire giornalmente:
  ```bash
  php /percorso/assoluto/bin/console app:schede:promemoria --env=prod
  ```
- In alternativa, endpoint HTTP dedicato (meno consigliato).

## 10.4 ffmpeg

- Se ffmpeg non disponibile in produzione:
  - usare `Config` per disabilitare conversione video→gif.
  - consentire solo upload GIF o GIF generate via AI.

---

# 11. BRANDING, MOBILE-FIRST & DESIGN SYSTEM TIME2FIT

## 11.1 Nome & logo

- Nome app: **Time2Fit**
- Logo: file `logo-time2fit.png` nella root progetto (es. `/logo-time2fit.png`)
- In React:
  ```jsx
  import logo from '/logo-time2fit.png';
  ```

## 11.2 Tema dark Time2Fit (palette definitiva)

Questa palette **sostituisce tutte le indicazioni colore precedenti**:

- **Sfondo principale**: `#000000` (nero)
- **Titoli (h1, h2, h3, ecc.)**: `#00ff00` (verde acceso)
- **Testo standard (body)**: `#FFFFFF` (bianco)
- **Link `<a>`**:
  - normale: `#FFFFFF`
  - hover: `#00ff00`
- **Bottoni principali**: `#f9cc49`
- **Colori extra / accent**:
  - `#ed3833` (error/alert)
  - `#44b5f8` (info/highlight)
- **Font base**: `"Lato", sans-serif`

Esempio CSS globale (SPA React):

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

## 11.3 Mobile-first & breakpoints

- La maggior parte degli utenti usa **smartphone/tablet**.
- Layout **mobile-first**:
  - su mobile: una colonna, bottom navigation, form verticali.
  - su tablet/desktop: 2 colonne per riga dove sensato.

Breakpoints suggeriti:

- `<480px` → smartphone piccoli.
- `480–768px` → smartphone grandi / tablet verticali.
- `>1024px` → desktop / iPad landscape.

Requisiti UX:

- Niente overflow orizzontale.
- Hit area touch ≥ 44px.
- Tabelle convertite in **card responsive** sotto 768px.
- Sidebar → bottom nav/hamburger su mobile.

## 11.4 Stile ispirato a FitnessOnline

UI ispirata a app **FitnessOnline** (card, layout pulito, bottom nav), ma:

- tema dark Time2Fit (sfondo nero, titoli verde, accenti giallo/rosso/blu).
- card arrotondate, spazio bianco (nero) abbondante.
- bottom nav persistente su mobile.
- dashboard cliente con card scheda, progressi, pulsante “+ Progresso”.
- liste esercizi come card con gif e info.

---

# 12. STRUTTURA SPA REACT + VITE

Cartella `/frontend`:

```text
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

Esempi:

- `src/api/http.js`:

```js
import axios from "axios";

const api = axios.create({
  baseURL: "/api",
  withCredentials: true,
});

export default api;
```

- `src/router/index.jsx`:

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

---

# 13. MOCKUP GRAFICO TIME2FIT (STILE FITNESSONLINE)

## 13.1 Dashboard Cliente (mobile)

- Header con logo Time2Fit (sinistra) + avatar (destra).
- Testo:
  - `Ciao, [Nome] 👋` (verde, bold).
  - `Ecco la tua scheda attiva di oggi` (bianco).
- Card “Scheda attiva”:
  - full-width, `.t2f-card`.
  - titolo scheda in verde, info in grigio chiaro.
  - badge `ATTIVA` (sfondo giallo, testo nero).
- Sezione `Allenamento di oggi` con 2–3 card esercizio (thumbnail GIF + info).
- Card progressi con mini grafico (linea blu/giallo).
- FAB “+ Progresso” in basso a destra (giallo, icona +).

Bottom nav:

- Sfondo nero/scuro.
- Icone bianche, icona attiva verde.
- Tab: Home, Scheda, Agenda, Profilo.

## 13.2 PlanView – Scheda (mobile)

- Header: back + `Scheda attiva`.
- Pill tipologia (ISOTONICA/FUNZIONALE).
- Card riassunto scheda.
- Lista esercizi in card verticali con GIF, nome, parametri.
- Tap su esercizio → dettaglio con GIF grande, descrizione, progressi.

## 13.3 Agenda PT (mobile)

- Header `Agenda PT` + bottone `+` giallo.
- Tab `Oggi / Settimana / Mese`.
- Vista `Oggi`:
  - timeline verticale, card appuntamento (cliente, tipo, orario).
- Vista `Mese`:
  - calendario minimal, giorni con puntini verdi per appuntamenti.

## 13.4 Dashboard Istruttore (tablet/desktop)

- Header con logo Time2Fit e nome istruttore.
- Sidebar con menu (Dashboard, Clienti, Schede, Esercizi, Agenda, Config).
- Contenuto in 2 colonne con card (clienti attivi, prossimi appuntamenti, schede recenti).

---

# 14. OVERRIDE – FLUSSO CREAZIONE SET DI ESERCIZI + ESERCIZI (UI + LOGICA)

Questa sezione **sostituisce** eventuali indicazioni precedenti su gestione Exercise/ExerciseSet.

## 14.1 Concetto chiave

- La **creazione di un Set di Esercizi** è un flusso **guidato in UNA SOLA PAGINA**.
- Flusso:
  1. Inserimento **nome set**, **tipologia palestra (GymType)**, **descrizione set**.
  2. Nella **stessa pagina**, appare la sezione **“Esercizi del set”**.
  3. L’utente crea uno o più esercizi **inline**, associati direttamente al Set.
- Non deve esistere un flusso principale in cui:
  - si crea l’esercizio separato,
  - poi si assegna il set tramite dropdown in un’altra pagina.

> Modello dati può mantenere `Exercise` come entità riutilizzabile, ma la UX principale di creazione/modifica è **dal Set**.

## 14.2 Layout pagina Set (2 colonne per riga)

### Blocco 1 – Dati Set (prima riga, 2 colonne)

- Colonna sinistra:
  - `Nome set` (obbligatorio)
  - `Tipologia palestra` (select: Isotonica/Funzionale)
- Colonna destra:
  - `Descrizione set` (textarea, opzionale)

### Blocco 2 – Esercizi del Set (secondo div)

Subito sotto, blocco `Esercizi del set` con:

- Titolo: `Esercizi del set`
- Bottone `Aggiungi esercizio`.
- Per ogni esercizio, card in 2 colonne (>= tablet; 1 colonna su mobile).

**Colonna sinistra (campi base):**

- Nome esercizio.
- Parametri base: serie, ripetizioni, tempo, note brevi.

**Colonna destra (descrizione + media):**

- Campo `Script/Prompt` (testo breve).
- Campo `Descrizione esercizio` (textarea).
- Bottone **“Genera descrizione”**:
  - prende `Script/Prompt` (se esiste) o la descrizione corrente.
  - chiama API AI backend.
  - **sostituisce** la descrizione con quella generata.

- Sezione Media:
  - Upload video/GIF.
  - (Se supportato) registrazione video dal browser (MediaRecorder).
  - Bottone **“Genera GIF esercizio”**:
    - legge descrizione esercizio.
    - chiama API backend che interroga servizi AI/web.
    - genera/aggancia una GIF adatta (aggiorna `mediaUrl`).

## 14.3 Salvataggio

- Salvataggio Set deve:
  - creare/aggiornare `ExerciseSet`,
  - creare/aggiornare/eliminare esercizi associati inline (e entità ponte `ExerciseInSet` se presente).
- Non è ammesso costringere l’utente a:
  - passare da una pagina generica esercizi,
  - scegliere un set da un dropdown per associarlo.

## 14.4 Impatto su API / React

Backend:

- `POST /api/exercise-sets`:
  - payload: dati Set + array esercizi (nome, parametri, descrizione, media info).
- `PUT /api/exercise-sets/{id}`:
  - payload simile, con ID per aggiornare/eliminare/aggiungere esercizi.

- Endpoint AI/media:
  - `POST /api/exercises/{id}/generate-description`
  - `POST /api/exercises/{id}/generate-gif`

Frontend:

- Pagina `ExerciseSetForm`:
  - form in 2 colonne per riga (>= tablet), card esercizi inline.
  - ogni card esercizio ha interfaccia per:
    - prompt + descrizione + “Genera descrizione”,
    - upload/registrazione media + “Genera GIF esercizio”.

---

# 15. TASK TECNICI PER CURSOR (BACKEND + FRONTEND)

1. Implementare tutte le **entità** e **migrazioni** (vedi §6).  
2. Implementare servizi:
   - `AiDescriptionService`
   - `AiMediaService` (facoltativo / stub)
   - `MediaTranscodingService` (ffmpeg)
   - `ReminderService`
   - `SettingsService`
3. Implementare command:
   - `app:seed:config`
   - `app:user:create-admin`
   - `app:schede:promemoria`
4. Implementare API controller `/api/...`:
   - auth, clienti, istruttori, exercise-set/esercizi, piani, progressi, agenda, config.
5. Implementare SPA React in `/frontend`:
   - struttura indicata,
   - tema dark Time2Fit,
   - mockup/UX FitnessOnline-like,
   - flusso Set + Esercizi inline come da override.
6. Implementare integrazione full:
   - login, registrazione cliente, scheda attiva, progressi, agenda PT, takeover, reminder.

---

# 16. PROMPT FINALE PER CURSOR

Quando il repository contiene **questa** `guideline.md` e (idealmente) `logo-time2fit.png` nella root, usare questo prompt in Cursor:

"""
Leggi attentamente il file guideline.md nella root del progetto. È la fonte unica di verità per il gestionale palestra **Time2Fit**.

Obiettivi:

1. Implementare il **backend Symfony 6.4 (PHP 8.3)** come **API JSON**:
   - Rispetta TUTTE le entità, i ruoli, i requisiti funzionali / non funzionali, i use case, i workflow e le regole di business descritti in guideline.md.
   - Esponi le funzionalità tramite endpoint REST sotto il prefisso `/api`.
   - Implementa un sistema di autenticazione sicuro (sessione + cookie same-origin oppure JWT) e usalo in modo coerente in tutta l’applicazione.
   - Implementa i servizi descritti (AI descrizioni, media transcoding video→gif, reminder scadenze, settings) e i command (`app:seed:config`, `app:user:create-admin`, `app:schede:promemoria`).
   - Usa esclusivamente Doctrine Migrations per gestire lo schema del database.

2. Implementare la **SPA React + Vite** in cartella `/frontend`:
   - Usa la struttura di cartelle indicata in guideline.md (api/, components/, pages/, router/, hooks/, contexts/, assets/, App.jsx, main.jsx).
   - Applica il **tema dark Time2Fit**: sfondo nero, titoli verde `#00ff00`, testo bianco, link bianchi con hover verde, bottoni `#f9cc49`, colori extra `#ed3833` e `#44b5f8`, font `Lato, sans-serif`.
   - Usa il logo `logo-time2fit.png` presente in root importandolo nel layout principale React.
   - Progetta TUTTE le schermate in modalità **mobile-first e completamente responsive**, seguendo i mockup grafici Time2Fit in stile FitnessOnline (Dashboard Cliente, Scheda, Agenda PT, Dashboard Istruttore, Dashboard Admin).

3. Integrazione backend/frontend:
   - Crea un client API standard in `frontend/src/api/http.js` (axios) con `baseURL: "/api"`.
   - Implementa `AuthContext` + `useAuth()` per gestire login, logout e stato utente.
   - Implementa le pagine principali (Login, Registrazione, Dashboard Cliente, PlanView, Progressi, Dashboard Istruttore, Dashboard Admin, Agenda PT) consumando le API Symfony.
   - Assicurati che i flussi chiave descritti (creazione scheda, scheda attiva, takeover, registrazione progressi, promemoria scadenze) funzionino end-to-end.

4. Flusso Set di Esercizi + Esercizi (override):
   - Rileggi con attenzione la sezione “OVERRIDE – FLUSSO CREAZIONE SET DI ESERCIZI + ESERCIZI (UI + LOGICA)” in guideline.md.
   - Adegua il backend (endpoint `/api/exercise-sets` e relativi) e il frontend (pagina `ExerciseSetForm`) affinché:
     - la creazione e modifica di un Set permetta la gestione degli esercizi **inline nella stessa pagina**, senza dropdown di scelta set,
     - ogni esercizio abbia campo descrizione + prompt + pulsante “Genera descrizione” (AI),
     - ogni esercizio abbia sezione media con upload/registrazione e pulsante “Genera GIF esercizio” (AI/web search),
     - il layout usi 2 colonne per riga su tablet/desktop e 1 colonna su mobile.
## AI – Generazione descrizioni esercizi (STRUTTURA FISSA)

Per ogni esercizio esiste:

- un campo “nome” (es. `Panca piana con bilanciere`)
- eventualmente un campo “muscolo target” (es. `pettorali`)
- un campo “prompt/script AI” opzionale inserito dall’utente

Quando l’utente preme il bottone **“Genera descrizione”**, il backend NON deve mandare all’AI solo il testo libero, ma deve usare SEMPRE un **prompt strutturato** con questa forma:

> Prompt BASE (template):

```text
Genera la spiegazione dettagliata dell'esercizio di allenamento seguente:

Nome esercizio: {{nome_esercizio}}
Muscoli target: {{muscoli_target}}
Livello utente: principiante/intermedio/avanzato
Contesto aggiuntivo (opzionale):
{{prompt_personalizzato}}

REQUISITI OBBLIGATORI:
- descrivi la posizione iniziale in modo dettagliato
- descrivi l'esecuzione passo passo
- indica la respirazione corretta
- elenca gli errori comuni da evitare
- elenca i muscoli coinvolti realmente
- NON usare frasi generiche tipo "mantieni la postura adeguata"
- NON dare consigli vaghi
- sii tecnico, accurato e pratico
- tono professionale ma chiaro

FORMATTAZIONE RICHIESTA (usa sempre questa struttura):

Posizione iniziale:
- ...

Esecuzione passo passo:
1. ...
2. ...
3. ...

Respirazione:
- ...

Errori comuni:
- ...

Muscoli coinvolti:
- ...

5. Build e deploy:
   - Prevedi in `frontend/package.json` gli script `npm run dev` e `npm run build` (Vite).
   - Il build in produzione deve finire in `frontend/dist` e la guideline prevede che i file vengano poi copiati in `/public/app` sul server (es. Aruba).

Operatività:
- Prima implementa e stabilizza il **backend API Symfony** (entità, repository, servizi, controller, command, migrazioni).
- Poi implementa la **SPA React Time2Fit** seguendo la palette, il tema dark e i mockup definiti.
- Quando hai dubbi di UX/UI o di business, NON inventare: rileggi guideline.md e mantieniti aderente alla specifica.

"""

---

Fine linea guida completa Time2Fit.
