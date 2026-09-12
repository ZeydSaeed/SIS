# MASTER PHASE 7 — PHASE 7.5
# HUMAN IMPLEMENTATION AUTHORIZATION — 7.5-U02

---

```text
Unit: 7.5-U02 — CalculateGpa (operational, year scope)
Status: GRANTED
Date: 2026-09-12
```

```text
- Input: current official annual_results for enrollment+year
- Fail if missing official annual
- gpa_value = average_weighted_total; scale_code=PERCENT_100
- Persist operational gpa_results version
- Idempotency + GpaCalculated outbox
- Tests + fitness
- No HTTP / Finalize (U03)
```
