# PHASE HR — U01 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE SCHEMA hr + job_positions + employees + employee_schools + FORCE RLS
Type: CREATE
Risk: MEDIUM (new domain; parallel to teachers)
Blueprint: 91 → 94
```

## Checklist

- [x] Blueprint updated
- [x] BIGINT IDENTITY PKs
- [x] school_id on job_positions + employee_schools
- [x] employees body RLS via membership (teachers pattern)
- [x] FK RESTRICT / SET NULL as appropriate
- [x] Reject hard DELETE
- [x] Optional teacher_id UNIQUE nullable
- [x] No payroll tables
- [x] PG tests
