# PHASE WF — MODULE AUTO-HOOKS BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» (after WF-ROLE)
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-WF-HOOK-001 | Open auto-hooks now? | A yes narrow · B hold | **A yes narrow** |
| HD-WF-HOOK-002 | Entities in v1 | A transfer_request only · B +promotion · C all | **A transfer only** |
| HD-WF-HOOK-003 | Trigger | A on CreateTransferRequest · B on ApproveTransfer | **A create** |
| HD-WF-HOOK-004 | On final WF Approved | A auto-approve transfer · B HOLD | **B HOLD** |
| HD-WF-HOOK-005 | No active flow | A skip soft · B fail transfer | **A skip soft** |
| HD-WF-HOOK-006 | Approval school_id | A from_school · B to_school | **A from_school** |
| HD-WF-HOOK-007 | Multiple active flows | A lowest id · B fail | **A lowest id** |
| HD-WF-HOOK-008 | Schema | A migrate · B none | **B none** |

## Implications

```text
IN: Transfer create → open approval_request when active flow exists (from_school)
OUT: side-effect on WF decide; promotion/docs/finance hooks; fail-hard without flow
```
