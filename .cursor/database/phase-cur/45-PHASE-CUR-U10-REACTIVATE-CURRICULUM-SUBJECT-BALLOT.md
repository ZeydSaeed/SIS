# Phase CUR-U10 — reactivate curriculum-subject link — BALLOT

**Unit:** CUR-U10  
**Parent:** CUR-U03  
**Authority:** «استمر» 10-step batch · step 5/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: link status Inactive→Active |
| C | Reject already active / not found |
| D | HTTP: `POST …/curriculum/curriculum-subjects/{link}/reactivate` |
| E | Auth: manageCurriculum · Idempotency |

## Out of scope

Prerequisite reactivate (CUR-U11); payroll.
