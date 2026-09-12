# MASTER PHASE 7 — PHASE 7.4
# HUMAN IMPLEMENTATION AUTHORIZATION — 7.4-U06

---

```text
Unit: 7.4-U06 — CalculateAnnualResult (operational)
Status: GRANTED
Date: 2026-09-12
```

## Behavior

```text
- Roll up from results.term_results WHERE is_current_operational for enrollment+year
- Fail if zero term rows
- average_weighted_total = mean of non-null weighted_total (NUMERIC 8,2 half-up)
- subjects_* counts from term rows
- incomplete if any term incomplete OR any weighted_total null
- Persist operational annual version + AnnualResultCalculated outbox
- Idempotency required
- No HTTP / GPA
```
