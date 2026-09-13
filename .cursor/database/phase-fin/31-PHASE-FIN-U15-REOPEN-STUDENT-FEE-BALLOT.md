# Phase FIN-U15 — reopen student_fee — BALLOT

**Unit:** FIN-U15  
**Parent:** FIN-U13  
**Authority:** «استمر» 10-step batch · step 9/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reopen: status Cancelled→Unpaid only |
| C | Reject Unpaid (already_open) / Partial / Paid (not_reopenable) |
| D | HTTP: `POST …/finance/student-fees/{id}/reopen` |
| E | Auth: manageFinance · Idempotency |
| F | Refunds / gateway HOLD |

## Out of scope

Partial refund; payroll.
