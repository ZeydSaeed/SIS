# ENR-SEC-U03 DeactivateSection — Schema NONE

**Unit:** ENR-SEC-U03  
**Date:** 2026-09-14

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft deactivate: status Active→Inactive |
| C | HTTP: `POST /api/v1/enrollment/sections/{section}/deactivate` |
| D | Auth: `enrollment.update` · Idempotency |

## Out of scope

Hard DELETE; migrations.
