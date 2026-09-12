# PHASE WF — ROLE-PER-STEP
# FINAL CLOSURE GATE (slice)

---

```text
Subphase: Phase WF — enforce approval step role on decide
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
```

## Workflow module matrix (v1)

| Slice | Status |
|-------|--------|
| approval_flows catalog | CLOSED |
| approval_requests create/list | CLOSED |
| decide (approve/reject/advance) | CLOSED |
| cancel (Pending → Cancelled) | CLOSED |
| role-per-step enforcement | CLOSED |
| module auto-hooks | HOLD |

## Conditions

```text
- Actor must hold security.roles.code matching flow.steps[current_step].role
- manageWorkflow does NOT bypass step role
- HTTP gate: manageWorkflow OR workflow.decide
- Cancel/create still manageWorkflow-only
```

## Recommended next

```text
1) Module auto-hooks ballot (Transfers/Promotion/Documents/Finance), OR
2) COM-PROVIDER / FIN refund with explicit ballot, OR
3) Ranking/PDF only after reopening 7.5/7.8
```

```text
PHASE WF ROLE FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Step role enforced on decide. Hooks HOLD.
```
