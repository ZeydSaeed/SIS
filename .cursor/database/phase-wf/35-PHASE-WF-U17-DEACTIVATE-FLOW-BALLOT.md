# Phase WF-U17 — deactivate approval_flow — BALLOT

**Unit:** WF-U17  
**Parent:** WF-U01  
**Authority:** «استمر» 10-step batch · step 4/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft deactivate: `is_active` true→false |
| C | Reject already inactive |
| D | HTTP: `POST …/workflow/approval-flows/{id}/deactivate` |
| E | Auth: manageWorkflow · Idempotency |
| F | Runtime hooks unchanged HOLD extras |

## Out of scope

Reactivate (WF-U18); CompleteTransfer hooks; payroll.
