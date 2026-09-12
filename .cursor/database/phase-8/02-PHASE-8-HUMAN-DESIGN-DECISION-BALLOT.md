# MASTER PHASE 8 — TEACHERS
# HUMAN DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Human «استمر» → RECOMMENDED SET APPLIED
```

### HD-8-001 — Domain

```text
[x] A — Teachers staff CQRS/HTTP (not full HR)
```

### HD-8-002 — RLS strategy

```text
[x] A — FORCE RLS on teacher_schools + teacher_subjects (school_id)
         teachers.teachers remains without row school_id — access via JOIN + app
[ ] B — EXISTS-policy RLS on teachers.teachers (insert chicken-egg) — REJECTED v1
```

### HD-8-003 — Soft lifecycle

```text
[x] A — status SMALLINT; reject hard DELETE via trigger
[ ] B — Hard delete allowed — FORBIDDEN
```

### HD-8-004 — Register semantics

```text
[x] A — Create teacher + primary teacher_schools row (school + academic_year) in one command
```

### HD-8-005 — U01 scope

```text
[x] A — RLS + reject-delete + RegisterTeacher + List/Show HTTP
[ ] B — Big-bang subjects + qualifications too
```

### HD-8-006 — Out of phase v1

```text
[x] A — Payroll, contracts, finance, promotion/transfers physicalization — OUT
```

```text
BALLOT: RECORDED / APPLIED
```
