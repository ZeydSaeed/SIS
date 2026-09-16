# Install SIS Desktop launcher (.lnk + .url) with Edge/Chrome --app mode.
# Browser host only — NOT Electron/Tauri/Blazor (RUNTIME-CONTRACT HOLD).
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$desktop = [Environment]::GetFolderPath('Desktop')
$url = 'http://sis.test/dashboard?desktop=1'
$displayNameAr = 'نظام معلومات الطالب'
$displayNameEn = 'SIS'

# Prefer project favicon (same brand mark as sidebar); mirror to Desktop\SIS.ico for reliable Explorer icons.
$repoRoot = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$faviconIco = Join-Path $repoRoot 'public\favicon.ico'
$desktopIco = Join-Path $desktop 'SIS.ico'
if (Test-Path -LiteralPath $faviconIco) {
    Copy-Item -LiteralPath $faviconIco -Destination $desktopIco -Force
}
$iconCandidates = @(
    $desktopIco,
    $faviconIco,
    (Join-Path $desktop 'SIS.png'),
    (Join-Path $repoRoot 'public\apple-touch-icon.png')
)
$iconPath = $iconCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1

$tempLnk = Join-Path $desktop 'SIS-Hub-Temp.lnk'
$lnkAr = Join-Path $desktop ($displayNameAr + '.lnk')
$lnkEn = Join-Path $desktop ($displayNameEn + '.lnk')
$urlAr = Join-Path $desktop ($displayNameAr + '.url')
$urlEn = Join-Path $desktop ($displayNameEn + '.url')

$toRemove = @(
    'SIS Operational Hub.lnk',
    'SIS Operational Hub.url',
    'SIS-Hub-Temp.lnk',
    $lnkAr,
    $lnkEn,
    $urlAr,
    $urlEn
)
foreach ($name in $toRemove) {
    $path = if ([System.IO.Path]::IsPathRooted($name)) { $name } else { Join-Path $desktop $name }
    if (Test-Path -LiteralPath $path) {
        Remove-Item -LiteralPath $path -Force -ErrorAction SilentlyContinue
    }
}

$candidates = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe"
)
$browser = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1

function New-SisShortcut {
    param(
        [string]$TempPath,
        [string]$FinalPath,
        [string]$Description
    )
    $wsh = New-Object -ComObject WScript.Shell
    $shortcut = $wsh.CreateShortcut($TempPath)
    if ($browser) {
        $shortcut.TargetPath = $browser
        $shortcut.Arguments = "--app=$url"
        $shortcut.WorkingDirectory = Split-Path $browser
    } else {
        $shortcut.TargetPath = $url
    }
    $shortcut.Description = $Description
    if ($iconPath) {
        $shortcut.IconLocation = "$iconPath,0"
    }
    $shortcut.Save()
    Move-Item -LiteralPath $TempPath -Destination $FinalPath -Force
}

# ASCII shortcut first (always visible in Explorer), then Arabic rename copy.
New-SisShortcut -TempPath $tempLnk -FinalPath $lnkEn -Description $displayNameAr
Copy-Item -LiteralPath $lnkEn -Destination $tempLnk -Force
Move-Item -LiteralPath $tempLnk -Destination $lnkAr -Force

$urlContent = "[InternetShortcut]`r`nURL=$url`r`n"
if ($iconPath) {
    $urlContent += "IconFile=$iconPath`r`nIconIndex=0`r`n"
}
[System.IO.File]::WriteAllText($urlEn, $urlContent, [System.Text.UTF8Encoding]::new($false))
[System.IO.File]::WriteAllText($urlAr, $urlContent, [System.Text.UTF8Encoding]::new($false))

Write-Host "Installed .lnk (EN): $lnkEn"
Write-Host "Installed .lnk (AR): $lnkAr"
Write-Host "Installed .url (EN): $urlEn"
Write-Host "Installed .url (AR): $urlAr"
Write-Host "Icon: $(if ($iconPath) { $iconPath } else { 'default' })"
Write-Host "Browser: $(if ($browser) { $browser } else { 'default handler' })"
Write-Host "URL: $url"
