# DATABASE PHASE 3B — STUDENT GRADES

# ARCHITECTURE & DEPENDENCY AUDIT

```text
STATUS: AUDIT + DESIGN + PLAN ONLY
DATE: 2026-09-10
PRECONDITION: PHASE 3A GATE PASS
BLUEPRINT OBJECTS: 87 (unchanged by this audit)
```

---

## 1. Executive Summary

Phase 3A delivered exam infrastructure without scores. Live `sis` still has **`exams.student_grades` ABSENT**. Blueprint already lists partitioned `student_grades` as a P0 object; ADR-002 requires LIST partition by `academic_year_id` from year one.

**Core invariant:**

```text
exams.student_grades = SINGLE SOURCE OF TRUTH for authoritative scores
term_results / annual_results / transcripts / GPA / rankings = DERIVED
```

**Key design locks recommended for future implementation approval:**

| Decision | Recommendation |
|----------|----------------|
| Natural key (active grade) | **One current grade per `exam_enrollment_id`** |
| Primary FK | `exam_enrollment_id` → `exams.exam_enrollments` (Phase 3A seating) |
| Denorm | `school_id`, `academic_year_id`, `student_id`, `enrollment_id`, `exam_session_id`, `subject_id` |
| Score SSOT | `score` + `max_score` snapshot + `is_absent` |
| Lifecycle | Draft → Entered → Submitted → Finalized → Voided (+ Corrected via void+replace) |
| Correction | **VOID + INSERT replacement** + `correction_of_grade_id` + `is_current` |
| Hard delete | **FORBIDDEN** |
| Partition | **LIST (`academic_year_id`) NOW** — **SAFE** (attendance pattern); **no DEFAULT partition** |
| RLS | Fail-closed + FORCE + direct `school_id` |
| Separate `grade_history` | **REJECTED** for v1 |

```text
PHASE 3B PLAN READY FOR HUMAN APPROVAL
```

Implementation remains **blocked** until explicit human approval of Phase 3B **and** the blueprint enrichment listed in §28.

---

## 2. Current Database State

Read-only inspection of live database `sis` (PostgreSQL **18.2**):

| Area | Live state |
|------|------------|
| `exams.exam_types` / `exams` / `exam_sessions` / `exam_enrollments` | Present |
| `exams.student_grades` | **Absent (0)** |
| `results.*` tables | Absent (schema empty) |
| `enrollment.enrollments` (+ classes/sections) | Present |
| `students.students` | Present |
| `academic.academic_years` / `terms` | Present |
| `curriculum.subjects` | Present |
| `organization.schools` | Present |
| `attendance.records` | LIST partitioned; only `records_default` child |
| `audit.outbox_messages` / `idempotency_keys` | Present |
| Exam RLS | `exams`, `exam_sessions`, `exam_enrollments`: ENABLE + FORCE |
| Phase 3B migrations | None |

No DDL was executed for this audit.

---

## 3. Existing Academic Relationships

### Canonical entities (reuse — do not duplicate)

```text
organization.schools
academic.academic_years → academic.terms
students.students
enrollment.classes → sections → enrollments (school_id, academic_year_id, student_id)
curriculum.subjects
exams.exam_types → exams → exam_sessions → exam_enrollments
```

### Phase 3A verified chain

```text
exams.exams
  (school_id, academic_year_id, term_id, exam_type_id)
        ↓
exams.exam_sessions
  (exam_id, school_id, subject_id, max_grade, pass_grade)
        ↓
exams.exam_enrollments
  (exam_session_id, school_id, enrollment_id)
  UNIQUE(exam_session_id, enrollment_id)
        ↓
enrollment.enrollments
  (student_id, school_id, academic_year_id, class_id, section_id)
```

Composite FKs already enforce `school_id` consistency between exams ↔ sessions ↔ exam_enrollments ↔ enrollments.

### Attendance precedent (partitioning + denorm)

```text
attendance.records
  PRIMARY KEY (id, academic_year_id)
  PARTITION BY LIST (academic_year_id)
  DEFAULT partition exists
  denorm: school_id, student_id, enrollment_id, academic_year_id
  UNIQUE (session_id, student_id, academic_year_id)
```

Grades should follow the same PK/partition pattern, with **stricter partition lifecycle** (no silent DEFAULT — see §11–12).

---

## 4. Student Grade Domain Definition

**One `student_grades` row means:**

```text
The authoritative score outcome for one exam seating eligibility
= one exam_enrollment (student-in-session) at a point in version history
```

Not:

- a term aggregate
- a transcript line
- a free-floating subject mark without exam seating
- a UI worksheet cell

Participation/absence of seating remains on `exam_enrollments.status`; the **numeric academic outcome** lives on `student_grades`.

---

## 5. SSOT Decision

| Store | Classification |
|-------|----------------|
| `exams.student_grades` | **AUTHORITATIVE** |
| `results.term_results` | **DERIVED** (Phase 3C) |
| `results.annual_results` | **DERIVED** (Phase 3C) |
| `results.transcripts` | **DERIVED artifact metadata** (Phase 3D) |
| GPA / rank / dashboards / MVs | **DERIVED / PROJECTION** |
| `exam_enrollments` | Seating only — **never** scores |
| Excel / JSON blobs / UI state | **FORBIDDEN** as authority |

**Rule:** No second table may accept teacher-entered scores as independent truth.

---

## 6. Natural Key Decision

### Preferred FK path (verified against Phase 3A)

```text
student_grades.exam_enrollment_id
        → exam_enrollments
        → exam_sessions → exams → year/school/term/type
        → enrollment.enrollments → student
```

### Why not blueprint-only `(exam_session_id, student_id)`?

| Approach | Pros | Cons |
|----------|------|------|
| Blueprint UNIQUE `(exam_session_id, student_id)` | Already documented | Skips Phase 3A seating; can grade non-enrolled sitters |
| **`exam_enrollment_id` (recommended)** | Ties grade to eligibility; inherits school/session/enrollment integrity | Requires blueprint enrichment before DDL |

**Natural key of an *active* grade:**

```text
exam_enrollment_id   WHERE is_current = true
```

**Surrogate PK (partition-compatible):**

```text
PRIMARY KEY (id, academic_year_id)
```

**Decision:** Use `exam_enrollment_id` as the business natural key; keep denormalized `exam_session_id` / `student_id` / `enrollment_id` / `subject_id` for query locality (same rationale as attendance).

---

## 7. Score Model

| Field | Role | Notes |
|-------|------|-------|
| `score` | **AUTHORITATIVE** | `NUMERIC(5,2)` nullable when `is_absent` |
| `max_score` | **AUTHORITATIVE snapshot** | Copied from `exam_sessions.max_grade` at entry/finalize; session defaults may change later |
| `is_absent` | **AUTHORITATIVE** | If true, `score` must be NULL |
| `percentage` | **DERIVED** | `ROUND((score / max_score) * 100, 2)` — compute in app; optional freeze at finalize |
| `letter_grade` | **FROZEN DERIVED** (optional at finalize) | Policy snapshot — not independently editable |
| `grade_points` | **FROZEN DERIVED** (optional) | Same |
| `is_pass` | **FROZEN DERIVED** | Compare score vs session `pass_grade` snapshot at finalize |

**Rounding rule (recommended):** half-up to 2 decimal places for percentage; letter/GPA scales from school/system settings — not hard-coded in DDL.

**CHECK (recommended):**

```text
(is_absent = true AND score IS NULL)
 OR (is_absent = false AND score IS NOT NULL AND score >= 0 AND score <= max_score)
AND max_score > 0
```

Rename blueprint column `grade` → `score` in enrichment (clearer; avoid overload with “letter grade”).

---

## 8. Grade Lifecycle

| Status | Value | Meaning | Mutate score? |
|--------|------:|---------|---------------|
| Draft | 1 | Teacher working copy | YES (owner/role) |
| Entered | 2 | Saved draft complete | YES |
| Submitted | 3 | Sent for review | LIMITED |
| Finalized | 4 | Locked academic fact | **NO** (correct via void+replace) |
| Voided | 5 | Superseded / cancelled; retained | NO |

Domain VO (future): `App\Domain\Exams\ValueObjects\GradeStatus`.

| Question | Answer |
|----------|--------|
| Who can change? | Role-gated commands (teacher entry / registrar finalize) — app auth **and** RLS school scope |
| Before finalization? | Yes, within Draft/Entered/Submitted rules |
| After finalization? | Only via **Correct** (void current + insert replacement) or controlled **Reopen** |
| Reopen? | Elevated permission + reason + audit/outbox; sets status back from Finalized → Submitted/Entered **only if no dependent published transcript** (app rule); prefer void+replace over reopen when possible |

---

## 9. Correction / Void Model

**Selected smallest robust model:**

```text
VOID current row (is_current=false, status=Voided)
  + INSERT replacement (is_current=true)
  + correction_of_grade_id → prior grade PK
  + outbox GradeCorrected
  + security_audit
```

| Alternative | Verdict |
|-------------|---------|
| In-place overwrite after finalize | **REJECTED** |
| Separate `grade_history` table | **REJECTED for v1** (voided rows + audit/outbox suffice) |
| Append-only versions without `is_current` | Heavier; deferred |

---

## 10. No-Hard-Delete Policy

```text
student_grades → NO HARD DELETE
```

| Operation | DB | App |
|-----------|----|-----|
| DELETE | Deny via REVOKE / trigger / policy | Never expose |
| VOID | UPDATE status + is_current | Command |
| CASCADE from parent | **RESTRICT** on all academic FKs | — |

Partitions may later be **DETACH → archive** (ops), not row DELETE.

---

## 11. Partitioning Decision

```text
LIST partition by academic_year_id:
SAFE
```

**Justification:**

- ADR-002 Accepted; blueprint P0; attendance already proven on PG 18.2
- Query patterns are year-scoped (transcript year, teacher year, reports)
- Archive = detach year partition
- Estimated baseline ~2.7M rows/year (45K × 15 subjects × 4 exams) — design estimate, not measured production

**Partition-compatible requirements (mandatory):**

| Constraint | Design |
|------------|--------|
| PK | `(id, academic_year_id)` |
| Active uniqueness | `UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current` |
| FKs outbound | To non-partitioned parents — OK |
| FKs inbound | Future children must reference `(id, academic_year_id)` or avoid FK |

```text
LIST partition by academic_year_id: SAFE
(with PK/UNIQUE including academic_year_id; no silent DEFAULT)
```

---

## 12. Partition Lifecycle

```text
New Academic Year row
      ↓
Controlled migration / admin op: CREATE PARTITION FOR (year_id)
      ↓
Validate partition exists
      ↓
Enable grade writes for that year
```

| Choice | Recommendation |
|--------|----------------|
| Auto-create partition on year insert | **Deferred** — prefer explicit migration/ops job |
| DEFAULT partition | **NO** for `student_grades` (stricter than attendance) |
| Missing partition INSERT | **FAIL** (do not silently accept) |
| Old years | Remain online until archive ADR; then DETACH |

Attendance’s DEFAULT partition is a known softer pattern; grades must not copy that softness.

---

## 13. School Ownership

**Direct `school_id` on `student_grades` — REQUIRED.**

| Consistency edge | Enforcement |
|------------------|-------------|
| grades.school_id = exam_enrollments.school_id | Composite FK `(exam_enrollment_id, school_id)` → enrollments seating |
| grades.school_id = enrollments.school_id | Composite FK `(enrollment_id, school_id)` |
| grades.academic_year_id = enrollments.academic_year_id | Composite FK `(enrollment_id, academic_year_id)` **or** CHECK via trigger; prefer composite unique support indexes |
| grades.exam_session_id / subject_id | Derived from exam_enrollment → session; denorm + composite FK where practical |

Do not rely on Laravel alone.

---

## 14. RLS Design

```text
FAIL-CLOSED + FORCE ROW LEVEL SECURITY + school_id = GUC app.current_school_id
```

| Scenario | Decision |
|----------|----------|
| School A → A grades | **ALLOW** |
| School A → B | **DENY** |
| School B → A | **DENY** |
| No context | **DENY** |
| Invalid context | **DENY** |
| Background worker | **CONTROLLED** — set GUC per school batch |
| System/admin | **CONTROLLED** — privileged role + audit; not anonymous bypass |

Policy shape (design only — mirror Admission/3A):

```sql
-- conceptual
USING (
  NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
  AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
)
```

Tests must use non-superuser actor (`sis_rls_tester`) on `sis_test`.

---

## 15. FK Design

| Column | Target | ON DELETE |
|--------|--------|-----------|
| `exam_enrollment_id` (+ school) | `exam_enrollments` | **RESTRICT** |
| `enrollment_id` (+ school) | `enrollments` | **RESTRICT** |
| `student_id` | `students` | **RESTRICT** |
| `academic_year_id` | `academic_years` | **RESTRICT** |
| `school_id` | `schools` | **RESTRICT** |
| `exam_session_id` (+ school) | `exam_sessions` | **RESTRICT** |
| `subject_id` | `subjects` | **RESTRICT** |
| `entered_by` / `finalized_by` | `users` | SET NULL / RESTRICT (prefer RESTRICT if required audit) |
| `correction_of_grade_id` | self `(id, academic_year_id)` | **RESTRICT** |

**No CASCADE** that can wipe academic history.

Support indexes on parents for composite FKs (as Phase 3A did for enrollments).

---

## 16. Uniqueness Design

**Business rule:** Exactly **one current** authoritative grade per exam enrollment.

```text
UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current = true
```

| Scenario | Allowed? |
|----------|----------|
| Second current grade same enrollment | **NO** |
| Voided prior + new current | **YES** |
| Multiple attempts as separate exam_enrollments | **YES** (new seating / retake session) |
| Multiple components without seating | **OUT OF SCOPE** (no assessment_id in 3B) |

Blueprint UNIQUE `(exam_session_id, student_id)` is **superseded** by exam_enrollment uniqueness (stricter and Phase 3A-aligned). Optional secondary unique `(exam_session_id, student_id, academic_year_id) WHERE is_current` may be added if denorm must independently enforce — usually redundant if exam_enrollment unique.

---

## 17. Attempts Design

| Concern | Placement |
|---------|-----------|
| First sitting | `exam_enrollments` + one current grade |
| Retake | New `exam_session` and/or new `exam_enrollment` (new eligibility) → new grade row |
| Correction of marking error | Void+replace on **same** `exam_enrollment_id` |
| Separate `attempts` table | **REJECTED for 3B** |

---

## 18. Index Strategy

| Index | Use case | Selectivity / benefit | Write cost |
|-------|----------|----------------------|------------|
| PK `(id, academic_year_id)` | Identity + partition | Required | Base |
| Partial UNIQUE `(exam_enrollment_id, academic_year_id) WHERE is_current` | Prevent duplicate SSOT | High | Medium |
| `(student_id, academic_year_id)` | Transcript / student year | High prune | Medium |
| `(enrollment_id, academic_year_id)` | Enrollment academic profile | High | Medium |
| `(school_id, academic_year_id, subject_id)` | Teacher entry grids / class subject | High with RLS | Medium |
| `(exam_session_id, academic_year_id)` | Session markbook | High | Medium |
| `(status, academic_year_id)` partial WHERE status IN (Submitted) | Finalization queues | Medium | Low |

**Reject:** indexing every FK blindly; GIN on scores; covering indexes without EXPLAIN evidence.

---

## 19. Scale Analysis

| Scale | Approx grade rows/year (order-of-magnitude) | Partition help |
|-------|-----------------------------------------------|----------------|
| 1K students | ~60K | Modest |
| 10K | ~600K | Useful |
| 45K baseline | ~2.7M | **Material** (ADR-002) |
| 100K+ | multi-million | Essential for prune/vacuum/archive |
| Multi-school / multi-year | Linear × years | Year prune dominates |

Exact production counts are **unknown** — estimates follow Phase D / capacity docs. Partitioning still justified by ADR + attendance precedent + archive strategy, not by a fabricated live measurement.

---

## 20. Normalization

| Form | Assessment |
|------|------------|
| 1NF | Atomic score fields; no score arrays |
| 2NF | Surrogate PK; natural key via exam_enrollment |
| 3NF | Controlled denorm of school/year/student/subject/session for RLS + partition + query locality — **intentional**, documented (same as attendance) |

**Avoid:** JSON score bags; duplicating student names; storing term totals as authoritative.

---

## 21. CQRS Decision

| Area | Pattern |
|------|---------|
| Grade entry / submit / finalize / correct / void | **SIMPLE command handlers** (Clean Architecture) + idempotency + outbox |
| Teacher markbook read | Same DB read of `student_grades` — **CRUD-style Query** |
| Transcript / heavy reports | **CQRS + projection** justified in **3C/3D** (derived tables / jobs), not required to write grades |
| Analytics | Projection / MV later — evidence-gated |

Do not introduce a grade event bus beyond existing outbox.

---

## 22. Audit / Outbox / Idempotency

Existing:

- `audit.outbox_messages`
- `audit.idempotency_keys`
- Enrollment handlers already stage outbox + idempotency

| Event | Outbox | Idempotency | Security audit |
|-------|--------|-------------|----------------|
| GradeCreated | YES | YES | YES |
| GradeSubmitted | YES | YES | optional |
| GradeFinalized | YES | YES | YES |
| GradeCorrected | YES | YES | YES |
| GradeVoided | YES | YES | YES |
| GradeReopened | YES | YES | YES (elevated) |

`audit.audit_logs` (blueprint) remains optional — do not block 3B if security_audit + outbox cover compliance initially.

---

## 23. Database Invariants

| Invariant | Layer |
|-----------|-------|
| No hard DELETE | DB REVOKE/trigger + app |
| One current grade per exam_enrollment | Partial UNIQUE |
| score/max/absent consistency | CHECK |
| school_id alignment | Composite FKs |
| Partition key present | NOT NULL academic_year_id + PK |
| Finalized score immutable | **App command rules** + recommended BEFORE UPDATE trigger rejecting score changes when `status=Finalized` and `is_current` |
| Cross-school deny | FORCE RLS |
| Year reassignment after insert | Forbid UPDATE of `academic_year_id` / `exam_enrollment_id` (trigger) |

Volatile pedagogy (who may reopen) stays in **application authorization**, not CHECK.

---

## 24. Future 3C Compatibility

> Can term and annual results be completely recomputed from `student_grades` plus academic structure?

**YES**, for exam-based assessments, via:

```text
student_grades (current, non-void)
  → exam_enrollment → exam_session (subject, pass)
  → exam (term_id, exam_type_id, academic_year_id)
  → exam_types.weight_percentage
  → enrollment (student, class, section)
```

**Gap (documented, deferred):** continuous vocational / non-exam assessments not seated in `exam_enrollments` cannot contribute until a future assessment model exists. Do not invent parallel score tables in 3B.

---

## 25. Future 3D Compatibility

Transcripts can be generated from:

- current `student_grades` history (incl. void chain for audit)
- derived term/annual results
- enrollment + student identity
- stored file metadata only in `results.transcripts`

**Must remain permanently online (or archived intact):** finalized/voided grade rows for issued transcript years.

---

## 26. Retention

| Data | Class |
|------|-------|
| Current + voided `student_grades` | **NEVER hard-delete** |
| Finalization / correction outbox + security audit | **NEVER delete** (or legal retention window via archive) |
| Term/annual results | **REBUILDABLE** |
| Transcript PDF in object storage | **ARCHIVEABLE** metadata; regenerate if rules allow |
| Draft grades never finalized | Retain or void — still no hard delete preferred |

---

## 27. PostgreSQL Portability

| Feature | Class |
|---------|-------|
| NUMERIC / SMALLINT / TIMESTAMPTZ | Portable SQL |
| Composite FKs / RESTRICT | Portable |
| LIST partitioning + PK includes key | **PostgreSQL-specific but justified** (ADR-002) |
| Partial UNIQUE indexes | **PostgreSQL-specific but justified** |
| RLS + FORCE + GUC | **PostgreSQL-specific but justified** (security) |
| DEFAULT partition avoidance | Portable concept |
| IDENTITY | Portable enough / PG-first |

Isolate partition/RLS DDL behind `SchemaHelper::isPostgreSql()` (existing pattern). SQLite tests cover structure lightly; PG suite proves partition/RLS.

---

## 28. Blueprint Impact

```text
Current Blueprint objects = 87
Proposed new objects      = 0
Future count after 3B     = 87
```

`student_grades` is **already** in the 87. This audit proposes **column/constraint enrichments** (not new objects):

| Item | Classification |
|------|----------------|
| Table `exams.student_grades` partitioned | **APPROVED BY PRIOR ARCHITECTURE** (blueprint + ADR-002) |
| Add `school_id`, `status`, `is_current`, `exam_enrollment_id`, `max_score`, `correction_of_grade_id` | **RECOMMENDED** (must update blueprint **before** DDL) |
| Rename `grade` → `score` | **RECOMMENDED** |
| Optional frozen `percentage` / `letter_grade` / `is_pass` at finalize | **RECOMMENDED** |
| Partial unique on `exam_enrollment_id` | **RECOMMENDED** (replaces sole reliance on session+student) |
| No DEFAULT partition | **RECOMMENDED** |
| Separate `grade_history` | **REJECTED** (v1) |
| Attempts table | **REJECTED** (v1) |
| Scores on `exam_enrollments` | **REJECTED** |
| `term_results` / transcripts in 3B | **DEFERRED** (3C/3D) |

---

## 29. Risk Register

| Risk | Severity | Probability | Mitigation |
|------|---------:|------------:|------------|
| Grade deletion | CRITICAL | Low | No DELETE; RESTRICT FKs; tests |
| Mutation after finalization | CRITICAL | Medium | Status lock + void/replace + trigger |
| Cross-school grade access | CRITICAL | Medium | FORCE RLS + direct school_id + PG tests |
| Duplicate authoritative grades | CRITICAL | Medium | Partial UNIQUE (exam_enrollment_id, year) WHERE is_current |
| Partition routing failure | HIGH | Medium | No DEFAULT; pre-create year partition; INSERT fail closed |
| Academic-year partition management | HIGH | High | Ops/migration checklist per new year |
| Incorrect derived results | HIGH | Medium | 3C recompute-only writers; reconcile tests |
| Inconsistent school_id | HIGH | Medium | Composite FKs (3A pattern) |
| Excessive indexes | MEDIUM | Medium | Query-justified list only (§18) |
| Excessive CQRS complexity | MEDIUM | Low | Commands + outbox; projections in 3C/3D |
| Future schema migration | HIGH | Medium | Additive migrations; blueprint-first enrichments |
| Testing against live `sis` | CRITICAL | Low | ProtectedDatabaseGuard (Phase 2 remediation) |

---

## 30. Future 3B Implementation Plan

**Do not execute until human approval.**

1. **Human gate** — approve this plan + blueprint enrichment text  
2. **Update blueprint** — `student_grades` columns/constraints/indexes/partition notes  
3. **Migration A** — support unique indexes on parents if needed for composite FKs  
4. **Migration B** — `CREATE TABLE exams.student_grades (...) PARTITION BY LIST (academic_year_id)` with PK `(id, academic_year_id)`; **no DEFAULT**; create partitions for existing academic years present in DB  
5. **Migration C** — CHECKs, partial UNIQUE, secondary indexes  
6. **Migration D** — ENABLE + FORCE RLS + school isolation policy  
7. **Domain** — `GradeStatus` enum (+ optional score value object)  
8. **Application (minimal for foundation)** — optional; handlers may wait until after DB gate if scope is schema-only like 3A — **recommend schema+RLS+tests first**, handlers in same or immediate follow-on PR under architecture skill  
9. **Tests** — structural, PG partition routing, RLS, uniqueness, correction invariants  
10. **Validate** — `architecture:validate --fitness`, `security:validate`, `sis:verify-database --no-seed-check`  
11. **Live read-only verify** — catalog, partitions, policies  
12. **Phase 3B GATE report** — STOP before 3C  

Rollback: drop grades table/partitions/policies only; never rewrite history.

---

## 31. Future Test Plan

### Structural
Tables/columns/PK/FK/UNIQUE/CHECK/indexes/partition children; assert **no** scores on `exam_enrollments`; assert `term_results` still absent.

### PostgreSQL (`sis_test` only)
- INSERT routes to correct year partition  
- INSERT fails when partition missing (no DEFAULT)  
- Partial unique blocks second `is_current`  
- Void+replace succeeds  
- Composite FK rejects cross-school  
- CHECK rejects score > max / absent+score  

### Security RLS
School A→A ALLOW; A→B DENY; B→A DENY; empty GUC DENY; invalid GUC DENY (`sis_rls_tester`).

### Safety
`testing + sis` BLOCKED; `sis_test` ALLOWED (ProtectedDatabaseGuard).

---

## 32. Open Questions

Resolved **recommendations** awaiting human confirmation (not blockers if approved as-is):

1. Confirm **VOID+replace + `is_current`** as the correction model.  
2. Confirm blueprint enrichment: `exam_enrollment_id`, `school_id`, `status`, `max_score`, rename `grade`→`score`.  
3. Confirm **no DEFAULT partition** for grades (stricter than attendance).  
4. Confirm 3B scope = **schema + RLS + domain enums + tests** first; handlers in same approval or follow-on?  
5. Confirm frozen letter/pass columns at finalize vs compute-only forever.  
6. Who may REOPEN vs must CORRECT only? (role list)

---

## 33. Recommendation

```text
PHASE 3B PLAN READY FOR HUMAN APPROVAL
```

**Next action when approved:** update blueprint enrichment → implement partitioned `exams.student_grades` only → Phase 3B gate → stop.

Do **not** start 3C/3D in the same wave.

---

```text
HUMAN APPROVAL REQUIRED

This task was AUDIT + DESIGN + PLAN ONLY.

No student_grades table was created.
No partitions were created.
No grades were implemented.
No result tables were created.
No transcript tables were created.
No production database was modified.

Await explicit human approval before implementing Phase 3B.
```
