# PHASE WF — DESIGN LOCK (module auto-hooks)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: WF-HOOKS
Ballot: 25 LOCKED
```

## In

```text
- TransferApprovalHookPort (owned by Transfers Application)
- Adapter opens Pending approval_request for entity_type=transfer_request
- Only when active approval_flow exists at from_school
- Soft skip when none / open already exists / disabled via config
- Config: sis.workflow.auto_hooks.transfer_request (default true)
```

## Out

```text
- Auto ApproveTransfer / CompleteTransfer on WF decide
- Promotion / Documents / Finance hooks
- Schema changes
- Breaking change to CreateTransfer HTTP response
```

## Units

| Unit | Name | Status |
|------|------|--------|
| WF-U13 | Transfer create → approval open | CLOSED |
| WF-U14 | Closure | CLOSED (see 29) |
