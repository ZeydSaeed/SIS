# PHASE COM — COMMUNICATION TEMPLATES
# FINAL CLOSURE GATE (slice)

---

```text
Subphase: Phase COM — notification_templates catalog
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-12
```

## Unit matrix

| Unit | Deliverable | Status |
|------|-------------|--------|
| U01 | Schema + FORCE RLS (+ school_id) | CLOSED |
| U02 | Create + List HTTP | CLOSED |
| Gate | Final Closure (templates slice) | CLOSED |
| COM-SEND | Messages/jobs/providers | HOLD |

## Conditions

```text
- Templates only — no outbound delivery
- Deactivate HTTP deferred (is_active present)
- Send pipeline requires separate AuthZ + provider ballot
```

## Recommended next

```text
1) Phase WF — workflow.approval_flows catalog (low risk), OR
2) FIN-BALLOT for money movement, OR
3) Ranking/PDF only after reopening 7.5/7.8
```

```text
PHASE COM TEMPLATES FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Templates Create/List live under school FORCE RLS. Send pipeline HOLD.
```
