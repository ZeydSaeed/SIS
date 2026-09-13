# Phase FIN-U13 — cancel student_fee — BALLOT

**Unit:** FIN-U13  
**Parent:** FIN obligations  
**Authority:** «استمر» 10-step batch · step 8/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft cancel: status Unpaid→Cancelled only |
| C | Reject Partial/Paid/already Cancelled |
| D | HTTP: `POST …/finance/student-fees/{id}/cancel` |
| E | Auth: manageFinance · Idempotency |
| F | Partial refund / gateway HOLD |

## Out of scope

Refunds; reopen cancelled; payroll.
