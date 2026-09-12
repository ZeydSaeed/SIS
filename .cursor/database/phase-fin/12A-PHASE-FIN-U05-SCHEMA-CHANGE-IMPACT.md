# PHASE FIN-U05 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE finance.payments + school_id + FORCE RLS
Risk: MEDIUM (cash movement rows; updates student_fees.status)
Date: 2026-09-12
```

## Checklist

```text
[x] Ballot HD-FIN-PAY-* locked
[x] school_id ADD for RLS
[x] amount NUMERIC(12,2) CHECK > 0
[x] UNIQUE(idempotency_key)
[x] FK student_fee_id RESTRICT
[x] Reject hard DELETE
[x] FORCE RLS
[ ] transactions — NOT in this unit
[ ] refunds — NOT in this unit
```
