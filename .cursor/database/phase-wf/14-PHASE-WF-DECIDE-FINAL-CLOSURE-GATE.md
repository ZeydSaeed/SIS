# PHASE WF — DECIDE ENGINE
# FINAL CLOSURE GATE (slice)

---

```text
Subphase: Phase WF — approve/reject with step advance
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
```

## Workflow module matrix (v1)

| Slice | Status |
|-------|--------|
| approval_flows catalog | CLOSED |
| approval_requests create/list | CLOSED |
| decide (approve/reject/advance) | CLOSED |
| role-per-step enforcement | HOLD |
| cancel request | HOLD |
| module auto-hooks | HOLD |

## Conditions

```text
- manageWorkflow can decide any pending step (no role match yet)
- No side effects on Transfers/Promotion/etc.
```

## Recommended next

```text
1) CancelApprovalRequest (Pending → Cancelled) — natural lifecycle close, OR
2) Observability / Phase-2 polish, OR
3) COM-PROVIDER / FIN refund / Ranking-PDF only with explicit ballot reopen
```

```text
PHASE WF DECIDE FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Approve advances or finalizes; reject closes. Hooks HOLD.
```
