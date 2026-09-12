# PHASE 8 — U04 IMPLEMENTATION AUDIT AND CLOSURE

---

```text
Unit: 8-U04 Teachers body FORCE RLS
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
Migration: 2026_09_13_014000_phase8_u04_enable_teachers_body_rls.php
```

## Evidence

| Check | Result |
|-------|--------|
| FORCE RLS teachers.teachers | PASS |
| FORCE RLS teacher_qualifications | PASS |
| Isolation school A vs B (sis_rls_tester) | PASS |
| Empty school context hides rows | PASS |
| HTTP register + list under school context | PASS |
| Phase8Teachers + Phase81 regression (13) | PASS |

## Conditions / HOLD

```text
- Binary document upload for qualifications
- employee_code rename
- Multi-school teacher transfer HTTP
- Adding denormalized school_id on body tables (rejected — membership model)
```

## Files

```text
- database/migrations/2026_09_13_014000_phase8_u04_enable_teachers_body_rls.php
- tests/Feature/Database/PostgreSql/Phase8TeachersBodyRlsPostgreSqlTest.php
- .cursor/architecture/database-blueprint.md (security notes)
- phase-8/10–12A governance
```
