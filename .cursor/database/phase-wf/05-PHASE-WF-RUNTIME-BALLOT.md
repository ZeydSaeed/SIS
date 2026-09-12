# PHASE WF — APPROVAL REQUESTS RUNTIME BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-12
Human: «استمر»
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-WF-RT-001 | Open approval_requests? | A yes · B hold | **A yes (create/list)** |
| HD-WF-RT-002 | school_id? | A ADD · B derive | **A ADD** |
| HD-WF-RT-003 | Multi-step Approve/Reject/Advance? | A yes · B HOLD | **B HOLD** |
| HD-WF-RT-004 | Auto-hook Transfers/Promotion? | A yes · B HOLD | **B HOLD** |
| HD-WF-RT-005 | Duplicate open request same entity? | A allow · B reject | **B reject** |
| HD-WF-RT-006 | Hard delete | A allow · B reject | **B reject** |
| HD-WF-RT-007 | entity_type must match flow | A yes · B free | **A yes** |

## Implications

```text
IN: approval_requests + CreateApprovalRequest + ListApprovalRequests
OUT: decide/advance engine, auto-hooks
```
