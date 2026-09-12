# PHASE WF — DESIGN LOCK (cancel request)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: WF-CANCEL
Ballot: 15 LOCKED
```

## In

```text
- CancelApprovalRequest (idempotent)
- Pending only → Cancelled + completed_at
- Route: POST /api/v1/workflow/approval-requests/{approvalRequest}/cancel
- Permission: workflow.manage (manageWorkflow)
- No schema change (status enum already includes Cancelled=4)
```

## Out

```text
- Cancel after Approved/Rejected
- Role matching per step
- Auto-hooks Transfers/Promotion/Documents/Finance
```

## Units

| Unit | Name | Status |
|------|------|--------|
| WF-U09 | Cancel HTTP | CLOSED |
| WF-U10 | Closure | CLOSED (see 19) |
