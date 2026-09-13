# Phase TV-U17 — reactivate workshop equipment — BALLOT

**Unit:** TV-U17  
**Parent:** TV-U14  
**Authority:** «استمر» 10-step batch · step 2/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: status Inactive→Active |
| C | Reject already active |
| D | HTTP: `POST …/vocational/workshop-equipment/{id}/reactivate` |
| E | Auth: manage Workshop · Idempotency |
| F | section_batches HOLD |

## Out of scope

section_batches; assignment enforce; payroll.
