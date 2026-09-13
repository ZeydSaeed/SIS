# Phase TV-U16 — reactivate workshop — BALLOT

**Unit:** TV-U16  
**Parent:** TV-U15  
**Authority:** «استمر» 10-step batch · step 1/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: status Inactive→Active |
| C | Reject already active |
| D | HTTP: `POST …/vocational/workshops/{id}/reactivate` |
| E | Auth: manage Workshop · Idempotency |
| F | section_batches / assignment enforce HOLD |

## Out of scope

Equipment reactivate (TV-U17); section_batches; payroll.
