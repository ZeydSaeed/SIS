# Phase COM-U08 — notification job complete/cancel — BALLOT

**Unit:** COM-U08  
**Parent:** COM-U07  
**Authority:** «استمر» step 3/3  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Complete: Open→Completed + sent_count ≤ total_count |
| C | Cancel: Open→Cancelled |
| D | HTTP: `POST …/jobs/{id}/complete` · `POST …/jobs/{id}/cancel` |
| E | Auth: communication.manage · Idempotency |
| F | Bulk fan-out / SMTP still HOLD |

## Out of scope

SMTP; message generation; payroll.
