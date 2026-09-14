# Phase G8 Window Shell — Human Start Authorization

**Status:** OPEN  
**Branch:** `feature/phase-g8-window-shell`  
**Predecessor:** `feature/phase-operational-ui` (progress gate @ `e20e9d2`)  
**Bound by:** OPERATIONAL-TRANSITION-GATE G8 + WINDOW-CONTRACT + RUNTIME-CONTRACT

## Intent

1. Advance **G8 Release Readiness** (tests/checklists — not full infra go-live).
2. Implement **central Window Manager shell** for desktop profile (web-inertia only).
3. Provide **guest Operational Hub** (static / no school PII) without login.
4. Provide **Windows Desktop shortcut** launching browser to hub — **no Electron/Tauri/Blazor** (ADR required; HOLD).

## Allowed

- Central window manager in `resources/js` (not per-module stacks)
- Shared Confirm Dialog per WINDOW-CONTRACT
- Public static hub routes
- `.url` / PowerShell Desktop shortcut to `http://sis.test`

## Absolute HOLDs

- No Electron / Tauri / Blazor without ADR
- No auth bypass for school-scoped academic data
- No Ranking/PDF · Payroll · exam.session.cancel · CANCELLED→OPEN
