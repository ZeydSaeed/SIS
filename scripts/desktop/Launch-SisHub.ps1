# Launch SIS guest hub in desktop window shell (prefer Edge/Chrome --app mode).
$ErrorActionPreference = 'Stop'
$url = 'http://sis.test/hub?desktop=1'
$candidates = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe"
)
$browser = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if ($browser) {
    Start-Process -FilePath $browser -ArgumentList "--app=$url"
} else {
    Start-Process $url
}
Write-Host "Launched $url"
