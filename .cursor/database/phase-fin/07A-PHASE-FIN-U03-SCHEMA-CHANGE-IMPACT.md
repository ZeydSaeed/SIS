# PHASE FIN-U03 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE finance.student_fees + school_id + FORCE RLS
Risk: MEDIUM (financial obligation rows; no cash movement)
Date: 2026-09-12
```

## Checklist

```text
[x] Ballot HD-FIN-001..008 locked
[x] school_id ADD for RLS
[x] academic_year_id required
[x] amount NUMERIC(12,2) CHECK >= 0
[x] UNIQUE(school_id, enrollment_id, fee_type_id, academic_year_id)
[x] Reject hard DELETE
[x] FORCE RLS
[ ] payments / transactions — NOT in this unit
```
