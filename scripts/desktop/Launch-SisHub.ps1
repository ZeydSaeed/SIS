# Launch SIS guest Operational Hub in desktop window shell mode.
$ErrorActionPreference = 'Stop'
$url = 'http://sis.test/hub?desktop=1'
Start-Process $url
Write-Host "Launched $url"
