# SIS Phase 1 Gate Report — Student Reference UI

**Date:** 2026-09-08  
**Phase:** Phase 1 — Student List / Search / Pagination / Details (read-only)  
**Predecessor:** Phase 0.3 Governance Gate — PASS WITH CONDITIONS  
**Final Status:** **PASS WITH CONDITIONS**  
**Security Score:** **92/100**

---

## Executive Summary

Phase 1 establishes the canonical **Inertia + React** reference surface for Students: school-scoped list, search, pagination, and adaptive read-only details. Governance tensions **M-002** and **M-003** were resolved before implementation. No Student Create/Edit, no client-side API fetching, no new frontend test frameworks, and no Window Manager.

| Metric | Value |
|--------|-------|
| New web routes | `GET /students`, `GET /students/{student}` |
| New Inertia pages | `students/index`, `students/show` |
| New shared components | `sis/*` (5), `students/*` (3) |
| Student UI tests | 12 passed, 0 failed |
| All Student tests | 56 passed, 0 failed |
| Architecture fitness | PASS |
| Security validate | PASS |
| Feature contract (Student) | PASS |
| TypeScript (`types:check`) | PASS |

---

## Mandatory Approval Conditions — Compliance

| # | Condition | Status | Evidence |
|---|-----------|--------|----------|
| 1 | Resolve M-002 / M-003 before UI | ✅ | `laravel-architecture.md` marked LEGACY; Constitution §24 clarified; `AGENTS.md` updated |
| 2 | Hybrid adaptive Student Details UX | ✅ | Dialog (desktop preview) + canonical `/students/{id}` show page + mobile navigation |
| 3 | Session school context + middleware | ✅ | `require.school.context` on web routes; `RequireSchoolContextMiddleware` aborts 403 for web |
| 4 | Page-level authorization props | ✅ | `authorization: { canView, canViewPii, canUpdate }` per page — not global Inertia share |
| 5 | Tailwind/shadcn tokens (no parallel `--sis-*`) | ✅ | `UI-CONTRACT.md` reconciled |
| 6 | RTL infrastructure | ✅ | Locale-driven `dir` on `<html>`; logical spacing in components |
| 7 | No Create/Edit in Phase 1 | ✅ | Read-only surface only |
| 8 | Defer Vitest/Jest/Playwright | ✅ | Documented in `UI-CONTRACT.md` |
| 9 | Minimal DataTable only | ✅ | `sis/data-table.tsx` — columns, loading, empty, pagination, mobile cards |
| 10 | Inertia boundary only | ✅ | No fetch/axios/TanStack Query in Student UI |
| 11 | No unnecessary dependencies | ✅ | No new npm/composer packages |
| 12 | Backend/security tests | ✅ | `tests/Feature/Student/StudentUiTest.php` (12 cases) |
| 13 | Architecture/security validation | ✅ | Commands run — see below |
| 14 | Documentation updates | ✅ | `UI-CONTRACT.md`, this report |
| 15 | Do not start Phase 2 | ✅ | Stopped after gate audit |

---

## M-002 / M-003 Resolution

### M-002 — Legacy architecture doc tension

`laravel-architecture.md` is explicitly marked **LEGACY / NON-AUTHORITATIVE** with pointer chain:

```text
00-SIS-CONSTITUTION → 01-ARCHITECTURE → ARCHITECTURE-STACK → clean-architecture
```

`AGENTS.md` and `application-feature/SKILL.md` now reference `ARCHITECTURE-STACK.md` as authoritative for application structure.

### M-003 — Constitution §24

§24 distinguishes:

- **Application Handlers / Use Cases** (default)
- **Domain Services** (genuine domain logic only)
- **Infrastructure Services** (IO/adapters)
- **Legacy Services** (explicit exception)

No competing “Service-first” application architecture remains in authoritative docs.

---

## Scope Delivered

### Backend

- `StudentPageController` — thin Inertia controller delegating to existing query handlers
- Routes under `auth`, `verified`, `require.school.context`
- `RequireSchoolContextMiddleware` — returns 403 for missing school context on web (not JSON-only)
- `bootstrap/app.php` — web 404 render for `SisDomainException` with `not_found` error code
- Test helpers: `actingAsStudentManagerWeb`, `actingAsStudentViewerWeb`, `withWebSchoolContext`

### Frontend

- `resources/js/pages/students/index.tsx` — list + search + desktop Dialog preview
- `resources/js/pages/students/show.tsx` — canonical detail page
- `resources/js/components/students/*` — list, details surface, status badge
- `resources/js/components/sis/*` — page header, data table, empty/error/loading states
- Sidebar navigation link to Students

### Explicitly NOT delivered (deferred)

- Student Create/Edit forms
- School-switcher UI
- Window Manager / RuntimeRegistry / PlatformResolver
- Frontend automated UI test framework
- Global permission array in `HandleInertiaRequests`

---

## Security Verification

| Control | Result |
|---------|--------|
| Authentication required | ✅ Guest redirected to login |
| School context required | ✅ 403 without session `current_school_id` |
| Authorization (list) | ✅ Policy + permissions via server props |
| IDOR / cross-school | ✅ 403 on show + preview for other-school student |
| PII visibility | ✅ `national_id` omitted when `canViewPii` false |
| CSRF | ✅ Laravel web middleware (Inertia forms N/A — read-only) |
| Tenant isolation | ✅ Handlers scoped by `schoolId` from `SchoolContext` |
| Audit logging | ✅ Student data access + IDOR blocks recorded |

---

## Test Evidence

```bash
php artisan test --filter=StudentUiTest
# → 12 passed

php artisan test --filter=Student
# → 56 passed

php artisan architecture:validate --fitness
# → PASS (all categories)

php artisan security:validate
# → PASS

php artisan architecture:feature-check Student
# → PASS

npm run types:check
# → PASS
```

### StudentUiTest coverage

- Guest redirect
- Missing school context → 403
- Authorized list + search + pagination
- Viewer `canUpdate: false`
- Authorized show page
- Missing student → 403 (authorization-first; no existence leak)
- Cross-school show → 403
- PII sanitization on show
- Desktop preview query (authorized + cross-school forbidden)

---

## Pre-existing Technical Debt (unchanged)

| Item | Status |
|------|--------|
| PHPStan (`composer test`) | ~293 errors — pre-existing; not disabled |
| VP markdown formatting (`composer ci:check`) | ~159 files — pre-existing; not disabled |
| Browser/responsive manual QA | Not automated in Phase 1 (by approval) |

---

## Accessibility & Responsive Notes

- Search input has `aria-label`
- Data table uses semantic `<table>` with caption on desktop; card buttons on mobile
- Dialog/Sheet use Radix primitives (focus trap, ESC close)
- Codes and dates use `dir="ltr"` where content is inherently LTR
- Logical spacing (`me-2`, `text-start`) used in show page navigation

**Condition:** Full keyboard walkthrough and screen-reader audit deferred to Phase 2 QA cycle.

---

## SIS CHANGE REPORT

**Status:** PASS WITH CONDITIONS  
**Risk:** LOW

**Summary:** Phase 1 Student reference UI — read-only Inertia surface with school context, server authorization props, adaptive details, and RTL root handling.

**Scope:** Student web presentation layer + governance doc reconciliation (M-002/M-003).

| Area | Modified |
|------|----------|
| Application Code | YES |
| Database | NO |
| API | NO (existing Student API unchanged) |
| UI | YES |
| Dependencies | NO |
| Governance | YES |

**Files Added:**
- `app/Http/Controllers/Student/StudentPageController.php`
- `resources/js/pages/students/index.tsx`, `show.tsx`
- `resources/js/components/sis/*` (5 files)
- `resources/js/components/students/*` (3 files)
- `tests/Feature/Student/StudentUiTest.php`

**Files Modified:**
- `.cursor/architecture/SIS-CONSTITUTION.md`, `UI-CONTRACT.md`, `laravel-architecture.md`
- `AGENTS.md`, `application-feature/SKILL.md`
- `app/Security/Middleware/RequireSchoolContextMiddleware.php`
- `bootstrap/app.php`, `routes/web.php`
- `resources/views/app.blade.php`, `resources/js/components/app-sidebar.tsx`
- `tests/Concerns/InteractsWithSecurity.php`

**Security:** Server-authoritative school context + policies; IDOR tests pass.  
**Authorization:** Page-level props; backend remains authoritative.  
**Tests:** 12 new UI tests; 56 total Student tests pass.  
**Architecture Validation:** PASS  
**Security Validation:** PASS  

**Regression:** LOW  
**Technical Debt:** Manual responsive/a11y QA; PHPStan/VP pre-existing failures  
**Remaining Issues:** No automated browser tests; school-switcher UI deferred  
**Human Approval Required:** YES — Phase 2 scope  

---

## Gate Decision

```
PHASE 1 GATE: PASS WITH CONDITIONS

Conditions:
  1. Manual responsive/browser QA recommended before production demo
  2. Full a11y audit deferred to Phase 2
  3. Pre-existing PHPStan/VP CI debt recorded separately — not introduced by Phase 1

PHASE 2 READINESS: READY WITH CONDITIONS
  — Phase 2 may proceed after human approval
  — Recommended Phase 2 scope: Student Create/Edit, school-switcher UX, frontend test framework ADR

HUMAN APPROVAL: REQUIRED
```

---

*End of Phase 1 Gate Report. Do not proceed to Phase 2 without explicit human approval.*
