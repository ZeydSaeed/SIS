# PHASE 3C.11 — MIGRATION DEPENDENCY PLAN

**DDL NOT EXECUTED. Migrations NOT CREATED.**

Naming convention (future): `YYYY_MM_DD_HHMMSS_phase3c_graduation_<purpose>.php`  
Align with LIVE: `2026_09_10_160000_phase3b_create_student_grades.php`.

---

## Prerequisites (verify before M01)

| Prerequisite | Status | Action if missing |
|--------------|--------|-------------------|
| Schema `graduation` in SchemaHelper | LIVE | Do not remove; ensure via `createSchemas` / IF NOT EXISTS |
| `enrollment.enrollments` UNIQUE `(id, school_id)` | LIVE (Phase 3B) | Block graduation FKs until present |
| `organization.schools` | LIVE | — |
| `audit.outbox_messages` / `idempotency_keys` | LIVE | — |
| Optional: UNIQUE `(id, academic_year_id)` on enrollments | LIVE (grades) | Use if denorm year composite FK added |

---

## Migration dependency graph

```text
M01 schema ensure
  → M02 eligibility_policies
    → M03 eligibility_policy_versions
      → M04 requirement_definitions
        → M05 requirement_definition_versions
M02 also → M06 completion_outcomes
  → M07 completion_outcome_versions (FK policy version)
    → M08 evidence_sets
      → M09 evidence_items
    → M10 requirement_evaluations
    → M11 graduation_approvals
M06 → M12 graduation_awards
  → M13 graduation_award_versions (FK approval + completion version)
M07/M13 → M14 outcome_supersessions
M13 → M15 revocation_records
M02–M15 → M16 remaining indexes (if not inline)
M02–M15 → M17 RLS all tables
M02–M15 → M18 immutability + denorm triggers
```

Why separate M17/M18: match Phase 3B pattern (table migration then RLS migration); triggers after constraints exist; empty-table RLS safer.

Why not one mega-migration: blast radius, reviewability, partial rollback of empty objects, CI clarity.

---

## Migration catalog

### M01 — Schema ensure

| Field | Value |
|-------|-------|
| Proposed filename | `*_phase3c_graduation_ensure_schema.php` |
| Purpose | `CREATE SCHEMA IF NOT EXISTS graduation` (idempotent) |
| Prerequisites | PostgreSQL |
| Objects created | Schema only (if missing) |
| Rollback | No DROP SCHEMA in down if other objects may exist — document no-op / human |
| Risk | LOW |
| Verification | `\dn graduation` / information_schema |

### M02 — eligibility_policies

| Field | Value |
|-------|-------|
| Purpose | Policy family catalog |
| Objects | `graduation.eligibility_policies` |
| FK | school_id → schools RESTRICT |
| UNIQUE | `(school_id, policy_code)` |
| Indexes | covered by UNIQUE |
| RLS/triggers | Later M17/M18 |
| Rollback | DROP TABLE if empty + approved |
| Risk | LOW |

### M03 — eligibility_policy_versions

| Field | Value |
|-------|-------|
| Purpose | Versioned policy shells (nullable JSONB payload) |
| FK | policy_id → policies; school_id → schools |
| UNIQUE | `(policy_id, version_no)` |
| Partial UNIQUE | `(policy_id) WHERE is_current_effective` (or equiv) — D-3C10-002 |
| JSONB | nullable — no invented schema |
| Risk | LOW |

### M04 — requirement_definitions

| Field | Value |
|-------|-------|
| FK | policy_id, school_id |
| UNIQUE | `(policy_id, requirement_code)` |

### M05 — requirement_definition_versions

| Field | Value |
|-------|-------|
| UNIQUE | `(requirement_definition_id, version_no)` |
| JSONB | nullable rule_payload |

### M06 — completion_outcomes

| Field | Value |
|-------|-------|
| Domain identity | UNIQUE `(school_id, enrollment_id)` |
| Composite FK | `(enrollment_id, school_id)` → enrollments RESTRICT |
| Denorm | student_id, academic_year_id, specialization_id nullable/as designed |
| Pointer | current_official_version_id nullable (FK deferred to M07 or ADD CONSTRAINT after) |
| Risk | MEDIUM if pointer FK circular — prefer add FK in M07 after versions exist |

**Circular FK note:** Create outcomes without FK to versions; add `current_official_version_id` FK in M07b or M08.

### M07 — completion_outcome_versions

| Field | Value |
|-------|-------|
| UNIQUE | `(completion_outcome_id, version_no)` |
| Partial UNIQUE | `(completion_outcome_id) WHERE is_current_official` |
| FK | outcome, policy_version, school; self-refs supersedes nullable |
| Risk | MEDIUM — concurrency depends on partial UNIQUE |

### M08 — evidence_sets

| Field | Value |
|-------|-------|
| PK | separate IDENTITY (D-3C10-003 CLOSED) |
| UNIQUE | `(completion_outcome_version_id)` 1:1 |

### M09 — evidence_items

| Field | Value |
|-------|-------|
| FK | evidence_set, school denorm |
| UNIQUE | `(evidence_set_id, source_type, source_id, coalesce version_ref)` |
| No grade payload copy | |

### M10 — requirement_evaluations

| Field | Value |
|-------|-------|
| FK | completion version + requirement def version |
| UNIQUE | `(completion_outcome_version_id, requirement_definition_version_id)` recommended |

### M11 — graduation_approvals

| Field | Value |
|-------|-------|
| Composite FK | enrollment+school |
| UNIQUE | `(completion_outcome_version_id, attempt_no)` |
| POLICY | Human approval only — no invented roles |

### M12 — graduation_awards

| Field | Value |
|-------|-------|
| UNIQUE | `(school_id, enrollment_id)` |
| Composite FK | enrollment+school |

### M13 — graduation_award_versions

| Field | Value |
|-------|-------|
| UNIQUE | `(graduation_award_id, version_no)` |
| Partial UNIQUE | `(graduation_award_id) WHERE is_current_issued` (and not revoked) |
| FK | approval, completion version, award |

### M14 — outcome_supersessions

| Field | Value |
|-------|-------|
| Append-only edges | pred/succ version ids + kind |
| CHECK | predecessor ≠ successor |

### M15 — revocation_records

| Field | Value |
|-------|-------|
| FK | award_version RESTRICT |
| Append-only | |

### M16 — Supporting indexes

| Field | Value |
|-------|-------|
| Purpose | REQUIRED/RECOMMENDED from INDEX plan; skip REDUNDANT |
| Separation | Allows EXPLAIN-driven adjustment without table rewrite |

### M17 — RLS

| Field | Value |
|-------|-------|
| Purpose | ENABLE+FORCE + fail-closed policy per table |
| Pattern | Copy `student_grades_school_isolation` |
| down() | Document: DISABLE RLS **not** automatic production rollback — see rollback plan |
| Risk | HIGH if mis-ordered with data |

### M18 — Triggers

| Field | Value |
|-------|-------|
| Purpose | reject DELETE; reject illegal UPDATE on official; denorm consistency |
| Pattern | Mirror `exams.reject_student_grades_delete` |
| Risk | MEDIUM |

---

## Application dependency graph (not migrations)

```text
DB M01–M18 green
  → Domain Graduation context (VOs, exceptions)
  → Repository interfaces + Eloquent
  → Commands: evaluate/publish/approve/issue/supersede/revoke
  → Queries: current state / historical reconstruct
  → Outbox event classes (PROPOSED names)
  → Consumer: StudentStatus sync + rebuild
  → HTTP/Inertia optional later wave
  → Tests + architecture:validate --fitness + security:validate
```

---

## Deployment risk by migration

| Band | Migrations | Risk |
|------|------------|------|
| Empty DDL | M01–M15 | LOW if no traffic |
| Indexes | M16 | LOW–MED write amp |
| RLS | M17 | HIGH if app lacks SchoolContext |
| Triggers | M18 | MED — breaks bad writers |

**Expand/migrate/verify:** Add empty tables first (expand); app ignore until feature flag; enable writes after RLS+tests (switch). Zero-downtime for empty schema add is feasible; not an SLA claim.
