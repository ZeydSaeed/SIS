# PHASE 8.4 — U01 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: 8.4-U01 ChangeTeacherEmployeeCode
AuthZ: 02 GRANTED («استمر»)
Audit: PASS WITH CONDITIONS
Closure: CLOSED WITH CONDITIONS
Date: 2026-09-13
Schema: NONE
```

## Delivered

```text
POST /api/v1/teachers/{teacher}/change-employee-code
Body: employee_code (normalized uppercase)
PATCH update still prohibits employee_code
```

## Conditions / HOLD

```text
- Move primary / leave source school: HOLD
- Column rename to another name: OUT
```

## Validation

```text
Phase84ChangeTeacherEmployeeCodeHttpApiPostgreSqlTest → 2 passed / 9 assertions
architecture:validate --fitness complexity_gate → PASS
```
