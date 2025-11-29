# Script per pulire la cache React/Vite
Write-Host "Pulizia cache React/Vite..." -ForegroundColor Green

# Rimuovi cache Vite
if (Test-Path "frontend\node_modules\.vite") {
    Remove-Item -Recurse -Force "frontend\node_modules\.vite"
    Write-Host "✓ Cache .vite rimossa" -ForegroundColor Green
} else {
    Write-Host "  Cartella .vite non trovata" -ForegroundColor Yellow
}

# Rimuovi dist
if (Test-Path "frontend\dist") {
    Remove-Item -Recurse -Force "frontend\dist"
    Write-Host "✓ Cartella dist rimossa" -ForegroundColor Green
} else {
    Write-Host "  Cartella dist non trovata" -ForegroundColor Yellow
}

# Rimuovi .vite nella root di frontend
if (Test-Path "frontend\.vite") {
    Remove-Item -Recurse -Force "frontend\.vite"
    Write-Host "✓ Cache .vite (root) rimossa" -ForegroundColor Green
} else {
    Write-Host "  Cartella .vite (root) non trovata" -ForegroundColor Yellow
}

Write-Host "`nPulizia completata!" -ForegroundColor Green
Write-Host "Per pulire anche la cache npm, esegui: cd frontend && npm cache clean --force" -ForegroundColor Cyan


