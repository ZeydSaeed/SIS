# Phase FIN-U12 — deactivate fee_type — BALLOT

**Unit:** FIN-U12  
**Parent:** FIN catalog  
**Authority:** «استمر» 10-step batch · step 3/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft deactivate: status Active→Inactive |
| C | Reject already inactive |
| D | HTTP: `POST …/finance/fee-types/{id}/deactivate` |
| E | Auth: manageFinance · Idempotency |
| F | Payments / payroll / gateway HOLD |

## Out of scope

Partial refund; gateway; payroll.
