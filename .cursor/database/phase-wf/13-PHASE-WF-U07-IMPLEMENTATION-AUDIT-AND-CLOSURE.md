# PHASE WF — U07
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: WF-U07 — DecideApprovalRequest
AuthZ: 12 GRANTED («استمر»)
Ballot: 10 LOCKED
Audit: PASS
Closure: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
```

## Delivered

```text
DecideApprovalRequest (idempotent)
- approve: advance step OR finalize Approved
- reject: Pending → Rejected + completed_at
Route: POST /api/v1/workflow/approval-requests/{id}/decide
Body: { "decision": "approve"|"reject" }
```

## Out of scope (HOLD)

```text
- Role-per-step actor enforcement
- Cancel HTTP
- Auto-hooks into other modules
```

## Validation

```text
PhaseWfDecide* + PhaseWfApprovalRequests* → 7 tests / 36 assertions PASS
architecture:validate --fitness → PASS
architecture:feature-check Workflow → PASS
```

```text
WF-U07: CLOSED / ACCEPTED WITH CONDITIONS
```
