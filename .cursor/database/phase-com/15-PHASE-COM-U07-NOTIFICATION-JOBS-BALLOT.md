# Phase COM-U07 — notification_jobs physicalize — BALLOT

**Unit:** COM-U07  
**Parent:** COM HOLD notification_jobs  
**Authority:** «استمر» step 2/3  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Create `communication.notification_jobs` + `school_id` |
| B | FORCE RLS; reject hard DELETE |
| C | Status v1: 1=Open, 2=Completed, 3=Cancelled |
| D | Create opens status=1; bulk fan-out / SMTP HOLD |
| E | HTTP: `POST/GET /api/v1/communication/jobs` |
| F | Auth: communication.manage / view |
| G | Partition HOLD |

## Out of scope

SMTP/SMS; message fan-out; payroll.
