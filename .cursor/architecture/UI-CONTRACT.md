# SIS UI Contract

**Version:** 1.0  
**Date:** 2026-09-08  
**Runtime:** Inertia + React (current)  
**Constitution:** §6, §19–22, §47

---

## Purpose

Framework-independent UI contract so Web, PWA, Desktop host, or future React/Vue/Blazor adapters share the same **behavioral** semantics.

---

## Current Implementation (Repository Baseline)

| Contract area | Current artifact | Location |
|---------------|------------------|----------|
| Pages | Inertia pages | `resources/js/pages/` |
| Layouts | AppLayout, AuthLayout | `resources/js/layouts/` |
| Components | Radix + shadcn-style | `resources/js/components/` |
| Forms | Inertia `useForm` | Per page |
| Grids/Tables | Shared table components | `resources/js/components/` |
| Navigation | Sidebar + Inertia links | `resources/js/components/ui/sidebar.tsx` |
| Design tokens | CSS variables + Tailwind | Global styles |
| RTL | Required | `react-inertia.mdc` |

**Gap:** Centralized Window Manager, Command Palette, Workspace Manager — **not implemented** (see `WINDOW-CONTRACT.md`).

---

## Logical Contracts (framework-independent)

Every feature UI MUST define:

```text
featureId          — e.g. enrollment.create
permissions        — server-enforced; UI reflects only
apiEndpoints       — stable REST/Inertia props source
validationRules    — mirror server (UX); server authoritative
states             — idle, loading, success, empty, error, disabled
auditEvents        — map to backend security audit
presentationProfiles — desktop | tablet | mobile (when UI exists)
```

---

## Form Contract

```text
purpose, fields, client UX validation, server validation, permissions,
submit command, cancel behavior, loading, errors, success, keyboard, a11y
```

Use Inertia `useForm` — never POST sensitive fields (`school_id`, `status`, `enrolled_by`) from client.

---

## Grid Contract

```text
sort, filter, search, pagination, column visibility,
loading, empty, error, keyboard nav, responsive (cards on mobile)
```

No unbounded record loads — paginate server-side.

---

## Design System

Hierarchy:

```text
UI Contract (behavior + semantics)
        ↓
Design Tokens Contract (CSS variables in app.css @theme / :root)
        ↓
Tailwind utilities (Tailwind v4 + shadcn-style primitives)
        ↓
Reusable components (resources/js/components/ui + sis/)
        ↓
Pages
```

**Implementation baseline:** existing shadcn/Tailwind tokens (`--background`, `--primary`, `--radius`, sidebar tokens, etc.) in `resources/css/app.css`.

Do **not** introduce a parallel `--sis-*` token namespace unless a compatibility alias is explicitly approved and documented.

Do not scatter arbitrary colors/spacing.

---

## Phase 1 Reference Surface — Students (2026-09-08)

### School context (Inertia web)

```text
Session: current_school_id
Middleware: SchoolContextMiddleware (resolve) + require.school.context (enforce)
Authority: server-only — never trust client-supplied school id
```

Web routes under `/students` require authenticated session **and** valid school context. No school-switcher UI in Phase 1.

### Authorization presentation

Page-level Inertia props only — **not** global permission arrays in `HandleInertiaRequests`:

```text
authorization: { canView, canViewPii, canUpdate }
```

Values are computed server-side from policies/permissions. UI visibility is presentation-only; backend authorization remains authoritative.

### Adaptive Student Details

Canonical routes (always directly navigable):

```text
GET /students
GET /students/{student}
```

| Viewport | List | Details |
|----------|------|---------|
| Desktop | Table | Radix Dialog preview + canonical show page |
| Tablet | Compact table | Expanded detail surface / show page |
| Mobile | Card list | Dedicated show page (primary); Sheet optional |

No Window Manager. Use Radix Dialog/Sheet + Inertia navigation only.

### RTL

Root `dir` is locale-driven in `resources/views/app.blade.php`. Components use logical spacing (`ms`/`me`, `text-start`) and `dir="ltr"` on codes/dates where content is inherently LTR.

### Frontend automated testing (Phase 1)

Deferred: Vitest/Jest/Playwright not installed for Phase 1. Verification uses backend feature/security tests, architecture validation, and `npm run types:check`. Browser automation requires explicit architectural approval.

---

## Business/UI Separation

```text
UI → API / Inertia props → Application Handler → Domain → DB
```

Critical rules live in Domain/Application only. UI MUST NOT duplicate enrollment eligibility, authorization, or tenant checks.

---

## Adapter Rule

Future adapters (React SPA, Vue, Blazor, Desktop host) MUST consume:

```text
Same API · Same auth · Same permissions · Same validation messages · Same audit
```

Presentation may differ; business meaning must not.
