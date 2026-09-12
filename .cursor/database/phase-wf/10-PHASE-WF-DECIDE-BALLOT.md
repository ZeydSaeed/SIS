# PHASE WF — DECIDE ENGINE BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر»
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-WF-DEC-001 | Open decide now? | A yes · B hold | **A yes** |
| HD-WF-DEC-002 | Approve behavior | A final only · B advance then final | **B advance if more steps; else Approved** |
| HD-WF-DEC-003 | Reject | Pending → Rejected + completed_at | **Yes** |
| HD-WF-DEC-004 | Role-gated step actor? | A enforce role · B manageWorkflow only v1 | **B manageWorkflow only** |
| HD-WF-DEC-005 | Auto-hooks modules? | A yes · B HOLD | **B HOLD** |
| HD-WF-DEC-006 | Cancel separate? | A yes · B HOLD | **B HOLD** |

## Implications

```text
IN: DecideApprovalRequest (approve|reject) + tests
OUT: role-per-step enforcement, cancel, module hooks
```
