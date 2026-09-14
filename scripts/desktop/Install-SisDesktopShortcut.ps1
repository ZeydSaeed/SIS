# Install SIS Desktop launcher as a Windows .lnk using Edge/Chrome app mode when available.
# Browser host only — NOT Electron/Tauri/Blazor (RUNTIME-CONTRACT HOLD).
$ErrorActionPreference = 'Stop'
$desktop = [Environment]::GetFolderPath('Desktop')
$url = 'http://sis.test/hub?desktop=1'
$displayName = 'نظام معلومات الطالب'
$lnkPath = Join-Path $desktop "$displayName.lnk"
$urlPath = Join-Path $desktop "$displayName.url"
$legacyNames = @('SIS Operational Hub.lnk', 'SIS Operational Hub.url')

foreach ($legacy in $legacyNames) {
    $legacyPath = Join-Path $desktop $legacy
    if (Test-Path $legacyPath) {
        Remove-Item -LiteralPath $legacyPath -Force
    }
}

$candidates = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe"
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
$shortcut.Description = 'مساحة العمل التشغيلية — نظام معلومات الطالب (ضيف)'
$shortcut.Save()

@"
[InternetShortcut]
URL=$url
"@ | Set-Content -Path $urlPath -Encoding ASCII

Write-Host "Installed .lnk: $lnkPath"
Write-Host "Installed .url: $urlPath"
Write-Host "Browser: $(if ($browser) { $browser } else { 'default handler' })"
Write-Host "URL: $url"
