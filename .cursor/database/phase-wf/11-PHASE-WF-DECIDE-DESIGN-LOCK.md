# PHASE WF — DESIGN LOCK (decide engine)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: WF-DECIDE
Ballot: 10 LOCKED
```

## In

```text
- DecideApprovalRequest (idempotent)
- decision: approve | reject
- approve: Pending only; if current_step < max(flow.steps.step) → current_step++; else Approved + completed_at
- reject: Pending → Rejected + completed_at
- Route: POST /api/v1/workflow/approval-requests/{approvalRequest}/decide
- Permission: workflow.manage
```

## Out

```text
- Role matching per step
- Cancel HTTP
- Auto-hooks Transfers/Promotion/Documents/Finance
```

## Units

| Unit | Name | Status |
|------|------|--------|
| WF-U07 | Decide HTTP | CLOSED |
| WF-U08 | Closure | CLOSED (see 14) |
