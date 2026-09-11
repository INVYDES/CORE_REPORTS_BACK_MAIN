# Sincroniza BD local corereports -> Railway railway para que queden iguales
# Requiere: Railway host PUBLICO (TCP Proxy), no el private domain
param(
    [string]$RailwayHost = $env:RAILWAY_HOST,
    [string]$RailwayPort = "3306",
    [string]$RailwayUser = "root",
    [string]$RailwayPassword = "yesxYzqeRzWNOCmRvOabInFYpdPxxtVw",
    [string]$RailwayDatabase = "railway",
    [string]$LocalDatabase = "corereports",
    [string]$LocalHost = "127.0.0.1",
    [string]$LocalPort = "3306",
    [string]$LocalUser = "root",
    [string]$LocalPassword = "747375"
)

if (-not $RailwayHost) {
    Write-Host "ERROR: Pasa -RailwayHost (host PUBLICO TCP Proxy de Railway)" -ForegroundColor Red
    Write-Host "  Railway dashboard -> MySQL -> Settings -> Networking -> Public Networking" -ForegroundColor Yellow
    Write-Host "  Activa Public Networking si no esta activo, copia Host y Port" -ForegroundColor Yellow
    Write-Host '  Ejemplo: .\sync-db-to-railway.ps1 -RailwayHost centerbeam.proxy.rlwy.net -RailwayPort 51234' -ForegroundColor Yellow
    exit 1
}

$ErrorActionPreference = "Stop"

# Verifica mysqldump
$mysqldump = "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"
$mysql = "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"
if (-not (Test-Path $mysqldump)) { $mysqldump = "mysqldump" }
if (-not (Test-Path $mysql)) { $mysql = "mysql" }

Write-Host "=== Sync corereports -> Railway railway ===" -ForegroundColor Cyan
Write-Host "Local: $LocalHost`:$LocalPort/$LocalDatabase"
Write-Host "Railway: $RailwayHost`:$RailwayPort/$RailwayDatabase"

# 1. Dump local con views/procedures
$dumpFile = ".\BD\railway_dump_$(Get-Date -Format yyyyMMdd_HHmmss).sql"
Write-Host "`n[1/3] Dump local corereports..." -ForegroundColor Green
& $mysqldump -h $LocalHost -P $LocalPort -u $LocalUser -p"$LocalPassword" `
  --single-transaction --routines --triggers --events --hex-blob `
  --set-gtid-purged=OFF `
  $LocalDatabase > $dumpFile
if ($LASTEXITCODE -ne 0) { throw "mysqldump fallo" }
Write-Host "  Dump: $dumpFile ($([math]::Round((Get-Item $dumpFile).Length/1KB)) KB)"

# 2. Test conexion Railway
Write-Host "`n[2/3] Probando conexion Railway..." -ForegroundColor Green
& $mysql -h $RailwayHost -P $RailwayPort -u $RailwayUser -p"$RailwayPassword" -e "SELECT 1;" $RailwayDatabase 2>&1 | Out-String | Write-Host
if ($LASTEXITCODE -ne 0) { throw "No se pudo conectar a Railway. Verifica host/port/password y que Public Networking este activo." }

# 3. Import a Railway
Write-Host "`n[3/3] Importando a Railway (puede tardar)..." -ForegroundColor Green
# Desactivar FK checks para import limpio
Get-Content $dumpFile | & $mysql -h $RailwayHost -P $RailwayPort -u $RailwayUser -p"$RailwayPassword" $RailwayDatabase 2>&1 | Out-String | Write-Host
if ($LASTEXITCODE -ne 0) { throw "Import a Railway fallo" }

Write-Host "`nOK: BD local sincronizada con Railway" -ForegroundColor Green

# 4. Alternativa via Laravel migrate + seed directo en Railway (si prefieres no usar dump):
Write-Host "`nTip: Tambien puedes correr migraciones directo contra Railway:" -ForegroundColor Yellow
Write-Host '  $env:DB_HOST="tu-proxy.rlwy.net"; $env:DB_PORT="12345"; $env:DB_DATABASE="railway"; php artisan migrate --force --seed' -ForegroundColor Gray

# Verificacion
Write-Host "`nVerificando tablas en Railway:" -ForegroundColor Green
& $mysql -h $RailwayHost -P $RailwayPort -u $RailwayUser -p"$RailwayPassword" -e "SHOW TABLES;" $RailwayDatabase 2>&1 | Out-String | Write-Host
