# DATABASE PHASE 3A GATE

```text
DATABASE PHASE 3A GATE
STATUS: PASS
```

**Date:** 2026-09-10  
**Scope:** Exam Foundation only (`exam_types`, `exams`, `exam_sessions`, `exam_enrollments`)  
**Blueprint object count:** **87** (unchanged; column enrichments only)

---

## 1. Executive Summary

Phase 3A delivered the examination infrastructure on schema `exams` with fail-closed RLS + FORCE on school-scoped tables, composite school-consistency FKs, domain status enums, and PostgreSQL integration tests on `sis_test`.

Authoritative grades and results were **not** implemented.

```text
PHASE 3A GATE:
PASS
```

---

## 2. Scope Implemented

| Item | Status |
|------|--------|
| `exams.exam_types` | DONE |
| `exams.exams` | DONE |
| `exams.exam_sessions` (+ denorm `school_id`) | DONE |
| `exams.exam_enrollments` (+ denorm `school_id`) | DONE |
| Fail-closed RLS + FORCE on school-scoped exam tables | DONE |
| Domain enums (`ExamStatus`, `ExamSessionStatus`, `ExamEnrollmentStatus`) | DONE |
| Structural + PG RLS + CHECK/integrity tests | DONE |
| `enrollments (id, school_id)` unique support index for composite FK | DONE |

## 3. Scope Explicitly Not Implemented

| Item | Deferred to |
|------|-------------|
| `student_grades` | Phase 3B |
| `term_results` | Phase 3C |
| `annual_results` | Phase 3C |
| `transcripts` | Phase 3D |
| GPA / ranking / finalization / grade correction | Phase 3B+ |
| Exam/Grade Application handlers / UI / ERP | Out of 3A |

---

## 4. Tables Created

### `exams.exam_types`

| Aspect | Detail |
|--------|--------|
| Purpose | Global exam-type catalog (codes as data, not hard-coded DDL vocabulary) |
| PK | `SMALLINT` IDENTITY |
| Columns | `code` UNIQUE, `name`, `weight_percentage` |
| CHECK | `weight_percentage BETWEEN 0 AND 100` |
| RLS | **None** (global reference) |
| `school_id` | N/A |

### `exams.exams`

| Aspect | Detail |
|--------|--------|
| Purpose | Logical examination (not student scores) |
| PK | `BIGINT` IDENTITY |
| FKs | `academic_year_id` → years; `school_id` → schools; `term_id` → terms; `exam_type_id` → exam_types — all **RESTRICT** |
| Columns | `name`, `start_date`, `end_date`, `status`, timestamps |
| UNIQUE | `(id, school_id)` for composite child FKs |
| CHECK | `end_date >= start_date`; `status BETWEEN 1 AND 5` |
| Indexes | `(academic_year_id, school_id)` school-year list; `(status)` status filter |
| RLS | ENABLE + **FORCE**; policy `exams_school_isolation` on `school_id` |
| `school_id` why | Direct ownership for fail-closed RLS (blueprint + Admission pattern) |

### `exams.exam_sessions`

| Aspect | Detail |
|--------|--------|
| Purpose | Scheduled sitting of an exam for a subject |
| PK | `BIGINT` IDENTITY |
| FKs | Composite `(exam_id, school_id)` → exams; `subject_id` → subjects; `room_id` → rooms nullable; `school_id` → schools — **RESTRICT** |
| Columns | `session_date`, `start_time`, `end_time`, `max_grade`, `pass_grade`, `status`, `created_at` |
| CHECK | `end_time > start_time`; `pass_grade <= max_grade`; `status BETWEEN 1 AND 4` |
| Indexes | `(exam_id)`; `(subject_id, session_date)`; `(school_id)` RLS/schedule |
| RLS | ENABLE + **FORCE**; policy on denorm `school_id` |
| `school_id` why | Denorm for FORCE RLS without multi-join; composite FK keeps consistency with parent exam |

### `exams.exam_enrollments`

| Aspect | Detail |
|--------|--------|
| Purpose | Eligibility/participation seat — **not** a grade |
| PK | `BIGINT` IDENTITY |
| FKs | Composite `(exam_session_id, school_id)` → sessions; composite `(enrollment_id, school_id)` → `enrollment.enrollments`; `school_id` → schools — **RESTRICT** |
| Columns | `seat_number` nullable, `status`, `created_at` |
| UNIQUE | `(exam_session_id, enrollment_id)` |
| CHECK | `status BETWEEN 1 AND 5` |
| Indexes | `(exam_session_id)`; `(enrollment_id)`; `(school_id)` |
| RLS | ENABLE + **FORCE**; policy on denorm `school_id` |
| No score columns | Confirmed by structural test |
| `school_id` why | RLS + prevents cross-school seating via composite FK to enrollments |

---

## 5. Relationship Diagram

```text
organization.schools
academic.academic_years
academic.terms
exams.exam_types (global)
        │
        ▼
   exams.exams  ←── school_id (RLS)
        │
        ▼
exams.exam_sessions  ←── school_id (denorm + composite FK)
        │                 subject_id → curriculum.subjects
        │                 room_id → organization.rooms (nullable)
        ▼
exams.exam_enrollments  ←── school_id (denorm + composite FKs)
        │
        └── enrollment_id → enrollment.enrollments
                              └── student_id → students.students
```

---

## 6. Security

| Scenario | Result |
|----------|--------|
| School A → School A exam data | **ALLOW** |
| School A → School B exam data | **DENY** |
| School B → School A exam data | **DENY** |
| No school context | **DENY** |
| Invalid school context | **DENY** |
| `exam_types` without context | Visible (global catalog — intentional) |

PostgreSQL suite: `ExamFoundationRlsPostgreSqlTest` — **PASS** (2/2) via non-superuser `sis_rls_tester`.

---

## 7. PostgreSQL

| Item | Value |
|------|-------|
| Live DB | `sis` (read-only verify; migrate additive only) |
| Version | PostgreSQL 18.2 |
| Test DB | `sis_test` |
| Exam tables | 4 |
| `student_grades` | **absent** |
| RLS + FORCE | exams, exam_sessions, exam_enrollments |
| Policies | 3 |
| CHECKs | 7 |
| Admission / Enrollment / Attendance | Intact (3 / 4 / 4 tables) |

---

## 8. Migration

| Migration | Status |
|-----------|--------|
| `2026_09_10_150000_phase3a_create_exams_tables` | Ran (batch 9) |
| `2026_09_10_150100_phase3a_enable_exams_rls` | Ran (batch 9) |
| Pending | **0** |
| History rewrite | **None** |

---

## 9. Tests

| Command | Result |
|---------|--------|
| `php artisan test --filter="Phase3AExamFoundation\|ExamStatusEnums"` | **PASS** 7 · **SKIPPED** 1 (sqlite cannot assert PG catalog — not claimed PASS) |
| `php artisan test -c phpunit.database-pgsql.xml --filter="ExamFoundation"` | **PASS** 9/9 |
| `php artisan test -c phpunit.database-pgsql.xml --filter="Admission\|ExamFoundation"` | **PASS** 14/14 |

---

## 10. Architecture

```text
php artisan architecture:validate --fitness
→ PASS (all fitness domains)
```

## 11. Security Validation

```text
php artisan security:validate
→ PASS
```

## 12. Database Verification

```text
php artisan sis:verify-database --no-seed-check
→ PASS (61 business tables; exams tables in required set)
```

---

## 13. Blueprint

| Category | Objects |
|----------|---------|
| **EXISTING count** | 87 |
| **PROPOSED count** | 87 (no new objects) |
| **IMPLEMENTED in 3A** | 4 of 5 `exams.*` tables (`student_grades` deferred) |
| **Column enrichment (approved by 3A audit)** | `school_id` on `exam_sessions`, `exam_enrollments`; UNIQUE `(id, school_id)` support; RLS notes |
| **DEFERRED** | `student_grades`, `results.*` |
| **REJECTED** | Partitioning exam headers; scores on enrollments; hard-delete cascades |

---

## 14. Risks

| Risk | Severity | Mitigation / Residual |
|------|----------|------------------------|
| Denorm `school_id` drift | MEDIUM | Composite FKs lock parent consistency |
| Writers forget GUC | MEDIUM | FORCE RLS fail-closed (proven in tests) |
| No app handlers yet | LOW | Expected — DB foundation only |
| Enrollment/attendance still without FORCE RLS | MEDIUM | Pre-existing; out of 3A scope |
| Grade SSOT still future | — | Explicitly deferred to 3B |

---

## 15. Deferred

```text
student_grades → Phase 3B
term_results → Phase 3C
annual_results → Phase 3C
transcripts → Phase 3D
```

---

## 16. Files Changed

| File | Purpose |
|------|---------|
| `database/migrations/2026_09_10_150000_phase3a_create_exams_tables.php` | Tables + CHECKs + composite FKs |
| `database/migrations/2026_09_10_150100_phase3a_enable_exams_rls.php` | RLS + FORCE |
| `app/Domain/Exams/ValueObjects/*.php` | Status enums |
| `app/Database/DatabaseFoundationVerifier.php` | Require exam foundation tables |
| `tests/Feature/Database/Phase3AExamFoundationSchemaTest.php` | Structural |
| `tests/Feature/Security/PostgreSql/ExamFoundationRlsPostgreSqlTest.php` | RLS |
| `tests/Feature/Database/PostgreSql/ExamFoundationCheckConstraintPostgreSqlTest.php` | CHECK/UNIQUE/FK |
| `tests/Unit/Domain/Exams/ExamStatusEnumsTest.php` | Enum semantics |
| `tests/Support/Database/PostgreSqlRlsActor.php` | Grant `exams` (+ `results`) schema |
| `.cursor/architecture/database-blueprint.md` | 3A enrichment notes |

---

## 17. Final Status

```text
PHASE 3A GATE:
PASS
```

---

```text
HUMAN APPROVAL REQUIRED

Phase 3A implementation is complete.

No Phase 3B implementation was started.
No student_grades table was created.
No term_results table was created.
No annual_results table was created.
No transcript table was created.
No ERP implementation was started.

Await explicit human approval before Phase 3B.
```
