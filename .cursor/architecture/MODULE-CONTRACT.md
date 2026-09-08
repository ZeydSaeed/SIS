# SIS Module Contract

**Version:** 1.0  
**Date:** 2026-09-08  
**Constitution:** §53–55

---

## Module Definition

Every SIS module MUST declare:

```text
Business responsibility
Bounded context folder (app/Application/{Context}/)
Routes (web + api)
Policies + permissions
Commands/Queries (CQRS)
Infrastructure adapters
Tests (unit + feature + security where applicable)
Documentation (docs/sis/{module}/ when gated)
```

Follow **existing** structure — do not invent parallel module layouts.

---

## Implementation Order (mandatory)

```text
organization → academic → security → students → enrollment → curriculum
→ teachers → attendance → exams → lifecycle modules
```

See `sis-core.mdc` and `WORK-PLAN.md`.

---

## Definition of Done (module gate)

Reference `FEATURE-DONE.md` + module gate report pattern (e.g. `docs/sis/enrollment/ENROLLMENT-GATE-REPORT.md`):

| Check | Required |
|-------|----------|
| Clean Architecture layers | Yes |
| `architecture:feature-check {Context}` | Pass |
| Security contract matrix | Executable tests |
| School scoping + policy | Yes |
| No hard-delete of official records | Yes |
| Gate report + human approval | Yes |

---

## New Module Checklist

```text
[ ] Baseline audit (read-only)
[ ] Security contract
[ ] Application handlers (not controller logic)
[ ] Policy + permissions in config/security.php
[ ] Feature + security tests
[ ] Gate report
[ ] STOP for human approval before next module
```

---

## Cross-Module Rules

- No direct cross-context DB access from controllers
- Read other contexts via Query ports / read repositories
- Shared kernel: `Domain/Shared`, `Application/Shared`
- Academic ops scoped to `academic_year_id`

---

## Example: Enrollment Module (reference)

| Phase | Scope |
|-------|-------|
| A | Create + list/show + security |
| B | Update placement + cancel |
| C+ | Transfer, admission (deferred) |

See `docs/sis/enrollment/` for full artifacts.
