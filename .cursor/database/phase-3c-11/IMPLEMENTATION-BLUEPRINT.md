# PHASE 3C.11 — IMPLEMENTATION BLUEPRINT

**Mode:** DESIGN-TO-IMPLEMENTATION PLANNING ONLY  
**Date:** 2026-09-10  

```text
IMPLEMENTATION: NOT EXECUTED
MIGRATIONS: NOT CREATED
DDL: NOT EXECUTED
HUMAN IMPLEMENTATION AUTHORIZATION: REQUIRED
```

---

## 1. Purpose

Convert finalized Phase 3C.10 / 3C.10A physical design into a **mechanical execution blueprint** for a future authorized implementation phase. This document is the index; detailed plans live in sibling files.

---

## 2. Authority chain

```text
3C.10A > 3C.10 > 3C.9 > 3C.8B locks > LIVE patterns > older sketches
```

Stale blueprint `graduation.eligibility_rules` / `graduation.records` / `min_gpa` = **NON-AUTHORITATIVE**. Do not implement.

---

## 3. Repository audit summary (LIVE)

| Area | Finding | Plan implication |
|------|---------|------------------|
| Schema `graduation` | In `SchemaHelper::schemas()`; created via `CREATE SCHEMA IF NOT EXISTS` | Verify exists; do not remove from SchemaHelper; optional no-op ensure in first migration |
| Composite FK support | `enrollments_id_school_id_unique` created in Phase 3B grades migration | Prerequisite already LIVE — verify before graduation FKs |
| PK style | `BIGINT GENERATED ALWAYS AS IDENTITY` (grades) | Match |
| ON DELETE | `RESTRICT` on academic FKs | Match — never CASCADE official history |
| Partial UNIQUE | `WHERE is_current` on grades | Mirror for `is_current_official` / `is_current_issued` |
| DELETE immutability | Trigger `exams.reject_student_grades_delete` | Plan equivalent reject-delete (+ update guards) for graduation official tables |
| RLS | ENABLE+FORCE; fail-closed GUC `app.current_school_id` | Copy grades policy pattern |
| Outbox | `audit.outbox_messages` + `EloquentOutboxRepository::stage` | Reuse only |
| Idempotency | `audit.idempotency_keys` + `IdempotencyStore` | Reuse only |
| CQRS | Exams/Enrollment handlers + UnitOfWork + Results | Future `Graduation` (or `Lifecycle`) context same pattern |
| StudentStatus | Domain enum includes `Graduated=3`; no graduation sync yet | Projection consumer only — no new table (D-3C10-008) |
| Partition | Grades LIST by year; graduation **NO PARTITION AT LAUNCH** | Preserve |

---

## 4. Final physical model (locked)

**Schema:** `graduation`  

**Separations preserved:**

```text
Completion ≠ Graduation Approval ≠ Graduation Award ≠ StudentStatus Projection
```

**Identity:** domain UNIQUE `(school_id, enrollment_id)` + technical BIGINT IDENTITY.  
**Composite FK:** `(enrollment_id, school_id)` → `enrollment.enrollments(id, school_id)` RESTRICT.  
**No hard delete** of official completion/award/evidence/approval/lineage rows.

Tables (14): see `TABLE-IMPLEMENTATION-MATRIX.md`.

---

## 5. WHAT / WHY / WHERE / WHEN (summary)

| What | Why | Where | When (future) |
|------|-----|-------|---------------|
| Create graduation tables | Persist finalized model | `graduation.*` via migrations | After human auth |
| Constraints/indexes | Integrity + Q1–Q14 | Same migrations / follow-ons | With tables / after |
| RLS FORCE | Tenant fail-closed | Per-table policies | After tables empty-safe |
| Triggers | Immutability not app-only | `graduation` schema functions | After tables |
| App CQRS slice | Commands/queries | `app/Domain|Application|Infrastructure/...` | After DB green |
| Outbox events | Side effects / status sync | Existing outbox | Inside write txns |
| StudentStatus sync | Projection | Existing students status | Consumer of outbox — eventual |

---

## 6. Implementation dependency graph (application + DB)

```text
[Prereq LIVE] schools, enrollments UNIQUE(id,school_id), audit outbox/idempotency, SchemaHelper
        ↓
M01–M07 DDL tables (policy → outcome → evidence → approval → award → lineage)
        ↓
M08 constraints/partial UNIQUE not inline
        ↓
M09 supporting indexes (REQUIRED set)
        ↓
M10 RLS ENABLE+FORCE+policies
        ↓
M11 immutability triggers (+ denorm consistency triggers)
        ↓
[App] Domain VOs/entities → repositories → commands/queries → HTTP (optional wave)
        ↓
[App] Outbox event classes (names PROPOSED) + consumers
        ↓
[App] StudentStatus projection consumer + rebuild command
        ↓
Tests + architecture:validate + security gates
```

Migration graph detail: `MIGRATION-DEPENDENCY-PLAN.md`.

---

## 7. Non-goals (this phase and until auth)

- No migrations/DDL/code/data changes  
- No inventing GPA/credits/roles/thresholds  
- No new outbox or projection table  
- No partitioning  
- No implementing blueprint stale tables  

---

## 8. Pre-implementation gate checklist

| Check | Required |
|-------|----------|
| 3C.10A FINAL PASS | YES |
| Blocking physical decisions closed/safe-deferred | YES |
| No hidden business policy | YES |
| Migration dependency graph complete | YES (this phase) |
| RLS / immutability / concurrency / rollback / tests | YES |
| StudentStatus / outbox / idempotency plans | YES |
| Human implementation authorization | **REQUIRED — not granted here** |

---

## 9. Companion artifacts

| File | Content |
|------|---------|
| `MIGRATION-DEPENDENCY-PLAN.md` | M01… migrations |
| `TABLE-IMPLEMENTATION-MATRIX.md` | Per-table plan |
| `SECURITY-RLS-IMPLEMENTATION-PLAN.md` | RLS |
| `IMMUTABILITY-LINEAGE-IMPLEMENTATION-PLAN.md` | Immutability + lineage |
| `INDEX-IMPLEMENTATION-PLAN.md` | Indexes |
| `OUTBOX-IDEMPOTENCY-IMPLEMENTATION-PLAN.md` | Events + keys |
| `STUDENTSTATUS-INTEGRATION-PLAN.md` | Projection sync |
| `APPLICATION-INTEGRATION-PLAN.md` | CQRS map |
| `TRANSACTION-CONCURRENCY-PLAN.md` | Txn + races |
| `ROLLBACK-RECOVERY-PLAN.md` | Rollback ≠ revoke |
| `TEST-IMPLEMENTATION-MATRIX.md` | Tests + negatives |
| `DEPLOYMENT-PLAN.md` | Deploy + scale + observability |
| `FILE-CHANGE-MAP.md` | Future files |
| `PHASE-3C-11-GATE-REPORT.md` | Gate |
| `STALE-BLUEPRINT-AUDIT.md` | Stale refs |
| `DENORMALIZED-IDENTITY-PLAN.md` | Denorm enforcement |
