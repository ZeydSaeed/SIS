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

---

## SC-G13 — Teacher scope for grade entry (EXAMIS U2, 2026-10-22)

Grade **entry** needs `grades.create` plus one of:

| Path | Rule |
|------|------|
| `grades.enter.any` (role `grades_manager` only) | Any seat of the school in context. Grants entry only — correct / void / finalize keep their own permissions. |
| Teacher scope | The user is linked (`teachers.teachers.user_id`) to an **active** teacher who is a **current member** of the seat's school and year (`teacher_schools.left_at IS NULL`), with an **active** teaching assignment (`status = 1`, `effective_from ≤ today ≤ effective_to`, app clock) that covers the seat. |

Coverage (`Domain/Exams/Services/GradeEntryCoverage`): same school, academic year (the exam's) and subject (the session's); same
branch as the student's enrollment; department (الاختصاص), class and section must match **where the assignment names them** — a
null column covers every value inside the wider scope. No other permission implies school-wide entry.

Enforcement: `GradePolicy::enterSeat` (single entry — web form + API, after validation) and `EnterSessionGradesHandler` (sheet,
per row). Denials → 403 / per-row `grades.sheet.seat_forbidden`, audited as `SEC_IDOR_BLOCKED` (`grades.enter.scope`).

### Boundary cases — current behaviour (deny), kept until a reviewed decision

| Case | Behaviour | Academic assumption | Tests |
|------|-----------|---------------------|-------|
| Assignment ended (or not started) on the day of entry, although the teacher taught the class when the exam was held | **Denied.** Coverage is evaluated on the entry date, not the exam date. A grades manager (`grades.enter.any`) enters the grade. | Grades are often entered days after the exam; a teacher who has handed the class over should not keep write access to it. Allowing it would mean inferring historical authority from `effective_from/effective_to` vs `exam_sessions.session_date` — a policy decision, not a technical one. | `GradeEntryTeacherScopePostgreSqlTest`: `ended assignment`, `assignment ended before today`, `assignment not started yet` |
| Enrollment without a branch (`enrollment.enrollments.branch_id IS NULL`) | **Denied** to every teacher, even a branch-wide assignment. Only `grades.enter.any` can enter it. | Every assignment is bound to a branch, so a branchless seat cannot be matched without guessing (e.g. from the class). Such rows are a data-quality gap to fix in enrollment, not to bypass in grading. | `GradeEntryCoverageTest::seat_without_branch_is_never_covered`, `GradeEntryTeacherScopePostgreSqlTest::seat_without_branch_placement_is_denied_to_teachers` |

Relaxing either requires an explicit, reviewed decision (who decides, the exact window rule, audit wording).

---

## SC-G14 — Session grade-entry sheet (EXAMIS U3, 2026-10-22)

`GET /grades/sheet?academic_year_id&session_id` · `POST /grades/sheet/{session}` (web, `grades.create`). `/grades/enter` redirects
here; the raw `exam_enrollment_id` form is retired (the single-grade `POST /grades` and the API are unchanged).

- **Roster scoping is server-side** (`GetGradeEntrySheetHandler`): without `grades.enter.any` the session list holds only sessions
  with a covered seat and the roster only covered seats; opening an uncovered session → 403 + audit; unknown / other-school session → 404.
  Only active seats (registered / confirmed / present) are listed.
- **Save** (`EnterSessionGradesHandler`): every row is re-checked — seat must be an active seat of *this* session
  (`seat_not_in_session`), covered (`seat_forbidden`), not repeated in the request (`duplicate_row`) — then entered through
  `EnterStudentGrade` (the only official write path) in its own transaction. Per-row outcomes; one failed row never hides or
  undoes another (`flash.grades.sheetPartial`).
- **Idempotency:** `X-Idempotency-Key` is required; each row uses `sheet:sha256(key|seat)`, so a retried submission replays
  (`replayed`) instead of duplicating.
- **No overwrite:** a seat with a current grade is shown read-only; a new value is refused (`grades.conflict`). Changing a
  grade stays on the correction path (VOID + INSERT, `grades.correct`). The sheet adds no correct / void / finalize / publish capability.
- **Validation:** request — score numeric, ≥ 0, ≤ 2 decimals, max 500 rows, no client-controlled identity fields; domain — score ≤ the
  session's `max_grade`, session in progress / completed, enrollment active.
