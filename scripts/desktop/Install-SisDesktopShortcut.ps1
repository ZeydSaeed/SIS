# Install SIS Operational Hub shortcut on the current user Desktop.
# Hosts web UI only — no Electron/Tauri/Blazor (RUNTIME-CONTRACT HOLD).
$ErrorActionPreference = 'Stop'
$desktop = [Environment]::GetFolderPath('Desktop')
$targetUrl = 'http://sis.test/hub?desktop=1'
$shortcutPath = Join-Path $desktop 'SIS Operational Hub.url'
$repoRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$iconCandidate = Join-Path $repoRoot 'assets\desktop\sis-hub.svg'

@"
[InternetShortcut]
URL=$targetUrl
"@ | Set-Content -Path $shortcutPath -Encoding ASCII

Write-Host "Installed: $shortcutPath"
Write-Host "Opens: $targetUrl"
if (Test-Path $iconCandidate) {
  Write-Host "Icon asset: $iconCandidate (browser uses default .url icon on Windows)"
}
