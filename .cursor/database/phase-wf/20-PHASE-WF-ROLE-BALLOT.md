# PHASE WF — ROLE-PER-STEP BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» (after OBS-PROM recommended next)
Status: LOCKED — recommended defaults adopted
Supersedes: HD-WF-DEC-004 manageWorkflow-only
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-WF-ROLE-001 | Open role-per-step now? | A yes · B hold | **A yes** |
| HD-WF-ROLE-002 | manageWorkflow bypasses step role? | A yes bypass · B no — must match | **B no bypass** |
| HD-WF-ROLE-003 | HTTP gate for decide | A manage only · B manage OR workflow.decide | **B manage OR decide** |
| HD-WF-ROLE-004 | Role source | A security.roles.code @ school | **A user_roles → roles.code** |
| HD-WF-ROLE-005 | Module auto-hooks? | A yes · B HOLD | **B HOLD** |
| HD-WF-ROLE-006 | Schema change? | A yes · B none | **B none** |

## Implications

```text
IN: step role match on DecideApprovalRequest + workflow.decide permission + tests
OUT: module hooks; cancel auth unchanged (manageWorkflow)
```
