# SIS Architecture Baseline (Human-Readable)

**Version:** 1.0 (companion to `ARCHITECTURE-BASELINE.json`)  
**Date:** 2026-09-08  
**Status:** Authoritative — validated by `php artisan architecture:validate --fitness`

---

## Current Implementation Stack

| Layer | Technology | Notes |
|-------|------------|-------|
| Backend | Laravel 13, PHP 8.4 | Application core |
| Database | PostgreSQL (primary), SQLite (local tests) | Source of truth |
| Cache | Redis | Cache only — not source of truth |
| API | REST JSON `/api/v1/*` + Sanctum | School context via `X-School-Id` |
| Web UI | Inertia 3 + React 19 + TypeScript | Primary presentation runtime |
| Styling | Tailwind 4 + Radix UI | Design tokens via CSS variables |
| Queue | Laravel Queue | Heavy ops off HTTP |
| AuthZ | Policies + permissions + RLS (partial) | Fail-closed school context |

**Not installed (future-capable only):** Vue, Blazor, Electron, Tauri — introduce only with ADR + approval.

---

## Clean Architecture Layers

```text
Presentation (Http/Controllers, Inertia pages)
        ↓
Application (Commands/Queries/Handlers/DTOs)
        ↓
Domain (Entities, Events, Specifications, Ports)
        ↓
Infrastructure (Eloquent *Record, Repositories, Jobs)
```

Machine-readable rules: `ARCHITECTURE-BASELINE.json`  
Full guide: `ARCHITECTURE-STACK.md`

---

## Feature Contract (writes)

- Command + Handler + Result
- Idempotency key on sensitive commands (Enroll, Payment, Import, Transfer, Withdraw)
- Domain events via Outbox — not direct `event()` in transactions
- Policy + FormRequest on HTTP endpoints

---

## Security Baseline

- `SchoolContextMiddleware` + `RequireSchoolContextMiddleware`
- RLS fail-closed: `enrollment.enrollments`, `attendance.records`
- Structured security audit logs
- Mass assignment guards on API requests

---

## UI Baseline

- **Current:** Inertia page-based navigation (not multi-window desktop host)
- **Target (Constitution):** Window Manager + adaptive presentation — see `WINDOW-CONTRACT.md`
- **RTL:** Required for Arabic — see `react-inertia.mdc`

---

## Module Implementation Order

```text
organization → academic → security → students → enrollment → curriculum
→ teachers → attendance → exams → lifecycle modules
```

See `WORK-PLAN.md` and `MODULE-CONTRACT.md`.

---

## Validation Commands

```bash
php artisan architecture:validate --fitness
php artisan architecture:feature-check {Context}
php artisan architecture:graph
php artisan security:validate
```

---

## Drift Prevention

Do not introduce:

- Business logic in controllers, React pages, or CSS
- Platform-specific business rules
- Parallel API contracts per frontend framework
- Hard deletes on official academic records

Report drift as **TECHNICAL DEBT** in change reports.
