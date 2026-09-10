# DATABASE PHASE 0 GATE

```text
DATABASE PHASE 0 GATE
STATUS: PASS WITH CONDITIONS
```

**Date:** 2026-09-10  
**Auditor role:** Senior Enterprise Database / PostgreSQL / SIS Domain Architect  
**Mode:** READ-ONLY discovery — **no tables created, no migrations written, no destructive operations**

---

## Verdict

Phase 0 discovery is complete. Live PostgreSQL state, migrations, blueprint, and the master ERP specification have been reconciled into a target architecture and phased plan.

Implementation must **not** begin until the human replies with explicit approval (e.g. `APPROVED` or `APPROVED PHASE 1`).

```text
HUMAN APPROVAL REQUIRED
```

**Update 2026-09-10:** Human approved Phase 1 with Decisions D1–D6. See [SIS-DATABASE-PHASE-1-GATE.md](./SIS-DATABASE-PHASE-1-GATE.md) and [ADR-020](../../.cursor/architecture/adr/ADR-020-phase-1-database-architecture.md).
---

## Current database state (summary)

| Item | Finding |
|------|---------|
| PostgreSQL | **18.2** |
| Database / user | `sis` / `postgres` |
| Migrations | 24 applied |
| Business tables (verifier) | 54 |
| Schemas | 25 (admission missing) |
| RLS | 2 tables only (`enrollments`, `attendance.records`) |
| Partitions | `attendance.records` LIST(`academic_year_id`) |
| CHECK constraints | 0 |
| Intelligence | Present and isolated |
| Foundation verify | PASS |

Full detail: [SIS-DATABASE-MASTER-AUDIT.md](./SIS-DATABASE-MASTER-AUDIT.md)

---

## Target architecture (decision frame)

### Preserve (do not rename)

Existing PostgreSQL schemas and tables for organization, academic, students, guardians, enrollment, teachers, curriculum, vocational, timetable, attendance, security (partial), audit (partial), reports, intelligence, and Laravel `public.*`.

### Complete next (blueprint backlog)

Lifecycle and academic completion: admission, exams/grades, results, promotion, transfers, graduation, certificates, documents, finance (fee track), communication, workflow, audit_logs, timetable schedules, RLS expansion.

### Expand later (master ERP — justified schemas only)

| Candidate schema | Responsibility | When |
|------------------|----------------|------|
| `hr` | Employees, contracts, leave, payroll (not teachers-only) | After staff model ADR |
| `inventory` | Stock, assets, workshop materials | With facilities |
| `facilities` | Maintenance beyond rooms | With inventory |
| `medical` | Health records (HIGHLY_RESTRICTED) | After RLS maturity |
| `support` | SEN/IEP/counseling | With medical controls |
| `behavior` | Discipline / merits | After students stable |
| `activities` | Clubs, assemblies, duties, volunteering | Parallel ops track |
| `internship` | Partners, placements, logbooks | Vocational priority |
| `transportation` | Routes, buses, subscriptions | Ops track |
| `library` | Resources/loans | Optional |
| `alumni` | Post-graduation engagement | After graduation module |
| `analytics` | Non-MV analytical structures | Only if MVs insufficient |
| `archive` | Cold historical storage | Retention policy phase |

**Do not create** unused prompt names (`core`, `identity`, `reference`, `system`, `optimization` as separate schema) unless an ADR proves `organization`/`security`/`intelligence` insufficient.

### PK strategy (recommended)

```text
KEEP: BIGINT GENERATED ALWAYS AS IDENTITY for internal PKs
KEEP: UUID public_id for external/API identifiers where needed
REJECT (default): mixed UUID internal PKs unless ADR + human approval
```

---

## Schema list (target steady state)

### Already justified (live)

`organization`, `academic`, `vocational`, `students`, `guardians`, `enrollment`, `teachers`, `curriculum`, `timetable`, `attendance`, `security`, `audit`, `reports`, `intelligence`, `public` (+ empty reserved: `exams`, `results`, `promotion`, `transfers`, `graduation`, `certificates`, `documents`, `finance`, `communication`, `workflow`)

### Must add when Phase starts

`admission` (fix SchemaHelper + create schema migration)

### Conditional ERP (approval per phase)

`hr`, `inventory`, `facilities`, `medical`, `support`, `behavior`, `activities`, `internship`, `transportation`, `library`, `alumni`, `analytics`, `archive`

---

## Table inventory

| Bucket | Count (approx.) | Notes |
|--------|-----------------|-------|
| Live domain OLTP | ~41 | Foundation through attendance |
| Live MVs | 3 | of 8 blueprint MVs |
| Intelligence | 10 | including validation target |
| Framework public | ~10 | Laravel/Fortify/Sanctum |
| Blueprint not migrated | ~40+ | Priority backlog |
| ERP expansion (new) | TBD by phase ADRs | Not inventing table counts without design |

Authoritative live lists: Master Audit §A; blueprint: `.cursor/architecture/database-blueprint.md`.

---

## Missing domains (priority)

1. **CRITICAL:** Exams / grades / results (immutable grade history)  
2. **CRITICAL:** RLS coverage for remaining `school_id` tables + `FORCE` policy decision  
3. **HIGH:** Admission pipeline; timetable schedules; workshop/batch/safety model  
4. **HIGH:** Domain audit_logs; CHECK constraints on statuses/grades/capacities  
5. **MEDIUM:** Finance fees → later GL; HR/payroll; inventory/facilities  
6. **MEDIUM:** Medical / SEN with classification HIGHLY_RESTRICTED  
7. **LATER:** Transport, library, alumni, volunteering links  

---

## Conflicts (must resolve before or during Phase 1)

| ID | Decision needed | Options |
|----|-----------------|---------|
| D1 | Internal PK type | **A (recommended):** BIGINT only · B: UUID for new tables · C: migrate all to UUID |
| D2 | Person SSOT | **A (recommended):** defer `persons`; keep students/guardians/teachers · B: introduce `identity.persons` now |
| D3 | Campus naming | **A:** keep `branches` · B: rename to campuses (destructive/doc churn) |
| D4 | Users home | **A:** keep `public.users` · B: move to `security.users` |
| D5 | Blueprint count SSOT | Reconcile 86 vs 89 in docs during Phase 1 |
| D6 | ERP scope for Phase 1 | **A (recommended):** architecture docs only · B: also add empty ERP schemas |

---

## Risks

See Master Audit §F. Highest: mixed PK strategy, premature ERP schema sprawl, RLS expansion breaking workers, rewriting migration history.

---

## Migration plan (post-approval)

```text
1. Never rewrite applied migrations
2. Additive migrations only
3. Update database-blueprint.md in the same change as schema
4. Follow database-change skill + DATABASE-CHANGE-CHECKLIST
5. Run migrate → verify → tests → architecture/security validate → gate report
6. STOP after each numbered phase for human review
```

---

## Security / RLS plan

| Step | Action |
|------|--------|
| 1 | Inventory all tables with `school_id` |
| 2 | Enable RLS + fail-closed policies matching enrollment pattern |
| 3 | Decide `FORCE ROW LEVEL SECURITY` (recommended YES for tenant tables) |
| 4 | Ensure HTTP middleware + queue/console set `app.current_school_id` |
| 5 | Stronger policies for medical/SEN/payroll (role claims in GUCs) |
| 6 | Tests: cross-school denied; medical denied without grant |

---

## Partitioning plan

| Table | Key | Strategy | Timing |
|-------|-----|----------|--------|
| `attendance.records` | `academic_year_id` | LIST (exists) | Maintain year partitions |
| `exams.student_grades` | `academic_year_id` | LIST | At first create (P0) |
| `audit.audit_logs` | `created_at` | RANGE monthly | When table created |
| `communication.messages` | `created_at` | RANGE | When volume justifies |
| `finance.transactions` | `academic_year_id` | LIST | When GL/fees history grows |

Do not partition small reference tables.

---

## Indexing plan

- Keep FK indexes that support JOIN/WHERE  
- Prefer composite `(school_id, academic_year_id, …)` for tenant queries  
- Evaluate BRIN on append-only time columns after evidence  
- No speculative indexes — EXPLAIN ANALYZE required for new ones on hot paths  

---

## Data lifecycle plan

| Data class | Delete policy |
|------------|---------------|
| Grades, enrollments, attendance, finance, payroll, audit, medical, discipline | **Never hard-delete** — status / effective_to / reversal / history |
| Reference codes | Soft deprecate via status |
| Ephemeral (jobs, cache) | Framework TTL OK |
| Intelligence telemetry | Retention policy TBD; not academic source of truth |

---

## Implementation phases (aligned to master prompt)

| Phase | Scope | Start only after |
|-------|-------|------------------|
| **0** | Discovery / Audit | — (**this gate**) |
| **1** | Database architecture docs + decisions D1–D6 | **APPROVED** |
| **2** | Core / Identity / Reference alignment (SchemaHelper, admission schema, persons ADR if any) | Phase 1 gate |
| **3** | Student / Guardian completion | Phase 2 |
| **4** | Academic / curriculum / vocational gaps | Phase 3 |
| **5** | Admissions / Enrollment hardening | Phase 4 |
| **6** | Attendance hardening | Phase 5 |
| **7** | Assessment / Exams / Grades | Phase 6 |
| **8** | Scheduling / Workshops / Batches / Safety | Phase 7 |
| **9** | Staff / HR | Phase 8 |
| **10** | Behavior / Activities / Assemblies / Duties | Phase 9 |
| **11** | Medical / SEN | Phase 10 |
| **12** | Internship / Alumni / Library | Phase 11 |
| **13** | Transportation | Phase 12 |
| **14** | Inventory / Facilities | Phase 13 |
| **15** | Finance (fees → GL) | Phase 14 |
| **16** | Documents / Communication | Phase 15 |
| **17** | Audit / Security / RLS expansion | Continuous; formalize |
| **18** | Reporting / Analytics MVs | After source tables |
| **19** | Intelligence safety integration review | Ongoing |
| **20** | Performance / Partitioning / Hardening | Evidence-driven |
| **21** | Final Database Gate | All critical gates green |

**Recommended first implementation after approval:** Phase 1 documentation + decision lock only (still no ERP table flood), then Phase 2 schema fixes (`admission`) before exams.

---

## Unresolved decisions

- D1–D6 above  
- Whether workshops are `vocational` extensions vs new schema  
- Whether `persons` is mandatory for multi-role humans in year 1  
- Full double-entry GL vs fee-ledger-first finance  
- Medical storage jurisdiction / encryption-at-rest requirements  

---

## Conditions for PASS WITH CONDITIONS

1. Human acknowledges PK = BIGINT IDENTITY remains default.  
2. Human acknowledges existing schemas are preserved (no big-bang rename).  
3. Human acknowledges ERP domains are phased — not created in one migration wave.  
4. No destructive SQL without a later explicit approval.  
5. Blueprint/AGENTS table-count inconsistency fixed in Phase 1 docs.

If any condition is rejected, status becomes **FAIL** until redesigned.

---

## Deliverables produced in Phase 0

```text
docs/database/SIS-DATABASE-MASTER-AUDIT.md
docs/database/SIS-DATABASE-PHASE-0-GATE.md
docs/database/00-DATABASE-ARCHITECTURE.md
docs/database/01-SCHEMA-CATALOG.md
docs/database/02-TABLE-CATALOG.md
docs/database/03-RELATIONSHIP-MAP.md
```

Remaining catalog docs (04–14), ERD machine file, structural tests, and Final Gate are **post-approval** work products.

---

## Explicit stop

```text
STATUS: PASS WITH CONDITIONS
HUMAN APPROVAL REQUIRED

Do not proceed to Phase 1 until the human explicitly approves.
Reply: APPROVED
Optional: APPROVED PHASE 1
Optional: APPROVED WITH DECISIONS: D1=A, D2=A, ...
```
