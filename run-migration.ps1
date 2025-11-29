# Script per eseguire la migrazione del database
Write-Host "Esecuzione migrazione database..." -ForegroundColor Green
Write-Host ""

# Verifica se Docker è in esecuzione
$dockerRunning = docker ps 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERRORE: Docker non è in esecuzione!" -ForegroundColor Red
    Write-Host "Avvia Docker Desktop e riprova." -ForegroundColor Yellow
    exit 1
}

Write-Host "Esecuzione migrazione..." -ForegroundColor Cyan
docker exec -it gym_php php bin/console doctrine:migrations:migrate -n

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n✓ Migrazione completata con successo!" -ForegroundColor Green
} else {
    Write-Host "`n✗ Errore durante la migrazione" -ForegroundColor Red
    Write-Host "Verifica i log sopra per dettagli." -ForegroundColor Yellow
}


