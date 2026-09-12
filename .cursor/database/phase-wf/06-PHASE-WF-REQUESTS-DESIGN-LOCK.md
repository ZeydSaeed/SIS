# PHASE WF — DESIGN LOCK (approval_requests create/list)

---

```text
Status: LOCKED
Date: 2026-09-12
Slice: WF-REQUESTS-LITE
Ballot: 05 LOCKED
```

## In

```text
- Physicalize workflow.approval_requests (+ school_id)
- FORCE RLS + reject hard DELETE
- CreateApprovalRequest (idempotent) + ListApprovalRequests
- status DEFAULT Pending(1); current_step=1
- flow must be active + entity_type match
- Reject second Pending for same school+entity_type+entity_id
- Reuse workflow.view / workflow.manage
- List filter: request_status (not status — SecuritySensitiveFieldGuard)
```

## Out

```text
- Approve / Reject / Advance step HTTP
- Auto-hooks into Transfers/Promotion/Documents/Finance
```

## Units

| Unit | Name | Status |
|------|------|--------|
| WF-U04 | Schema + RLS requests | CLOSED |
| WF-U05 | Create + List HTTP | CLOSED |
| WF-U06 | Closure | CLOSED (see 09) |
| WF-DECIDE | Approve/Reject/Advance | CLOSED (see 14) |
