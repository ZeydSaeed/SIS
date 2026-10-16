$root = Split-Path -Parent $PSScriptRoot
$desktop = [Environment]::GetFolderPath('Desktop')
$shell = New-Object -ComObject WScript.Shell
$lnk = $shell.CreateShortcut((Join-Path $desktop 'SIS Playwright.lnk'))
$lnk.TargetPath = Join-Path $root 'scripts\open-playwright.bat'
$lnk.WorkingDirectory = $root
$lnk.IconLocation = "$env:SystemRoot\System32\shell32.dll,13"
$lnk.Save()
Write-Host "Shortcut created on Desktop: SIS Playwright.lnk"
