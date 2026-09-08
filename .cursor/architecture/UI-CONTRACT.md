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
SIS Design Tokens (CSS variables)
        ↓
Tailwind utilities
        ↓
Reusable components (resources/js/components)
        ↓
Pages
```

Token examples: `--sis-primary`, `--sis-surface`, `--sis-spacing-*`, `--sis-radius-*`

Do not scatter arbitrary colors/spacing.

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
