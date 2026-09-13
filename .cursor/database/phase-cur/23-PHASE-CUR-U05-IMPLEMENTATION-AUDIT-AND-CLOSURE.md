# PHASE CUR — U05 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: CUR-U05 Enrollment subject + prerequisite enforcement
AuthZ: GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: enrollment.enrollment_subjects FORCE RLS + soft status CHECK + reject hard DELETE
```

## Delivered

```text
POST /api/v1/enrollments/{enrollment}/subjects
GET  /api/v1/enrollments/{enrollment}/subjects
POST /api/v1/enrollment-subjects/{link}/deactivate
AssignEnrollmentSubjectGuard → PrerequisiteCatalogPort (evidence v1: subject history)
```

## Conditions / HOLD

```text
Grade-based prerequisite pass evidence → CUR-U06
specialization_id HOLD (unchanged)
Payroll — no ballot
```

## Validation

```text
PhaseCurEnrollmentSubjectPrereqHttpApiPostgreSqlTest → 1 passed / 16 assertions
architecture:validate --fitness → PASS
architecture:feature-check Enrollment → PASS
```
