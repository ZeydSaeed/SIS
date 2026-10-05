# SIS — Claude Code Instructions

Enterprise Student Information System.  
**Stack:** Laravel 13 + Inertia/React 19 + PostgreSQL + Redis  
**Workspace:** `d:\Projects\sis`  
**Governance index:** `AGENTS.md` (must be read before implementation; do not assume automatic Cursor rule injection)

## Tooling split (mandatory)

| Tool | Role |
|------|------|
| **Claude Code Desktop / CLI** | Primary coding agent — edits, commands, tests, commits |
| **Cursor** | IDE only — browse, review, search. Do not rely on Cursor Agent for feature work |

Open the same folder in both. Prefer Cursor via **Your IDE** / `/ide` (CLI: `claude --ide`).

### Cursor → Claude bridge

When work is requested from Cursor Agent, implementation runs through:

```powershell
powershell -ExecutionPolicy Bypass -File ".\scripts\claude-bridge.ps1" -Prompt "<task>"
```

Plan only: add `-PlanOnly`. See `.cursor/rules/claude-code-bridge.mdc`.

## Governance load model

```text
CLAUDE.md (global contract)
    ↓
AGENTS.md (governance index)
    ↓
Classify task → load ONLY applicable:
    .cursor/rules/*.mdc
    .cursor/skills/*/SKILL.md
    .cursor/architecture/*
    ↓
UNDERSTAND → INSPECT → IMPACT → PLAN → IMPLEMENT → TEST → VALIDATE → REPORT
```

Do **not** assume `.cursor/rules/*.mdc` are auto-applied the way Cursor applies them.  
Do **not** read every rule file on every task — classify first, then load what matches.

## MANDATORY PRE-IMPLEMENTATION GATE

Do not modify project files until you have:

1. Identified the change category (app feature / database / UI / architecture / docs-only / ops).
2. Read `CLAUDE.md` and `AGENTS.md`.
3. Read the matching `.cursor/skills/*/SKILL.md` when a skill applies.
4. Read the **applicable** `.cursor/rules/*.mdc` files for that category/path (not the entire rules tree).
5. Read the applicable architecture/governance documents.
6. Inspected the existing implementation.
7. Produced an implementation plan (impact + files + risks + STOP checks).

Then follow:

```text
UNDERSTAND → INSPECT → IMPACT → PLAN → IMPLEMENT → TEST → VALIDATE → REPORT
```

### Category → read first

| Change type | Read first |
|-------------|------------|
| Any app feature | `.cursor/skills/application-feature/SKILL.md` + `.cursor/architecture/ARCHITECTURE-STACK.md` + `clean-architecture.mdc` |
| Database / migrations | `.cursor/skills/database-change/SKILL.md` + blueprint checklist + `database-changes-mandatory.mdc` |
| UI (`resources/js/**`) | `UI-CONTRACT.md`, `02-ui-ux.mdc`, `react-inertia.mdc`, `17-color-typography-governance.mdc` |
| Architecture-sensitive | `.cursor/architecture/SIS-CONSTITUTION.md`, `GOVERNANCE-MAP.md`, `01-ARCHITECTURE.mdc` |

Global governance files listed in `AGENTS.md` under **GLOBAL GOVERNANCE RULES** must be followed on every implementation task (read if not already loaded this session).

## Non-negotiables

1. Domain/Application stay framework-independent — no Eloquent/HTTP/DB in Domain.
2. Controllers stay thin — handlers own transactions; no business logic in UI/React.
3. Server-side authorization + tenant isolation on every protected operation.
4. Every academic operation scoped to `academic_year_id`.
5. Never hard-delete official academic records — status + `effective_to`.
6. Migrations only — no manual schema edits; update `database-blueprint.md` on DB changes.
7. Heavy work via Queue — never block HTTP for bulk/import/certificates/reports.
8. PostgreSQL = source of truth; Redis = cache only.
9. No N+1; no `SELECT *` in production API queries.
10. Correctness > performance. Do not optimize for a number — measure the workload.
11. No new framework/runtime (Blazor, Electron, etc.) without ADR + human approval.
12. Do not commit unless the user explicitly asks.

## Priority order (never invert)

```text
Security → Data integrity → Tenant isolation → Authorization → Architecture
→ Domain → API contracts → Constitution → Modules → UI → Platform → A11y
→ Tests → Maintainability → Performance → Convenience
```

## STOP — ask the human before proceeding

- Destructive / irreversible DB changes
- Security or authorization bypass
- Breaking API without migration
- Data loss or tenant isolation risk
- New framework/runtime without ADR
- Removing protected functionality / reopening phase gates without proven regression

## Commands

```bash
# Frontend
npm run dev
npm run build
npm run check
npm run types:check

# Backend
composer lint
composer test
php artisan architecture:validate --fitness
php artisan architecture:feature-check {Context}
php artisan architecture:graph
```

Prefer project scaffolds: `php artisan sis:make-feature` / `sis:make-command` / `sis:make-query`.

## Layout (where code belongs)

```text
app/Domain/**          pure domain
app/Application/**     commands/queries/handlers
app/Infrastructure/**  persistence, queues, external IO
app/Http/**            thin controllers + FormRequests
resources/js/**        Inertia/React presentation only
database/migrations/** versioned schema only
.cursor/architecture/** authoritative design docs
.cursor/skills/**      mandatory workflows
```

## UI rules (when touching frontend)

- Inertia-only data flow — no ad-hoc fetch/axios/React Query unless ADR-approved
- Forms: Inertia Form + Wayfinder + FormRequest; server validation is authoritative
- Tables: server pagination/filter — no thousands of client DOM rows
- Colors/fonts: Night/Oxford/Steel/Mist/Pearl + Segoe UI/Tahoma/Calibri/Aptos only
- RTL/Arabic from day one; accessibility and contrast are mandatory

## Validation gate (before claiming done)

```bash
php artisan architecture:validate --fitness
# + feature-check when module contracts change
composer test   # or targeted tests for the change
npm run types:check   # when TS/UI changed
```

For significant work, include an **SIS CHANGE REPORT** (see Constitution §81 / `AGENTS.md`).

## Do not

- Bypass project governance or invent architecture
- Assume Cursor auto-applies `.mdc` rules for Claude Code
- Put business logic in React, controllers, or platform adapters
- Hard-delete academic history
- Skip database-change skill for schema work
- Extend legacy shadcn/Instrument Sans palette on touched UI
- Refactor unrelated code inside a feature task — note `TECHNICAL DEBT:` instead
- Push to remote or amend commits unless explicitly requested
