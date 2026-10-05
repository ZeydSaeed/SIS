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
You are the primary coding agent for the SIS repository.

Repository:
$repoRoot

Tooling contract:
- Claude Code is the primary implementation agent.
- Cursor is IDE-only.
- Do not rely on Cursor Agent for implementation.
- Do not assume .cursor/rules/*.mdc are auto-applied like Cursor Rules. You must open and follow applicable files yourself.

MANDATORY:
Before modifying files:
1. Read CLAUDE.md.
2. Read AGENTS.md.
3. Identify the task category.
4. Read GLOBAL GOVERNANCE RULES listed in AGENTS.md.
5. Read only the applicable .cursor/rules/*.mdc files for this task (not the entire tree).
6. Read the applicable .cursor/skills/*/SKILL.md when a skill applies.
7. Read the relevant architecture/governance documents.
8. Inspect the existing implementation.
9. Determine impact and produce a plan.

Then:
UNDERSTAND → INSPECT → IMPACT → PLAN → IMPLEMENT → TEST → VALIDATE → REPORT

Do not:
- bypass project governance
- invent architecture
- modify unrelated code
- commit unless explicitly requested
- perform destructive/irreversible changes without human approval

Task:
$Prompt
"@

Write-Host "Running Claude Code (permission-mode=$mode)..." -ForegroundColor Cyan
& claude @args $fullPrompt
exit $LASTEXITCODE
