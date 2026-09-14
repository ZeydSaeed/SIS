# ENR-CLS-U03 DeactivateClass — Schema NONE

**Unit:** ENR-CLS-U03  
**Date:** 2026-09-14

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft deactivate: status Active(1)→Inactive(2) |
| C | HTTP: `POST /api/v1/enrollment/classes/{class}/deactivate` |
| D | Auth: `enrollment.update` · Idempotency required |
| E | Domain event via Outbox |

## Out of scope

Hard DELETE; cascade sections.
