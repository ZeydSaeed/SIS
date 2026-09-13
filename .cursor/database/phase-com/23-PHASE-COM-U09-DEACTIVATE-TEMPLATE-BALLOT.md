# Phase COM-U09 — deactivate notification template — BALLOT

**Unit:** COM-U09  
**Parent:** COM-U01  
**Authority:** «استمر» 5-step batch · step 4/5  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE (`is_active` already exists) |
| B | Soft deactivate: `is_active` true → false |
| C | Reject already inactive |
| D | HTTP: `POST …/communication/templates/{id}/deactivate` |
| E | Auth: `manageCommunication` · Idempotency |
| F | SMTP / bulk fan-out still HOLD |

## Out of scope

Reactivate (COM-U10); message send; payroll.
