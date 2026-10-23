# Exams / Student Grades — Security Test Matrix

**Phase:** 3B.1  
**Contract:** `docs/sis/exams/SECURITY-CONTRACT.md`

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G01 | Authn | API without token | 401 |
| SC-G02 | Authz view | Viewer vs no grades perm | 200 / 403 |
| SC-G02 | Authz create | Teacher create; viewer create | 201 / 403 |
| SC-G02 | Authz correct/void/finalize | Manager yes; teacher no | 200 / 403 |
| SC-G03 | School isolation | Manager school A → school B seat | 403/404/422 |
| SC-G04 | Prohibited fields | `max_score`, `school_id`, `entered_by` | 422 |
| SC-G05 | No DELETE | `DELETE /api/v1/grades/{id}` | 405/404 |
| SC-G06 | Correction | VOID+INSERT; prior not current | new id; prior voided |
| SC-G07 | Finalize | Entered → Finalized | status 4 |
| SC-G08 | Idempotency | Same key twice | 201 then 200 replay |
| SC-G09 | Audit/outbox | Enter stores outbox + audit | rows present |
| SC-G11 | Partition | PG missing partition | partition_missing |

Executable suites: `tests/Feature/Exams/*`, `tests/Feature/Security/*Grade*`, `tests/Unit/Domain/Exams/*`, existing Phase 3B PostgreSQL suites.

## EXAMIS U2–U3 (2026-10-22)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G13 | Teacher scope | Covering assignment: section / class / department / branch-wide | 201 |
| SC-G13 | Teacher scope | Other subject / section / class / department / branch / year; ended or future assignment; none | 403, no grade, `SEC_IDOR_BLOCKED` |
| SC-G13 | Teacher link | No linked teacher record; teacher left the school | 403 |
| SC-G13 | Boundary | Seat without branch | 403 for teachers |
| SC-G13 | School-wide | `grades.enter.any` holder without teacher record | 201; held by `grades_manager` only |
| SC-G13 | Least privilege | Role with only view + create + enter.any → correct / void / finalize | 403 |
| SC-G14 | Roster | Teacher sees covered sessions/seats only; manager sees all active seats | Inertia props |
| SC-G14 | Roster | Uncovered session (teacher) / unknown or other-school session | 403 + audit / 404 |
| SC-G14 | Save | Modified ids: other section, other session, other school, withdrawn seat | per-row denial, no grade |
| SC-G14 | Save | Replay same key / duplicate row / existing grade | `replayed` / `duplicate_row` / `grades.conflict`, one grade |
| SC-G14 | Save | Score > max / negative / 3 decimals / prohibited field / empty rows / no key | row error / 422 / error flash, no grade |
| SC-G14 | Save | Session not open | `grades.session_unavailable` |
| SC-G14 | Access | `grades_viewer` | 403 (GET and POST) |

Executable: `tests/Unit/Domain/Exams/GradeEntryCoverageTest.php`,
`tests/Feature/Database/PostgreSql/GradeEntryTeacherScopePostgreSqlTest.php`,
`tests/Feature/Database/PostgreSql/GradeEntrySheetPostgreSqlTest.php`.

## EXAMIS U5 — grades immutable in place (2026-10-22)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G06 | Allowed UPDATEs | finalize `1/2/3 → 4`; void `2 → 5`, `4 → 5`; `updated_at`-only touch | 1 row updated |
| SC-G06 | Immutable columns | each identity column, `score` (incl. → NULL), `max_score`, `is_absent`, `correction_of_grade_id` (→ NULL / other), `entered_by`, `entered_at`, `created_at`, year move | SQLSTATE 23514, row unchanged |
| SC-G06 | Transitions | `2→1`, `2→3`, `1→2`, `4→1/2`, leave current without void, `finalized_at` set/changed/cleared outside finalize, finalize non-current, any update of a voided grade | 23514 |
| SC-G06 | Application flows | enter → finalize → correct (VOID + INSERT) → finalize → void through the handlers; outbox events staged | chain intact |
| SC-G06 | Partitions | trigger cloned on every partition in `ALWAYS` mode, incl. a year created after the migration; rejection in two years + the new one | `tgenabled = A`, 23514 |
| SC-G06 | Connections | superuser; superuser with `session_replication_role = replica`; non-privileged `sis_rls_tester` under RLS | 23514 for all |

Executable: `tests/Feature/Database/PostgreSql/StudentGradesImmutabilityPostgreSqlTest.php`.

## EXAMIS U6 — seating a class (2026-10-22)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G15 | Who is seated | class with students with / without the subject, already seated, inactive, other class | only active subject-takers seated; others reported |
| SC-G15 | Section | one section only | other section untouched |
| SC-G15 | Idempotency | same key twice; new key | no second seat |
| SC-G15 | Targets | section of another class, class of another year, other exam's / school's session, cancelled session, no key, `enrollment_ids` in body | refused, nothing seated (404 / coded error / 422) |
| SC-G15 | Access | `grades_teacher`, `grades_viewer` | 403, nothing seated |
| SC-G15 | Page | manager gets classes + seat counts; teacher gets none; seated students appear on the grade sheet | Inertia props |
| SC-G15 | Browser | manager seats section B via the form; repeat seats 0; viewer has no action, POST 403 | `e2e/exams/seating.spec.ts` |

Executable: `tests/Feature/Database/PostgreSql/ExamSeatingPostgreSqlTest.php`, `e2e/exams/seating.spec.ts`.

## EXAMIS U8 — exam administration from the web (2026-10-22)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G16 | Create exam | manager creates; same key again | one exam, redirect to it |
| SC-G16 | Refusals | end before start; no key; `school_id` in body | error / 422, no exam |
| SC-G16 | Session lifecycle | create → open → close; reopen a completed session | 1 → 2 → 3; refused |
| SC-G16 | Targets | session of another exam / school | 404, status unchanged |
| SC-G16 | Access | `grades_teacher`, `grades_viewer` | 403 on every action |
| SC-G16 | Page | forms + lookups only for users with the permission | Inertia props |
| SC-G16 | Browser | create exam → add session → start → close (confirm); viewer has no controls | `e2e/exams/administration.spec.ts` |

Executable: `tests/Feature/Database/PostgreSql/ExamAdministrationWebPostgreSqlTest.php`, `e2e/exams/administration.spec.ts`.

## EXAMIS U9 — session candidate roster «كشف المرشحين» (2026-10-23)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G17 | Roster page | every seat of the session with student, seat number, status, grade flag; action flags per permission | Inertia props |
| SC-G17 | Lifecycle | confirm → present (only once the session is in progress); seat number frozen once present | 1 → 2 → 3; refusals flash `exams.roster.action_refused`, nothing changes |
| SC-G17 | Lifecycle | seat number → withdraw → reopen → absent; absent is final (withdraw / reopen refused) | as stated |
| SC-G17 | Refusals | `status=5`, `school_id` in body, no idempotency key | validation errors, unchanged |
| SC-G17 | Targets | seat of another session; session under another exam; another school's seat / page | 404, unchanged |
| SC-G17 | Access | `grades_teacher`, `grades_viewer` | 403 on every action |
| SC-G17 | Browser (manual run, throwaway DB) | link from the exam page; present → «سُجّل حضور الطالب.»; absent through the confirm dialog; 390 px without horizontal scroll | verified 2026-10-23 — no committed e2e spec yet |

Executable: `tests/Feature/Database/PostgreSql/ExamRosterWebPostgreSqlTest.php`.

## EXAMIS U10 — exam types per school «أنواع الامتحانات» (2026-10-23)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G18 | Ownership | migration: `school_id` NOT NULL, UNIQUE (school_id, code), composite FK exams → exam_types (id, school_id), FORCE RLS | another school's type unusable in app (create exam refused) and in DB (FK error) |
| SC-G18 | Create / replay | manager adds a type; same key again | one row, flash `flash.exams.typeCreated` |
| SC-G18 | Refusals | weight 150, bad code, `school_id` in body, no key, duplicate code in the school | validation / `exams.types.code_taken`; same code in another school allowed |
| SC-G18 | Frozen once used | a type used by an exam: change weight or code | `exams.types.locked_in_use`, unchanged; name change allowed |
| SC-G18 | Isolation | page + exam form list only own types; update another school's type | 404; RLS shows only own rows, none without context |
| SC-G18 | Access | `grades_teacher`, `grades_viewer` | page read-only (`can_manage` false), POST 403 |

Executable: `tests/Feature/Database/PostgreSql/ExamTypesWebPostgreSqlTest.php` (+ fixtures updated to per-school types).

## EXAMIS U11 — grades / results pages choose instead of raw ids (2026-10-23)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G19 | Grades list | session dropdown = the school's sessions of the year; student names for listed rows | Inertia props |
| SC-G19 | Results picker | search by code finds own-school students only; chosen student described | candidates / `picker.student` |
| SC-G19 | Isolation | another school's enrollment on /results, /results/show, /results/transcript | 404 |

Executable: `tests/Feature/Database/PostgreSql/GradesResultsPickersWebPostgreSqlTest.php`. Browser (manual, throwaway DB, 2026-10-23): add type → appears in exam form → exam created; grades dropdown; results search → choose; 390 px no horizontal scroll.

## EXAMIS U12 — term-results sheet (2026-10-23)

| ID | Rule | Test focus | Expected |
|----|------|------------|----------|
| SC-G20 | Calculate | finalized + entered + ungraded student | calculated, calculated, `results.term_no_eligible_grades`; no official row; audit |
| SC-G20 | Finalize | same scene; finalize twice | finalized / `results.official_dataset_incomplete` ×2; second run `already_official`, no new version |
| SC-G20 | Replay | same key twice | `replayed`, no new versions |
| SC-G20 | Weights | type weight 40 | per-student `results.term_weight_sum_invalid`, nothing written |
| SC-G20 | Scope | term of another year, class of another year, section of another class, `enrollment_ids` in body | coded error / validation error; nothing written |
| SC-G20 | Access | `results_viewer` page read-only, POST 403; `grades_teacher` GET/POST 403 | |
| SC-G20 | Page | rows = seated students with grade counts and operational/official result | Inertia props |

Executable: `tests/Feature/Database/PostgreSql/TermResultSheetWebPostgreSqlTest.php`.
