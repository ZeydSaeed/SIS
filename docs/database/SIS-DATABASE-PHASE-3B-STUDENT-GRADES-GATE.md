# DATABASE PHASE 3B GATE — STUDENT GRADES

```text
DATABASE PHASE 3B GATE
STATUS: PASS WITH CONDITIONS
```

**Date:** 2026-09-10  
**Human approval reference:** Explicit approval of Phase 3B after `SIS-DATABASE-PHASE-3B-STUDENT-GRADES-AUDIT.md`  
**Blueprint objects:** **87** (enrichment of existing `student_grades` definition; no new objects)

---

## Executive Summary

Phase 3B delivered authoritative `exams.student_grades` as a **LIST-partitioned** table by `academic_year_id`, with fail-closed **FORCE RLS**, composite school-consistency FKs, score/absent CHECKs, VOID+INSERT correction model (`is_current` + `correction_of_grade_id`), hard-delete trigger block, and PostgreSQL integration tests on `sis_test`.

Derived results (`term_results`, `annual_results`, `transcripts`) were **not** created.

```text
PHASE 3B GATE: PASS WITH CONDITIONS
```

### Conditions

1. **Live `sis` has zero academic years** → **zero year partitions** after migrate (by design: no DEFAULT). Partitions are created via `StudentGradesPartitionManager::ensurePartitionForAcademicYear($id)` when years exist/are added.
2. **Application command handlers / outbox events** for grade entry/correction are **not** shipped in this slice (DB + domain VO + tests only, matching Phase 3A foundation style). Wire handlers before production grade entry.
3. **Deeper correction-cycle detection** beyond self-FK CHECK remains an application invariant.

---

## Human Approval Reference

Approved implementation of Phase 3B only after audit review. Phase 3C/3D explicitly excluded.

---

## Implemented Schema

| Object | Status |
|-------|--------|
| `exams.student_grades` (parent, partitioned) | DONE |
| Year partitions `student_grades_ay_{id}` | Created for years present at migrate/test time |
| DEFAULT partition | **None** |
| `results.term_results` / `annual_results` / `transcripts` | **Absent** |

### Columns (authoritative)

`id`, `academic_year_id`, `school_id`, `exam_enrollment_id`, `exam_session_id`, `enrollment_id`, `student_id`, `subject_id`, `score`, `max_score`, `is_absent`, `status`, `is_current`, `correction_of_grade_id`, `entered_by`, `entered_at`, `finalized_at`, `created_at`, `updated_at`

---

## Migration List

| Migration | Purpose |
|-----------|---------|
| `2026_09_10_160000_phase3b_create_student_grades` | Partitioned table, FKs, CHECKs, indexes, reject-DELETE trigger, support uniques |
| `2026_09_10_160100_phase3b_enable_student_grades_rls` | ENABLE + FORCE RLS + fail-closed policy |

Live `sis`: both **Ran**; **0 pending**.

---

## Partition Design

```text
PARTITION BY LIST (academic_year_id)
PRIMARY KEY (id, academic_year_id)
NO DEFAULT PARTITION
```

**Ops procedure for a new academic year:**

```text
1. INSERT academic.academic_years (...)
2. App\Database\StudentGradesPartitionManager::ensurePartitionForAcademicYear($id)
3. Validate partition exists
4. Allow grade writes for that year
```

Missing partition → INSERT **fails** (no silent routing).

---

## Constraint Matrix

| Constraint | Purpose |
|------------|---------|
| `student_grades_score_absent_check` | Absent ⇒ score NULL; else 0 ≤ score ≤ max_score |
| `student_grades_max_score_check` | max_score > 0 |
| `student_grades_status_check` | status 1–5 |
| `student_grades_void_not_current_check` | Voided ⇒ is_current false |
| `student_grades_no_self_correction_check` | correction_of_grade_id ≠ id |
| Partial UNIQUE `student_grades_current_enrollment_uidx` | One current grade per `(exam_enrollment_id, academic_year_id)` |
| BEFORE DELETE trigger | Hard delete forbidden |

---

## Foreign-Key Matrix

| FK | Target | ON DELETE |
|----|--------|-----------|
| academic_year_id | academic_years | RESTRICT |
| school_id | schools | RESTRICT |
| (exam_enrollment_id, school_id) | exam_enrollments | RESTRICT |
| (exam_session_id, school_id) | exam_sessions | RESTRICT |
| (enrollment_id, school_id) | enrollments | RESTRICT |
| (enrollment_id, academic_year_id) | enrollments | RESTRICT |
| student_id | students | RESTRICT |
| subject_id | subjects | RESTRICT |
| entered_by | users | SET NULL |
| (correction_of_grade_id, academic_year_id) | student_grades | RESTRICT |

Support indexes added: `exam_enrollments (id, school_id)`, `enrollments (id, academic_year_id)` (idempotent IF NOT EXISTS).

---

## Index Matrix

| Index | Query purpose |
|-------|----------------|
| PK `(id, academic_year_id)` | Identity + partition |
| Partial UNIQUE current enrollment | SSOT / duplicate prevention |
| `(student_id, academic_year_id)` | Student year / transcript prep |
| `(enrollment_id, academic_year_id)` | Enrollment profile |
| `(school_id, academic_year_id, subject_id)` | Teacher entry grids |
| `(exam_session_id, academic_year_id)` | Session markbook |
| Partial `(correction_of_grade_id, academic_year_id)` | Correction chain |

---

## RLS / FORCE RLS Evidence

| Item | Live `sis` |
|------|------------|
| ENABLE RLS | **Y** |
| FORCE RLS | **Y** |
| Policy | `student_grades_school_isolation` (USING + WITH CHECK fail-closed) |
| Relkind | `p` (partitioned) |

PG tests use non-superuser `sis_rls_tester`.

---

## Security Evidence

| Check | Result |
|-------|--------|
| School A → A | ALLOW (tested) |
| School A → B | DENY (tested) |
| No / invalid GUC | DENY (tested) |
| WITH CHECK cross-school INSERT | DENY (tested) |
| `security:validate` | **PASS** |
| Protected DB guard | Unchanged; tests use `sis_test` |

---

## Score Semantics

| Field | Role |
|-------|------|
| `score` | Authoritative achieved score (NULL if absent) |
| `max_score` | Historical snapshot (not live session lookup) |
| `is_absent` | Absence flag; mutually exclusive with score |

---

## Correction / Void Lifecycle

```text
Grade A (current) → void (is_current=false, status=Voided)
  → Grade B (current, correction_of_grade_id=A)
  → void B → Grade C (current, correction_of_grade_id=B)
```

Status VO: `App\Domain\Exams\ValueObjects\GradeStatus` (Draft=1 … Voided=5).

---

## Current-Grade Invariant

```text
UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current
```

Partition key included (PostgreSQL requirement). App must keep `academic_year_id` aligned with enrollment (also enforced by composite FK).

---

## Audit / Outbox / Idempotency Integration

Infrastructure (`audit.outbox_messages`, `idempotency_keys`) already exists (Enrollment pattern).

**This gate:** schema foundation only — grade Commands/Outbox events **deferred** until application entry API (Condition 2). Do not invent a parallel audit system.

---

## Test Results

| Command | Result |
|---------|--------|
| `php artisan test --filter="Phase3BStudentGrades\|GradeStatusTest"` | **PASS** 4 · **SKIPPED** 1 (sqlite catalog) |
| `php artisan test -c phpunit.database-pgsql.xml --filter=StudentGrades` | **PASS** 6/6 |
| `php artisan test -c phpunit.database-pgsql.xml --filter="Admission\|ExamFoundation\|StudentGrades"` | **PASS** 20/20 |
| `php artisan security:validate` | **PASS** |
| `php artisan sis:verify-database --no-seed-check` | **PASS** (62 business tables) |
| `php artisan architecture:validate --fitness` | **PASS** |

---

## PostgreSQL Catalog Evidence (live `sis`)

```text
PostgreSQL 18.2
database = sis
student_grades relkind = p
RLS = Y, FORCE = Y
DEFAULT partitions = 0
year partitions = 0 (no academic_years rows)
policy = student_grades_school_isolation
results.term_results = 0
results.annual_results = 0
results.transcripts = 0
```

---

## Live Database Verification

| Item | Value |
|------|-------|
| Migrations 160000 / 160100 | Ran |
| Pending | 0 |
| `student_grades` | Exists (partitioned) |
| Phase 3C/3D tables | **Do not exist** |
| Admission / Exam foundation | Intact |

No `migrate:fresh` / wipe against `sis`.

---

## Regression Results

Admission + ExamFoundation + StudentGrades PG suite: **20/20 PASS**.

---

## Blueprint Impact

| Item | Status |
|------|--------|
| Object count | **87** unchanged |
| `student_grades` definition | Enriched (approved 3B) |
| New objects | None |

---

## Risks / Limitations

| Risk | Notes |
|------|-------|
| Empty year list on live | Must create partitions when seeding years |
| No grade handlers yet | Required before production entry |
| Correction cycles >1 hop | App validation recommended |
| Attendance still has DEFAULT partition | Unrelated; grades intentionally stricter |

---

## Rollback / Recovery

```text
Migrate down 160100 → drop policy/FORCE
Migrate down 160000 → DROP TABLE exams.student_grades CASCADE + trigger/function
```

Does not touch Phase 3A exam header tables beyond additive support indexes.

---

## Phase 3C Boundary Confirmation

```text
term_results — NOT created
annual_results — NOT created
transcripts — NOT created
GPA/ranking tables — NOT created
```

---

## Final Gate

```text
PHASE 3B GATE: PASS WITH CONDITIONS
```

---

```text
HUMAN APPROVAL REQUIRED FOR PHASE 3C.

Phase 3B implementation is complete.
No Phase 3C or Phase 3D implementation was performed.
Await explicit human approval before proceeding.
```
