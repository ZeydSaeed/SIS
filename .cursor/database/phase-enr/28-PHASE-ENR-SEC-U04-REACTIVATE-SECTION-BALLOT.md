# ENR-SEC-U04 ReactivateSection — Schema NONE

**Unit:** ENR-SEC-U04  
**Date:** 2026-09-14

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: status Inactive→Active |
| C | HTTP: `POST /api/v1/enrollment/sections/{section}/reactivate` |
| D | Auth: `enrollment.update` · Idempotency |

## Out of scope

Migrations.
