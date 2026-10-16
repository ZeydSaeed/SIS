# Starts the app, runs the k6 load test with the live web dashboard, then stops the app.
# Dashboard: http://127.0.0.1:5665 (opens automatically)
param(
    [string]$Script = "tests\load\public-pages.js",
    [string]$BaseUrl = "http://127.0.0.1:8000",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$k6 = (Get-Command k6 -ErrorAction SilentlyContinue).Source
if (-not $k6) { $k6 = "C:\Program Files\k6\k6.exe" }
if (-not (Test-Path $k6)) { Write-Host "k6 not found. Run: winget install k6 --source winget"; Read-Host "Press Enter"; exit 1 }

Write-Host "Starting app on port $Port ..."
$server = Start-Process php -ArgumentList "artisan", "serve", "--host=127.0.0.1", "--port=$Port" -WindowStyle Hidden -PassThru
Start-Sleep -Seconds 4

try {
    $env:K6_WEB_DASHBOARD = "true"
    $env:K6_WEB_DASHBOARD_EXPORT = Join-Path $root "tests\load\report.html"
    Start-Job { Start-Sleep -Seconds 3; Start-Process "http://127.0.0.1:5665" } | Out-Null

    & $k6 run -e BASE_URL=$BaseUrl --summary-export (Join-Path $root "tests\load\summary.json") $Script

    if (Test-Path $env:K6_WEB_DASHBOARD_EXPORT) { Start-Process $env:K6_WEB_DASHBOARD_EXPORT }
}
finally {
    if ($server -and -not $server.HasExited) { Stop-Process -Id $server.Id -Force }
    Write-Host "App stopped."
    Read-Host "Press Enter to close"
}
