# Exams / Student Grades — Security Contract

**Version:** 1.0  
**Date:** 2026-09-10  
**Phase:** 3B.1 — Grade Application Hardening  
**Scope:** Application/API security for `exams.student_grades` (not Phase 3C results/GPA/transcripts)

This contract is grade-specific. It complements Phase 3B database RLS/FORCE RLS and does not reopen Phase 3B DDL.

---

## SC-G01 — Authentication

Every grade HTTP operation requires `auth:sanctum` (API middleware group).

| Operation | Route | Auth |
|-----------|-------|------|
| Enter | `POST /api/v1/grades` | Required |
| Show | `GET /api/v1/grades/{id}` | Required |
| Correct | `POST /api/v1/grades/{id}/correct` | Required |
| Void | `POST /api/v1/grades/{id}/void` | Required |
| Finalize | `POST /api/v1/grades/{id}/finalize` | Required |
| List by session/enrollment/current | GET list routes | Required |

**Evidence:** unauthenticated → 401.

---

## SC-G02 — Authorization (permissions)

| Permission | Operations |
|------------|------------|
| `grades.view` | show, list |
| `grades.create` | enter |
| `grades.correct` | correct (including finalized → VOID+INSERT) |
| `grades.void` | void without replacement |
| `grades.finalize` | finalize entered/draft |

Roles (config): `grades_manager`, `grades_teacher`, `grades_viewer`.

**Policy:** `GradePolicy` via Gate on `StudentGradeRecord`.

**Evidence:** missing permission → 403.

---

## SC-G03 — School isolation (defense in depth)

```text
Policy (GradePolicy + GradeSchoolAccessService)
  → SchoolContext (X-School-Id; never trust body school_id)
  → Handler schoolId on repository queries
  → Composite FKs (school_id + related ids)
  → PostgreSQL RLS + FORCE RLS on exams.student_grades
```

Client `school_id` is **prohibited** (`SecuritySensitiveFieldGuard`).

**Evidence:** cross-school enter/show/correct → 403/404; PostgreSQL RLS tests remain green.

---

## SC-G04 — Server-derived fields (mass assignment)

Clients MUST NOT control:

```text
school_id, academic_year_id (on enter), student_id, enrollment_id,
subject_id, exam_session_id, max_score, entered_by, is_current,
finalized_at, correction_of_grade_id, status
```

`max_score` is snapshotted from `exam_sessions.max_grade` at enter/correct time.

`StudentGradeRecord::$fillable` is empty.

**Evidence:** prohibited fields → 422.

---

## SC-G05 — No hard delete

- No `DELETE /api/v1/grades/{id}`
- No `DeleteStudentGrade` command
- Repository has no ordinary delete method
- DB BEFORE DELETE trigger remains authoritative

**Evidence:** route missing; API delete → 405/404.

---

## SC-G06 — Correction model

Corrections use **VOID + INSERT** only. `correction_of_grade_id` set once on insert; no API to mutate pointer. Reason required for correct/void (audit + outbox payload; no new DB column in 3B.1).

---

## SC-G07 — Finalization

Entered (or Draft) → Finalized. Score/max_score not edited in place after finalize; further change requires Correct or Void.

Submitted workflow is **out of scope**.

---

## SC-G08 — Idempotency

Write commands use shared `IdempotencyStore` + `X-Idempotency-Key`. Command names distinguish Enter / Correct / Void / Finalize. Unique current-grade constraint remains concurrency backstop.

---

## SC-G09 — Audit & outbox

- `SecurityAuditLoggerInterface` + `GradeDataAccess` / `GradeDataModified`
- Outbox events: `StudentGradeEntered|Corrected|Voided|Finalized` staged in same UnitOfWork transaction
- No Phase 3C consumers

---

## SC-G10 — Composite identity

Reads/mutations that target a grade require `academic_year_id` (query or body) with `(id, academic_year_id)` semantics.

---

## SC-G11 — Partition safety

Missing academic-year LIST partition → clear domain error (`grades.partition_missing`). No DEFAULT partition. No silent partition create on ordinary writes.

---

## SC-G12 — Test requirements

Unit (rules/status), handler (idempotency/authz preconditions), API (authn/authz/prohibited fields/no DELETE), PostgreSQL (RLS/FORCE/unique current/hard-delete), concurrency (duplicate enter / concurrent correct).
