# PHASE TV — TV-U05
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: TV-U05 — vocational.* FORCE RLS harden
AuthZ: 12 GRANTED (استمر)
Audit: PASS
Closure: CLOSED / ACCEPTED
Blueprint count: 90 unchanged
Date: 2026-09-12
```

## Delivered

- Migration `2026_09_12_171000_phase_tv_u05_harden_vocational_rls.php`
  - FORCE RLS on specializations / tracks / specialization_subjects
  - Child isolation via EXISTS → specialization.school_id
  - reject hard DELETE on all three
- PG: `PhaseTvVocationalRlsPostgreSqlTest` (2 PASS)
- Blueprint vocational RLS notes
- fitness PASS

```text
TV-U05: CLOSED / ACCEPTED
NEXT: TV-U06 Vocational Application commands
```
