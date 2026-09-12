# PHASE FIN-U08 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE finance.transactions + school_id + FORCE RLS (unpartitioned)
Risk: MEDIUM (append ledger; hooks into fee/payment writes)
Date: 2026-09-12
```

## Checklist

```text
[x] Ballot locked
[x] school_id ADD
[x] academic_year_id required
[x] amount NUMERIC(12,2)
[x] No partition yet (adaptive)
[x] Reject hard DELETE
[x] FORCE RLS
[ ] refunds — NOT in this unit
```
