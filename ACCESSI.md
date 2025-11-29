# 🔐 Accessi Time2Fit - Ambiente Locale

## ✅ Sistema Operativo

Tutti i servizi sono attivi e funzionanti!

## 🌐 URL Accesso

- **Frontend SPA (Dev Server)**: http://time2fit.local ⭐ **PRINCIPALE - APRI QUI!**
- **Backend API**: http://time2fit.local/api
- **Frontend SPA (Build)**: http://time2fit.local/app (quando buildato)
- **phpMyAdmin**: http://localhost:8080
- **Mailcatcher**: http://localhost:1080

✅ **Frontend attivo e funzionante!** Il dev server Vite è in esecuzione nel container `gym_node` e viene servito tramite Nginx su `time2fit.local`.

## 👤 Credenziali Admin

**Email**: `admin@time2fit.local`  
**Password**: `Admin123!`

## 🗄️ Database MySQL

- **Host**: `localhost:3306` (o `db` da dentro Docker)
- **Database**: `gestionale`
- **Username**: `walan`
- **Password**: `Palestra$`
- **phpMyAdmin**: http://localhost:8080 (stesse credenziali)

## 📧 Mailcatcher (Email Testing)

- **Web Interface**: http://localhost:1080
- **SMTP**: `localhost:1025`

## ✅ Frontend (Docker)

Il frontend gira in un container Docker Node.js:
- **Container**: `gym_node`
- **URL**: http://localhost:5173
- **Hot reload**: Attivo automaticamente
- **Proxy API**: Configurato verso http://time2fit.local/api

Se il container non è avviato:
```bash
docker compose up -d node
docker logs -f gym_node
```

## 🚀 Prossimi Passi

1. **Test Login API**:
   ```bash
   # Via browser o Postman
   POST http://time2fit.local/api/login
   Body (form-data):
   - email: admin@time2fit.local
   - password: Admin123!
   ```

2. **Frontend Dev Server** (già avviato in background):
   - Apri: http://localhost:5173
   - Il dev server Vite fa proxy delle API a http://time2fit.local/api
   - Hot reload attivo: modifica i file e vedi le modifiche in tempo reale

3. **Build Frontend per Produzione**:
   ```bash
   cd frontend
   npm run build
   # I file saranno in frontend/dist/
   # Copiali in public/app/ per servire la SPA
   ```

## 📝 Note

- Il database è stato creato con successo
- Le configurazioni iniziali sono state seedate
- L'utente admin è stato creato
- Tutti i container Docker sono attivi

