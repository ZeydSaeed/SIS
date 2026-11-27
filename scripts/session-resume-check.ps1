# Read-only resume check for a new Claude Code session on SIS.
# Prints the live Git state, other recently active Claude sessions and the header of each resume file,
# so the session can compare them with what docs/project and docs/examis record. Changes nothing.
#
#   powershell -ExecutionPolicy Bypass -File .\scripts\session-resume-check.ps1 [-ActiveMinutes 30]

param([int]$ActiveMinutes = 30)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

Write-Output '== Git =='
Write-Output ("branch : " + (git branch --show-current))
Write-Output ("HEAD   : " + (git log --oneline -1))
$status = @(git -c core.quotepath=off status --short)
# Same digest as `git status --short | md5sum` in Git Bash, which the resume files record.
$bytes = [System.Text.Encoding]::UTF8.GetBytes((($status -join "`n") + "`n"))
$md5 = -join ([System.Security.Cryptography.MD5]::Create().ComputeHash($bytes) | ForEach-Object { $_.ToString('x2') })
Write-Output ("status : " + $status.Count + " entries, md5 " + $md5)
Write-Output ("stash  : " + @(git stash list).Count + " entries")
$status | Where-Object { $_ -like '`?`?*' } | ForEach-Object { Write-Output ("  untracked " + $_.Substring(3)) }

Write-Output ''
Write-Output ("== Claude sessions active in the last $ActiveMinutes min ==")
$transcripts = Join-Path $env:USERPROFILE '.claude\projects\D--Projects-sis'
if (Test-Path $transcripts) {
    $since = (Get-Date).AddMinutes(-$ActiveMinutes)
    $active = @(Get-ChildItem $transcripts -Filter *.jsonl | Where-Object { $_.LastWriteTime -gt $since } | Sort-Object LastWriteTime -Descending)
    if ($active.Count -eq 0) { Write-Output '  none' }
    $active | ForEach-Object { Write-Output ("  " + $_.BaseName + "  last write " + $_.LastWriteTime.ToString('yyyy-MM-dd HH:mm')) }
    Write-Output '  (one of these is the current session; any other one may be writing to the same tree)'
} else {
    Write-Output '  transcript folder not found'
}

Write-Output ''
Write-Output '== Resume files =='
foreach ($f in 'docs\project\PROJECT-NEXT-SESSION.md', 'docs\examis\EXAMIS-NEXT-SESSION.md') {
    if (Test-Path $f) {
        Write-Output ("-- $f (modified " + (Get-Item $f).LastWriteTime.ToString('yyyy-MM-dd HH:mm') + ')')
        Get-Content $f -Encoding UTF8 -TotalCount 4 | ForEach-Object { Write-Output ("  " + $_) }
    } else {
        Write-Output ("-- $f MISSING")
    }
}
