# Phase PT-U04 — reactivate promotion rule — BALLOT

**Unit:** PT-U04  
**Parent:** PT-U03  
**Authority:** «استمر» 10-step batch · step 10/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: `is_active` false→true |
| C | Reject already active |
| D | HTTP: `POST …/promotion/rules/{id}/reactivate` |
| E | Auth: managePromotion · Idempotency |

## Out of scope

Auto-enrollment; ranking/PDF.
