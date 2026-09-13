# Phase CUR-U11 — reactivate subject prerequisite — BALLOT

**Unit:** CUR-U11  
**Parent:** CUR-U01  
**Authority:** «استمر» 10-step batch · step 6/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: prerequisite status Inactive→Active |
| C | Reject already active / not found |
| D | HTTP: `POST …/curriculum/prerequisites/{id}/reactivate` |
| E | Auth: manageCurriculum · Idempotency |

## Out of scope

Payroll; enrollment subject reactivate (ENR-U03).
