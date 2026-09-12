# PHASE 8 — U01
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: 8-U01 — Teachers RLS + Register + List/Show HTTP
AuthZ: 04 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED / ACCEPTED
Date: 2026-09-12
```

## Delivered

```text
Migration: FORCE RLS teacher_schools + teacher_subjects; reject DELETE on teachers.*
RegisterTeacher + ListTeachers + GetTeacher
Permissions: teachers.view / teachers.manage
Routes: POST/GET /api/v1/teachers, GET /api/v1/teachers/{id}
```

## Validation

```text
Phase8TeachersHttpApiPostgreSqlTest → passed
PhaseTvSchedule* regression → passed
architecture:validate --fitness → PASS
security:validate → PASS
```

```text
8-U01: CLOSED / ACCEPTED
```
