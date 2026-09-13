# PHASE 8.3 — U01 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: 8.3-U01 AssignTeacherSchool
AuthZ: 02 GRANTED («استمر»)
Audit: PASS WITH CONDITIONS
Closure: CLOSED WITH CONDITIONS
Date: 2026-09-13
Schema: NONE
```

## Delivered

```text
POST /api/v1/teachers/{teacher}/assign-school
Body: source_school_id, academic_year_id
Secondary membership is_primary=false
Actor must manage both context (target) and source_school_id
```

## Conditions / HOLD

```text
- employee_code rename: HOLD
- Move primary / leave source: HOLD
```

## Validation

```text
Phase83AssignTeacherSchoolHttpApiPostgreSqlTest → 2 passed / 15 assertions
architecture:validate --fitness complexity_gate → PASS
```
