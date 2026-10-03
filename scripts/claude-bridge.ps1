#Requires -Version 5.1
<#
.SYNOPSIS
  Run a coding task through Claude Code CLI against this repo (Cursor = IDE only).

.EXAMPLE
  .\scripts\claude-bridge.ps1 -Prompt "Fix the attendance filter bug"
  .\scripts\claude-bridge.ps1 -Prompt "Add validation" -PermissionMode acceptEdits
#>
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string] $Prompt,

    [ValidateSet('default', 'acceptEdits', 'auto', 'plan', 'dontAsk')]
    [string] $PermissionMode = 'acceptEdits',

    [string] $Model = '',

    [switch] $PlanOnly
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

$claude = Get-Command claude -ErrorAction SilentlyContinue
if (-not $claude) {
    Write-Error "claude CLI not found on PATH. Install Claude Code / ensure claude.exe is available."
}

$mode = if ($PlanOnly) { 'plan' } else { $PermissionMode }

$args = @(
    '--print',
    '--ide',
    '--permission-mode', $mode,
    '--output-format', 'text'
)

if ($Model) {
    $args += @('--model', $Model)
}

$fullPrompt = @"
You are working in the SIS repository at $repoRoot.
Cursor is the IDE only. Apply the change in this repo following CLAUDE.md and AGENTS.md.
Task:
$Prompt
"@

Write-Host "Running Claude Code (permission-mode=$mode)..." -ForegroundColor Cyan
& claude @args $fullPrompt
exit $LASTEXITCODE
