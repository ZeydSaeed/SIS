# Phase COM-U10 — reactivate notification template — BALLOT

**Unit:** COM-U10  
**Parent:** COM-U09  
**Authority:** «استمر» 5-step batch · step 5/5  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: `is_active` false → true |
| C | Reject already active |
| D | HTTP: `POST …/communication/templates/{id}/reactivate` |
| E | Auth: `manageCommunication` · Idempotency |
| F | SMTP / bulk fan-out still HOLD |

## Out of scope

SMTP; message send; payroll.
