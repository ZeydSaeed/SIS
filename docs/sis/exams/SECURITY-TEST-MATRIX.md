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
