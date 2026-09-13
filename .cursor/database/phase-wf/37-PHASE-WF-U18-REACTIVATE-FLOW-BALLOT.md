# Phase WF-U18 — reactivate approval_flow — BALLOT

**Unit:** WF-U18  
**Parent:** WF-U17  
**Authority:** «استمر» 10-step batch · step 10/10  
**Date:** 2026-09-13

## Locked

| # | Decision |
|---|----------|
| A | Schema ALTER NONE |
| B | Soft reactivate: `is_active` false→true |
| C | Reject already active |
| D | HTTP: `POST …/workflow/approval-flows/{id}/reactivate` |
| E | Auth: manageWorkflow · Idempotency |

## Out of scope

CompleteTransfer hooks; payroll; SMTP.
