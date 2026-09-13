# Phase ENR-U03 — reactivate enrollment-subject link — BALLOT

**Unit:** ENR-U03  
**Parent:** CUR-U05  
**Authority:** «استمر» 10-step batch · step 7/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: status Inactive→Active |
| C | Reject already active / not found |
| D | HTTP: `POST …/enrollment-subjects/{link}/reactivate` |
| E | Auth: update enrollment · Idempotency |

## Out of scope

Payroll; FORCE RLS extras.
