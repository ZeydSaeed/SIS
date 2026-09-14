# Install SIS Desktop launcher as a Windows .lnk using Edge/Chrome app mode when available.
# Browser host only — NOT Electron/Tauri/Blazor (RUNTIME-CONTRACT HOLD).
$ErrorActionPreference = 'Stop'
$desktop = [Environment]::GetFolderPath('Desktop')
$url = 'http://sis.test/hub?desktop=1'
$displayName = 'نظام معلومات الطالب'
$tempLnk = Join-Path $desktop 'SIS-Hub-Temp.lnk'
$lnkPath = Join-Path $desktop ($displayName + '.lnk')
$urlPath = Join-Path $desktop ($displayName + '.url')
$legacyNames = @(
    'SIS Operational Hub.lnk',
    'SIS Operational Hub.url',
    'SIS-Hub-Temp.lnk',
    'SIS.lnk'
)

foreach ($legacy in $legacyNames) {
    $legacyPath = Join-Path $desktop $legacy
    if (Test-Path -LiteralPath $legacyPath) {
        Remove-Item -LiteralPath $legacyPath -Force
    }
}
if (Test-Path -LiteralPath $lnkPath) {
    Remove-Item -LiteralPath $lnkPath -Force
}
if (Test-Path -LiteralPath $urlPath) {
    Remove-Item -LiteralPath $urlPath -Force
}

$candidates = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe"
)
$browser = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1

# WScript.Shell cannot always Save() directly to Arabic filenames — write ASCII then rename.
$wsh = New-Object -ComObject WScript.Shell
$shortcut = $wsh.CreateShortcut($tempLnk)
if ($browser) {
    $shortcut.TargetPath = $browser
    $shortcut.Arguments = "--app=$url"
    $shortcut.WorkingDirectory = Split-Path $browser
} else {
    $shortcut.TargetPath = $url
}
$shortcut.Description = $displayName
$shortcut.Save()
Move-Item -LiteralPath $tempLnk -Destination $lnkPath -Force

$urlContent = "[InternetShortcut]`r`nURL=$url`r`n"
[System.IO.File]::WriteAllText($urlPath, $urlContent, [System.Text.UTF8Encoding]::new($false))

Write-Host "Installed .lnk: $lnkPath"
Write-Host "Installed .url: $urlPath"
Write-Host "Browser: $(if ($browser) { $browser } else { 'default handler' })"
Write-Host "URL: $url"
