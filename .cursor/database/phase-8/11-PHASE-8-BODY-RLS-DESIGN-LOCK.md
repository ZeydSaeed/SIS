# PHASE 8 — DESIGN LOCK (teachers body RLS)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: 8-BODY-RLS
Ballot: 10 LOCKED
```

## In

```text
- FORCE RLS on teachers.teachers
  USING: membership in app.current_school_id via teacher_schools
  WITH CHECK: app.current_school_id IS NOT NULL (bootstrap insert)
- FORCE RLS on teachers.teacher_qualifications
  USING/WITH CHECK: teacher membership via teacher_schools
- PG isolation test with sis_rls_tester role
```

## Out

```text
- ALTER add school_id on body tables
- Binary upload / employee_code rename / multi-school transfer HTTP
```

## Units

| Unit | Name | Status |
|------|------|--------|
| 8-U04 | Body RLS migration + tests | AUTHORIZED |
| 8-U05 | Closure | PENDING |
