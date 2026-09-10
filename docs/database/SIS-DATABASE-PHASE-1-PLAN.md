# Phase 1 — Implementation Plan

**Phase:** 1 — Database Architecture  
**Date:** 2026-09-10  
**Human approval:** `APPROVED PHASE 1` + Decisions D1–D6 + mandatory conditions  
**Predecessor:** Phase 0 Gate — PASS WITH CONDITIONS

---

## Objective

Lock approved architecture decisions into ADRs and `docs/database/*`, reconcile documentation SSOT (table counts, PK, schemas, RLS strategy, phased delivery), and produce a Phase 1 Gate Report.

**No PostgreSQL DDL. No Laravel migration files. No SchemaHelper changes. No RLS enablement.**

---

## In scope

| # | Work item | Artifact |
|---|-----------|----------|
| 1 | Decision lock ADR (D1–D6 + mandatory conditions) | `.cursor/architecture/adr/ADR-020-phase-1-database-architecture.md` |
| 2 | Reinforce PK ADR reference | ADR-003 (status note / related link) |
| 3 | Amend RLS ADR for incremental expansion | ADR-005 (amendment for D6) |
| 4 | Update ADR index | `adr/README.md` |
| 5 | Lock decisions into architecture docs | `docs/database/00`–`03` updates |
| 6 | Author strategy docs (design only) | `04`–`10` under `docs/database/` |
| 7 | Reconcile blueprint table-count SSOT | `database-blueprint.md` header/summary |
| 8 | Align AGENTS.md table-count claim | `AGENTS.md` |
| 9 | Record Phase 1 plan + gate | this file + `SIS-DATABASE-PHASE-1-GATE.md` |

---

## Out of scope (explicit)

| Item | Deferred to |
|------|-------------|
| `CREATE SCHEMA admission` / SchemaHelper add | Phase 2 |
| Exams / grades / results / grade_history tables | Assessment phase (after foundation; prioritize per D5) |
| RLS `ENABLE` / new policies on students etc. | Dedicated RLS implementation phase after design |
| CHECK constraint migrations | Progressive later phases with data validation |
| New indexes | Evidence-driven later phases |
| ERP schemas (hr, medical, inventory, …) | Later phase gates |
| DROP / rename schemas or tables | Forbidden without separate approval |
| Rewriting applied migrations | Forbidden |

---

## Affected schemas / tables

```text
NONE (no database structure changes)
```

---

## Migrations

```text
NONE
```

---

## Constraints / indexes / RLS changes (database)

```text
NONE applied in Phase 1

Design-only:
- CHECK strategy documented in 07 / 10
- Index strategy documented in 04
- RLS expansion design documented in 06 (priority list from D6)
```

---

## Risks

| Risk | Mitigation |
|------|------------|
| Scope creep into admission/exams DDL | Hard out-of-scope list; gate fails if DDL appears |
| Doc/blueprint count still wrong | Recount headings; publish single SSOT number |
| Future agents ignore decision lock | ADR-020 + docs/database cross-links + gate |

---

## Rollback strategy

Documentation/ADR only — revert git commits if needed. **No database rollback required.**

---

## Tests

| Check | Method |
|-------|--------|
| No pending schema drift from Phase 1 | Confirm zero new migration files |
| Foundation still green | `php artisan sis:verify-database --no-seed-check` |
| Decision artifacts exist | File presence of ADR-020 + docs 04–10 + gate |

---

## Success criteria

1. D1–D6 recorded as Accepted in ADR-020  
2. Strategy docs 04–10 published (architecture, not implementation)  
3. Blueprint/AGENTS table-count inconsistency resolved  
4. Zero destructive or additive DDL  
5. Phase 1 Gate issued → STOP for human review  

---

## Proceed

This plan is the binding Phase 1 scope. Implementation below follows only this plan.
