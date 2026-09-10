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
