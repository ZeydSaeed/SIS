# PHASE FIN-U01 — SCHEMA CHANGE IMPACT

---

```text
Change: CREATE finance.fee_types + FORCE RLS + reject DELETE
Risk: LOW (catalog; no money movement)
Date: 2026-09-12
```

## Checklist

```text
[x] Blueprint aligned (fee_types)
[x] Schema finance
[x] PK BIGINT IDENTITY
[x] school_id FK RESTRICT
[x] amount NUMERIC(12,2) CHECK >= 0
[x] UNIQUE(school_id, code)
[x] No academic_year_id (catalog, not year-scoped charge)
[x] No hard-delete — reject trigger
[x] FORCE RLS school isolation
[ ] student_fees / payments / transactions — NOT in this unit
```

## Blast radius

```text
New schema + 1 table. No consumers yet. No partition.
```
