# Deploy CsRecords a Cloud Run con BD Railway
# Uso: .\deploy.ps1 -RailwayHost "centerbeam.proxy.rlwy.net" -RailwayPort "12345"
param(
    [string]$RailwayHost = $env:RAILWAY_HOST,
    [string]$RailwayPort = "3306",
    [string]$ProjectId = "csrecords-508023",
    [string]$Region = "us-central1",
    [string]$AppKey = "base64:KCvNRVjATwlfjUkj9M/n6bbmhQyRkm9cqqlrfjBEOaw="
)

if (-not $RailwayHost) {
    Write-Host "ERROR: Debes pasar -RailwayHost con el host PUBLICO de Railway (TCP Proxy)" -ForegroundColor Red
    Write-Host "  Ve a Railway -> tu servicio MySQL -> Settings -> Networking -> Public Networking -> TCP Proxy" -ForegroundColor Yellow
    Write-Host "  Ejemplo: .\deploy.ps1 -RailwayHost centerbeam.proxy.rlwy.net -RailwayPort 51234" -ForegroundColor Yellow
    exit 1
}

$ErrorActionPreference = "Stop"

Write-Host "=== CsRecords Cloud Run Deploy ===" -ForegroundColor Cyan
Write-Host "Project: $ProjectId  Region: $Region"
Write-Host "Railway: $RailwayHost`:$RailwayPort  DB: railway"

gcloud config set project $ProjectId | Out-Null

$BackendImage = "us-central1-docker.pkg.dev/$ProjectId/csrecords/csrecords:latest"
$FrontendImage = "us-central1-docker.pkg.dev/$ProjectId/csrecords/csrecords-front:latest"

# Build backend
Write-Host "`n[1/4] Building backend..." -ForegroundColor Green
gcloud builds submit --config cloudbuild.yaml `
  --substitutions="_DB_HOST=$RailwayHost,_DB_PORT=$RailwayPort,_DB_DATABASE=railway,_DB_USERNAME=root,_DB_PASSWORD=yesxYzqeRzWNOCmRvOabInFYpdPxxtVw,_APP_KEY=$AppKey,_APP_URL=https://csrecords-$ProjectId.a.run.app,_FRONTEND_URL=https://csrecords-front-$ProjectId.a.run.app,_CORS_ALLOWED_ORIGINS=https://csrecords-front-$ProjectId.a.run.app,_VITE_API_URL=https://csrecords-$ProjectId.a.run.app/api/v1" `
  --region $Region

# Alternativa single-service deploy (si cloudbuild.yaml no se usa, deploy manual):
# Write-Host "`n[2/4] Deploy backend..."
# gcloud run deploy csrecords --image $BackendImage --region $Region --allow-unauthenticated --port 8080 --memory 1Gi --set-env-vars "APP_ENV=production,APP_DEBUG=false,DB_CONNECTION=mysql,DB_HOST=$RailwayHost,DB_PORT=$RailwayPort,DB_DATABASE=railway,DB_USERNAME=root,DB_PASSWORD=yesxYzqeRzWNOCmRvOabInFYpdPxxtVw,APP_KEY=$AppKey"

Write-Host "`nDone! Verifica:" -ForegroundColor Cyan
gcloud run services list --region $Region
Write-Host "`nBackend URL:" -ForegroundColor Yellow
gcloud run services describe csrecords --region $Region --format "value(status.url)"
Write-Host "Frontend URL:" -ForegroundColor Yellow
gcloud run services describe csrecords-front --region $Region --format "value(status.url)"
