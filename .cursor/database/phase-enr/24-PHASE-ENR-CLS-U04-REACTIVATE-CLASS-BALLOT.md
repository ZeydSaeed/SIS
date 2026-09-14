# ENR-CLS-U04 ReactivateClass — Schema NONE

**Unit:** ENR-CLS-U04  
**Date:** 2026-09-14

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: status Inactive(2)→Active(1) |
| C | HTTP: `POST /api/v1/enrollment/classes/{class}/reactivate` |
| D | Auth: `enrollment.update` · Idempotency |

## Out of scope

Migrations; create class.
