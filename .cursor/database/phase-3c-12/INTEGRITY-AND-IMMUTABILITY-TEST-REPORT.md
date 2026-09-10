# INTEGRITY AND IMMUTABILITY TEST REPORT

## Database-enforced (LIVE)

| Control | Mechanism | Status |
|---------|-----------|--------|
| Cross-school enrollment | Composite FK `(enrollment_id, school_id)` | IMPLEMENTED |
| Year denorm | Composite FK `(enrollment_id, academic_year_id)` | IMPLEMENTED |
| Student/spec denorm | BEFORE INSERT/UPDATE triggers | IMPLEMENTED |
| Child school_id | Parent-match triggers | IMPLEMENTED |
| Hard delete official history | `reject_hard_delete` triggers | IMPLEMENTED |
| Official completion payload | UPDATE guard lifecycle=2 | IMPLEMENTED |
| Issued award payload | UPDATE guard lifecycle=1 | IMPLEMENTED |
| One current official | Partial UNIQUE | IMPLEMENTED |
| One current issued | Partial UNIQUE WHERE lifecycle=1 | IMPLEMENTED |

## Runtime automated tests

| Suite | Result |
|-------|--------|
| Feature Phase3C12 on sis_test | ENVIRONMENT-BLOCKED (RefreshDatabase) |
| LIVE catalog + migrate verify | PASS |

No production business graduation rows inserted.
