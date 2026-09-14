# Install SIS Desktop launcher as a Windows .lnk using Edge/Chrome app mode when available.
# Browser host only — NOT Electron/Tauri/Blazor (RUNTIME-CONTRACT HOLD).
$ErrorActionPreference = 'Stop'
$desktop = [Environment]::GetFolderPath('Desktop')
$url = 'http://sis.test/hub?desktop=1'
$lnkPath = Join-Path $desktop 'SIS Operational Hub.lnk'
$urlPath = Join-Path $desktop 'SIS Operational Hub.url'

$candidates = @(
    "$env:ProgramFiles (x86)\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "$env:ProgramFiles (x86)\Google\Chrome\Application\chrome.exe"
)
$browser = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1

$wsh = New-Object -ComObject WScript.Shell
$shortcut = $wsh.CreateShortcut($lnkPath)
if ($browser) {
    $shortcut.TargetPath = $browser
    $shortcut.Arguments = "--app=$url"
    $shortcut.WorkingDirectory = Split-Path $browser
} else {
    $shortcut.TargetPath = $url
}
$shortcut.Description = 'SIS Operational Hub (guest desktop shell)'
$shortcut.Save()

@"
[InternetShortcut]
URL=$url
"@ | Set-Content -Path $urlPath -Encoding ASCII

Write-Host "Installed .lnk: $lnkPath"
Write-Host "Installed .url: $urlPath"
Write-Host "Browser: $(if ($browser) { $browser } else { 'default handler' })"
Write-Host "URL: $url"
