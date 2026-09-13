# Phase PT-U03 — deactivate promotion rule — BALLOT

**Unit:** PT-U03  
**Parent:** PT-U01  
**Authority:** «استمر» 10-step batch · step 9/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft deactivate: `is_active` true→false |
| C | Reject already inactive |
| D | HTTP: `POST …/promotion/rules/{id}/deactivate` |
| E | Auth: managePromotion · Idempotency |

## Out of scope

Auto-enrollment; ranking/PDF.
