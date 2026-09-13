# PHASE CUR — U06 FINAL CLOSURE GATE

```text
Unit: CUR-U06
Status: CLOSED
Date: 2026-09-13
Next: specialization_id HOLD or master-audit gap (not payroll without ballot)
```

## Evidence

| Check | Result |
|-------|--------|
| Schema migration | NONE |
| Grade-pass OR history | locked |
| PG test | PhaseCurGradePassPrereqHttpApiPostgreSqlTest |
| U05 regression | PhaseCurEnrollmentSubjectPrereqHttpApiPostgreSqlTest |
| architecture:validate --fitness | PASS |
| architecture:feature-check Enrollment | PASS |

## Absolute holds (unchanged)

```text
exam.session.cancel — forbidden
student_grades DEFAULT partition — forbidden
Ranking/PDF — after reopen 7.5/7.8 only
Payroll — new ballot required
```
