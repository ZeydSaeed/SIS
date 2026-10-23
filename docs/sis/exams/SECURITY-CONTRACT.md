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

**Enforced in the database (EXAMIS U5, 2026-10-22):** trigger `student_grades_reject_in_place_update` (`BEFORE UPDATE`,
`ENABLE ALWAYS`, on every year partition) rejects any in-place change with SQLSTATE `23514`. Allowed UPDATEs: finalize
(`1|2|3 → 4`, current, `finalized_at` set once) and void (`→ 5`, `is_current` true → false, `finalized_at` unchanged).
Score, max score, absence, every identity column, the correction link, the entry actor and timestamps never change; a
voided grade is terminal and never current again; other status changes (e.g. `1→2`, `2→3`) are rejected until a reviewed
submission workflow exists. It applies to superuser, replica-role and RLS-limited connections alike.

**Residual risk (accepted):** a trigger does not protect against `TRUNCATE` (used only by the guarded disposable-database
reset commands) or a deliberate privileged `ALTER TABLE … DISABLE TRIGGER` / `DROP TRIGGER`; those stay controlled by
database privileges and operational access. A rejected UPDATE aborts its transaction, so it is not written to
`security_audit_logs` (the PostgreSQL log keeps the error).

---

## SC-G07 — Finalization

Entered (or Draft) → Finalized. Score/max_score not edited in place after finalize; further change requires Correct or Void.

Submitted workflow is **out of scope**.

---

## SC-G08 — Idempotency

Write commands use shared `IdempotencyStore` + `X-Idempotency-Key`. Command names distinguish Enter / Correct / Void / Finalize. Unique current-grade constraint remains concurrency backstop.

**Payload fingerprint (EXAMIS U9, 2026-10-23):** each grade command stores a fingerprint of its canonical request (seat or
grade, year, school, score normalized to 2 decimals, absence, reason, actor) with its result. A replay with the same key
and the same request returns the first result; with a different request it is refused — HTTP 409 /
`grades.idempotency_payload_conflict` (a per-row outcome on the grade-entry sheet) — instead of silently ignoring the
new values. Keys stored before this change carry no fingerprint and keep replaying until they expire (24h). Not covered
(documented residual): two simultaneous first requests with the same key — the unique current-grade index still allows
only one grade; the second reports `grades.conflict` rather than a replay.

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

---

## SC-G15 — Seating a class into an exam session (EXAMIS U6, 2026-10-22)

`POST /exams/{exam}/sessions/{session}/seat` (web, `exam.enrollment.create` via `ExamPolicy::createEnrollment`; the handler
re-checks it through `ExamAdministrationAuthorityPort`). Body: `class_id`, optional `section_id`; enrollment ids are
prohibited — the server chooses the students.

- **Whole-request refusals (nothing seated):** session not in this exam and school → 404; cancelled session, class not of
  the exam's school-year, section not of the class, missing `X-Idempotency-Key` → coded error flash (`exams.seating.*`).
- **Who is seated:** active enrollments (`status = 1`, `effective_to IS NULL`) of the class (or section) in the exam's
  school-year **that have the session's subject actively assigned** (`enrollment.enrollment_subjects`). Others are
  reported (`subject_not_assigned`), not seated; students already holding a seat in the session are reported
  (`already_seated`) and never seated twice.
- **Write path:** one `CreateExamEnrollment` per student (its own rules, transaction, fingerprinted idempotency and
  events), with a per-student key derived from the request key. A repeated request seats nobody twice.
- **Audit:** one `SEC_GRADE_DATA_MODIFIED` record `exams.web.seat_class` with the per-outcome counts.

---

## SC-G16 — Exam administration from the web (EXAMIS U8, 2026-10-22)

`POST /exams` · `POST /exams/{exam}/sessions` · `POST /exams/{exam}/sessions/{session}/open|close` (web) reuse the API's
form requests and commands unchanged — permissions `exam.create`, `exam.session.create`, `exam.session.open`,
`exam.session.close`, required `X-Idempotency-Key`, prohibited `school_id` / `status` / `exam_id`, the commands'
authority, idempotency fingerprint and lifecycle guards. Open / close also require the session to belong to the exam in
the URL and to the school in context (else 404). Audit: `exams.web.store`, `exams.web.sessions.store|open|close`.

The forms offer existing records only: terms of the year, existing exam types (with their weight), subjects of the
school-year's active curricula. **No exam type exists until one is defined:** exam types carry `weight_percentage`, which
feeds official term results, so defining them is an academic-policy decision outside this unit; until then the
create-exam form explains that and offers nothing.

## SC-G20 — Term-results sheet for a class (EXAMIS U12, 2026-10-23)

`GET /results/sheet` (`viewOfficial`) · `POST /results/sheet/calculate` (`results.calculate`) ·
`POST /results/sheet/finalize` (`results.finalize`). The target is term + subject + class (+ optional section) of the
year; the server validates term ∈ year, class ∈ school-year, section ∈ class (`results.batch.*_invalid`) and derives the
students itself: active enrollments of the class/section seated in a non-cancelled session of that term/subject.
`enrollment_ids` / `enrollment_id` in the body are prohibited. Required `X-Idempotency-Key`.

Each student runs through the existing single-student `CalculateTermResultCommand` / `FinalizeTermResultCommand` in its
own transaction with a derived key `sha256(mode|key|enrollment)`, so a failed student never hides another and repeating
the same request replays. **Finalize never re-finalizes:** a student with a current official result is reported
`already_official` and untouched; re-finalizing stays on the separately authorised rebuild path. Per-student outcomes
use the domain error codes (`results.term_no_eligible_grades`, `results.official_dataset_incomplete`,
`results.term_weight_sum_invalid`). Audit: `results.web.term_batch.calculate|finalize`.

## SC-G21 — Annual result and year GPA sheet for a class (EXAMIS U13, 2026-10-23)

`GET /results/year-sheet` (`viewOfficial`) · `POST /results/year-sheet/calculate|finalize` (`results.calculate` /
`results.finalize`) with `target` ∈ {`annual`, `gpa`}. The target is a class (+ optional section) of the school-year
(`results.batch.class_invalid` / `section_invalid`); the server derives the students (active enrollments of the class /
section); `enrollment_ids` / `enrollment_id` prohibited; required `X-Idempotency-Key`.

Each student runs through the existing single-student Calculate / Finalize AnnualResult or Gpa command in its own
transaction with key `sha256(target|mode|key|enrollment)`. **Finalize never re-finalizes:** a student with a current
official annual result (resp. GPA) is reported `already_official` and untouched; rebuild stays the separate path. The
order is the existing rule set, nothing new: annual uses the student's current term results (official ones for
finalize), GPA requires the official annual result (`results.gpa_official_annual_missing`). Audit:
`results.web.year_batch.{annual|gpa}.{calculate|finalize}`.

## SC-G22 — Class ranking and transcripts from the annual / GPA sheet (EXAMIS U14, 2026-10-23)

`POST /results/year-sheet/ranking` (`results.ranking.build`, the API's `BuildRankingSnapshotRequest`, required
`X-Idempotency-Key`): the class is first checked to be of the school-year (`results.batch.class_invalid`), then the
existing `BuildRankingSnapshot` command ranks the whole class by official year GPA (`results.ranking_no_participants`
when nobody has one). `POST /results/year-sheet/transcripts` (`results.transcript.issue`): class (+ section) of the
school-year, `enrollment_ids` prohibited, required key; each student runs through the existing `IssueTranscript` command
in its own transaction (`results.transcript_official_gpa_missing` without an official GPA). **No silent re-issue:** a
student whose current transcript was issued at or after the current official GPA's finalization is `already_issued`.
Audit: `results.web.year_sheet.ranking`, `results.web.year_batch.transcripts`.

## UI — exams / grades / results pages on the system chrome (2026-10-23)

Every exams, grades and results page uses the shared list-page shell (`sisListPageClass`), the standard table
(`AttendanceTable`: selection, sort, paging, title-bar search), windows for forms (`AttendanceWindow`) and the message
dialog for guidance. Commands are on the «تحرير» ribbon tab with the module's sections (`useExamisEditRibbon`, sections
from the server-computed `examisShell` shared prop, each page still authorizes on the server); filters are on
«الصفحة الرئيسية». Ribbon commands mirror the old buttons one-to-one (same routes, requests and permissions); roster seat
actions apply to each selected eligible seat with one request per seat.
