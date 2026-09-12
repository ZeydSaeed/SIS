# PHASE WF — DECIDE→TRANSFER SYNC BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» (after WF-HOOKS create→open)
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-WF-DSYNC-001 | Open decide→transfer sync? | A yes · B hold | **A yes** |
| HD-WF-DSYNC-002 | On final WF Approved | A mark transfer Approved · B Complete · C HOLD | **A Approved only** |
| HD-WF-DSYNC-003 | On final WF Rejected | A mark transfer Rejected · B ignore | **A Rejected** |
| HD-WF-DSYNC-004 | HTTP Approve at to_school still allowed? | A yes dual-path · B disable | **A yes** (first-writer wins) |
| HD-WF-DSYNC-005 | Failure mode | A soft-skip · B fail decide | **A soft-skip** |
| HD-WF-DSYNC-006 | Other entities | A none · B promotion too | **A none** |
| HD-WF-DSYNC-007 | Schema | A migrate · B none | **B none** |

## Implications

```text
IN: ApprovalEntityCompletionHookPort — transfer_request final Approved/Rejected → transfers status
OUT: auto-CompleteTransfer; promotion/docs/finance; fail-hard
```
