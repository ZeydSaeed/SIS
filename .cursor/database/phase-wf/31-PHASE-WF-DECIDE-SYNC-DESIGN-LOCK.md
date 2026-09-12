# PHASE WF — DESIGN LOCK (decide→transfer sync)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: WF-DECIDE-SYNC
Ballot: 30 LOCKED
```

## In

```text
- ApprovalEntityCompletionHookPort
- On Decide final Approved → markApproved(to_school_id) + TransferRequestApproved outbox
- On Decide final Rejected → markRejected(to_school_id) + TransferRequestRejected outbox
- Config sis.workflow.auto_hooks.transfer_decide_sync (default true)
- Soft-skip if not pending / missing / disabled
```

## Out

```text
- Auto CompleteTransfer
- Removing HTTP approve/reject at to_school
- Other entity types
```

## Units

| Unit | Name | Status |
|------|------|--------|
| WF-U15 | Decide→Transfer status sync | CLOSED |
| WF-U16 | Closure | CLOSED (see 34) |
