# Phase 2 — Admission Implementation Plan

**Phase:** 2 — Admission Domain (database)  
**Date:** 2026-09-10  
**Authorization:** `APPROVED PHASE 2`  
**SSOT:** `.cursor/architecture/database-blueprint.md` (87 objects; admission = 3 tables)  
**Decision lock:** ADR-020 (D1–D6)

---

## STEP 1 — Verification summary

| Finding | Detail |
|---------|--------|
| Schema name | `admission` — approved in Phase 1 catalog / blueprint |
| SchemaHelper | Exists at `App\Database\SchemaHelper` — **missing** `admission` |
| Blueprint tables | `application_periods`, `applications`, `application_documents` only |
| Interviews / waitlists / status_history | **Not in blueprint** — defer (would change 87 SSOT) |
| Programs | No `programs` table — use `vocational.specializations` + `academic.grade_levels` |
| Student identity | `students.students` — do **not** duplicate |
| Users FK | `public.users` (not `security.users`) — match enrollments |
| Status convention | `SMALLINT` + PHP enums/constants (StudentStatus, EnrollmentStatus) |
| RLS baseline | Fail-closed on enrollments + attendance.records via `app.current_school_id` |
| Existing data | Admission schema/tables absent — no compatibility rows |

---

## A. Tables (implement)

### 1. `admission.application_periods`

| Item | Value |
|------|-------|
| Purpose | Open/close window for applications per school + academic year |
| PK | BIGINT IDENTITY |
| FKs | `academic_year_id` → academic.academic_years; `school_id` → organization.schools |
| Lifecycle | `status` SMALLINT (1=open/active, 0=closed/inactive, 2=archived) |
| Retention | Keep historical periods — no hard-delete; RESTRICT FKs |
| School scope | `school_id` NOT NULL |
| Growth | Low (few rows per school per year) |

Columns per blueprint: id, academic_year_id, school_id, name, start_date, end_date, max_applications, status, created_at.

### 2. `admission.applications`

| Item | Value |
|------|-------|
| Purpose | Applicant submission + decision lifecycle (**not** student master) |
| PK | BIGINT IDENTITY |
| FKs | period, grade_level, specialization (nullable), reviewed_by → users (nullable), **student_id** nullable → students.students (conversion link only) |
| Applicant data | first_name, last_name, national_id (nullable, **not** globally unique), birth_date, gender |
| Lifecycle | status SMALLINT — see status map |
| Retention | Never hard-delete after submit; status transitions |
| School scope | Via `application_periods.school_id` (RLS subquery) |
| Growth | Medium (thousands/school/year typical) |

**Identity rule:** Applicant fields live on the application until conversion creates/links `students.students`. No `admission.students`.

**student_id:** Additive vs blueprint — nullable conversion pointer only; does not create identity. Blueprint updated in same change (object count remains 87).

### 3. `admission.application_documents`

| Item | Value |
|------|-------|
| Purpose | Metadata for uploaded application files (object storage) |
| PK | BIGINT IDENTITY |
| FKs | application_id → applications RESTRICT |
| Retention | Keep with application history |
| School scope | Via application → period |
| Growth | Medium (N docs per application) |

---

## Deferred tables (explicit)

| Concept | Why deferred |
|---------|--------------|
| interviews | Not in blueprint 87 SSOT |
| waitlists | Representable as `applications.status = Waitlisted` for Phase 2 |
| admission_status_history | Not in blueprint; use `security_audit_logs` / future `audit.audit_logs` + outbox for significant transitions in app layer later |
| Conversion handlers / Enrollment auto-create | Application feature work; DB only provides optional `student_id` |

---

## Status map (`applications.status`)

| Value | Name |
|------:|------|
| 1 | Draft |
| 2 | Submitted |
| 3 | UnderReview |
| 4 | Interview |
| 5 | Waitlisted |
| 6 | Accepted |
| 7 | Rejected |
| 8 | Withdrawn |
| 9 | Converted |

`application_periods.status`: 0=Inactive, 1=Active, 2=Archived (aligned with common SMALLINT patterns).

---

## B. Constraints

| Constraint | Table | Justification |
|------------|-------|---------------|
| PK | all | IDENTITY |
| FK RESTRICT | all parent refs | Preserve history |
| FK SET NULL | reviewed_by → users | Match enrollments `enrolled_by` |
| FK RESTRICT | student_id → students | Conversion link; no CASCADE |
| UNIQUE application_number | applications | Blueprint business reference |
| UNIQUE (none) on national_id | — | Multi-school / multi-year applications allowed |
| CHECK end_date >= start_date | application_periods | Date invariant |
| CHECK max_applications IS NULL OR > 0 | application_periods | Capacity invariant |
| CHECK status IN (0,1,2) | application_periods | Period lifecycle |
| CHECK status IN (1..9) | applications | Application lifecycle |
| CHECK gender IN (1,2) | applications | Match typical student gender SMALLINT (1/2) — verify students seed |
| CHECK document_type > 0 | application_documents | Non-zero type |

---

## C. Indexes

| Index | Columns | Query | Reason |
|-------|---------|-------|--------|
| PK | id | — | Default |
| btree (academic_year_id, school_id) | periods | List periods by school/year | Blueprint |
| UNIQUE application_number | applications | Lookup by number | Blueprint |
| btree (application_period_id) | applications | List by period | Blueprint |
| btree (status) | applications | Filter pipeline | Blueprint |
| btree (student_id) WHERE student_id IS NOT NULL | applications | Conversion lookup | Partial — justified |
| btree (application_id) | documents | List docs | Blueprint |

No GIN/GiST/BRIN/partitioning.

---

## D. RLS

| Table | RLS? | Design |
|-------|------|--------|
| application_periods | **YES** | Fail-closed: `school_id = app.current_school_id` |
| applications | **YES** | Fail-closed via EXISTS period.school_id match |
| application_documents | **YES** | Fail-closed via EXISTS application→period.school_id |

Matches enrollment/attendance fail-closed pattern. FORCE RLS: **OFF** (same as current baseline).  
Tests: extend PostgreSQL RLS feature tests.

---

## E. Relationships

```text
organization.schools ──┐
academic.academic_years ─┼─► application_periods ─► applications ─► application_documents
academic.grade_levels ──────► applications
vocational.specializations ─► applications (nullable)
public.users ───────────────► applications.reviewed_by (nullable)
students.students ──────────► applications.student_id (nullable, post-conversion)
```

No FK to enrollments in Phase 2 (conversion creates enrollment via existing Enrollment handlers later).

---

## F. Migrations

```text
2026_09_10_131000_phase2_create_admission_schema.php
2026_09_10_131100_phase2_create_admission_tables.php
2026_09_10_131200_phase2_enable_admission_rls.php
```

Also update `SchemaHelper::schemas()` to include `admission` (existing helper — justified).

---

## G. Risks

| Risk | Mitigation |
|------|------------|
| Fresh install double-create schema | IF NOT EXISTS |
| RLS breaks seeders without school context | Same as enrollments — set GUC in middleware; tests set config |
| Gender CHECK vs future values | Restrict to 1,2; document |
| Scope creep to interviews | Explicit defer |
| student_id misused as second identity | Nullable; conversion-only; documented |

---

## H. Rollback

`down()` drops policies → drops tables → drops schema (admission only). Safe on empty/new domain. No destructive ops on other schemas.

---

## Checkpoint

- Aligns with blueprint (3 tables); additive `student_id` only.  
- No ADR-020 conflict.  
- No rewrite of applied migrations.  
- Proceed to implementation.
