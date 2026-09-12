# PHASE WF — U04+U05
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Units: WF-U04 (Schema+RLS requests) + WF-U05 (Create/List HTTP)
AuthZ: 07 GRANTED («استمر»)
Ballot: 05 LOCKED
Audit: PASS
Closure: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-12
```

## Delivered

```text
WF-U04:
- workflow.approval_requests (+ school_id)
- Partial UNIQUE open Pending per entity
- FORCE RLS + reject hard DELETE

WF-U05:
- CreateApprovalRequest (idempotent)
- ListApprovalRequests (?entity_type=&entity_id=&request_status=)
- Routes:
  POST /api/v1/workflow/approval-requests
  GET  /api/v1/workflow/approval-requests
```

## Out of scope (HOLD)

```text
- Approve / Reject / Advance
- Auto-hooks into Transfers/Promotion/Documents/Finance
```

## Validation

```text
PhaseWfApprovalRequests* → PASS
architecture:validate --fitness → PASS
architecture:feature-check Workflow → PASS
security:validate → PASS
```

```text
WF-U04: CLOSED / ACCEPTED
WF-U05: CLOSED / ACCEPTED WITH CONDITIONS
(condition: no decide engine)
```
