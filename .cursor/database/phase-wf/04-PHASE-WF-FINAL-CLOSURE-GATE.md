# PHASE WF — APPROVAL FLOWS
# FINAL CLOSURE GATE (slice)

---

```text
Subphase: Phase WF — approval_flows catalog
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-12
```

## Unit matrix

| Unit | Deliverable | Status |
|------|-------------|--------|
| U01 | Schema + FORCE RLS (+ school_id) | CLOSED |
| U02 | Create + List HTTP | CLOSED |
| Gate | Final Closure (flows slice) | CLOSED |
| WF-RUNTIME | approval_requests engine | HOLD |

## Conditions

```text
- Flow definitions only — steps not executed
- Runtime approve/reject requires separate ballot
```

## Recommended next

```text
1) FIN-BALLOT for money movement (student_fees/payments), OR
2) Empty-schema polish / observability (Phase 2), OR
3) Ranking/PDF only after reopening 7.5/7.8
```

```text
PHASE WF FLOWS FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Approval flow Create/List live under school FORCE RLS. Runtime HOLD.
```
