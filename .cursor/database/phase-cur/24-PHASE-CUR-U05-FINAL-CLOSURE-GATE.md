# PHASE CUR — U05 FINAL CLOSURE GATE

```text
Unit: CUR-U05
Status: CLOSED
Date: 2026-09-13
Next: CUR-U06 (grade-pass prereq evidence) — ballot required
```

## Evidence

| Check | Result |
|-------|--------|
| Migration `2026_09_13_063000_phase_cur_u05_enrollment_subjects_rls` | applied |
| HTTP assign / list / deactivate | live |
| Prereq gate evidence v1 | history on same student+school |
| PG test | PhaseCurEnrollmentSubjectPrereqHttpApiPostgreSqlTest |
| architecture:validate --fitness | PASS |
| architecture:feature-check Enrollment | PASS |

## Absolute holds (unchanged)

```text
exam.session.cancel — forbidden
student_grades DEFAULT partition — forbidden
Ranking/PDF — after reopen 7.5/7.8 only
Payroll — new ballot required
```
