# PHASE WF — CANCEL REQUEST BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر»
Status: LOCKED — recommended defaults adopted
Supersedes: HD-WF-DEC-006 HOLD on cancel
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-WF-CAN-001 | Open cancel now? | A yes · B hold | **A yes** |
| HD-WF-CAN-002 | Cancellable statuses | A Pending only · B Pending+Approved | **A Pending only** |
| HD-WF-CAN-003 | Terminal effect | Pending → Cancelled + completed_at | **Yes** |
| HD-WF-CAN-004 | Role-per-step? | A enforce · B manageWorkflow only | **B manageWorkflow only** |
| HD-WF-CAN-005 | Module auto-hooks? | A yes · B HOLD | **B HOLD** |

## Implications

```text
IN: CancelApprovalRequest (idempotent) + tests
OUT: cancel of Approved/Rejected; role-per-step; module hooks
```
