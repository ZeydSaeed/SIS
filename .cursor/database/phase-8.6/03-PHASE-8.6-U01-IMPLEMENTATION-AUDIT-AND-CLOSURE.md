# PHASE 8.6 — U01 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: 8.6-U01 LeaveTeacherSchool
AuthZ: 02 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: ADD teacher_schools.left_at + body RLS active filter
```

## Delivered

```text
POST /api/v1/teachers/{teacher}/leave-school
Soft leave: left_at=now, is_primary=false
Reject leave while is_primary=true
Active membership = left_at IS NULL (app + body RLS)
assign-school rejoins by clearing left_at
```

## Conditions / HOLD

```text
NONE for 8.6-U01
Payroll / exam.session.cancel / Ranking-PDF reopen: absolute holds elsewhere
```

## Validation

```text
Phase86LeaveTeacherSchoolHttpApiPostgreSqlTest → 1 passed / 19 assertions
architecture:validate --fitness → PASS
```
