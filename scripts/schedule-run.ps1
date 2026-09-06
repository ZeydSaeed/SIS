# Run Laravel scheduler every minute (Windows Task Scheduler / dev)
# Usage: powershell -ExecutionPolicy Bypass -File scripts/schedule-run.ps1

$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

php artisan schedule:run --verbose
