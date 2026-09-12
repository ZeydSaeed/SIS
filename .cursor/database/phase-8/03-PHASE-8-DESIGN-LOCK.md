# MASTER PHASE 8 — TEACHERS
# DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: 02 APPLIED
```

## In

```text
- FORCE RLS: teacher_schools, teacher_subjects
- Reject hard DELETE on teachers / teacher_schools / teacher_qualifications
- teacher_subjects DELETE allowed for unlink (U02)
- Commands: Register / Update / Deactivate / AssignSubject / UnlinkSubject
- Queries: ListTeachers / GetTeacher
- Permissions: teachers.view, teachers.manage
- HTTP teachers CRUD-lite + subjects
```

## Out

```text
- RLS on teachers.teachers body table (v1)
- Qualifications HTTP
- Payroll / HR contracts / finance
- Promotion / transfers schema physicalization
- Intelligence self-learning (not this phase number)
```

## Invariants

| ID | Rule |
|----|------|
| INV-8-01 | No hard delete of teacher rows |
| INV-8-02 | School operations require teacher_schools membership for context school+year |
| INV-8-03 | Register always creates primary teacher_schools row |
| INV-8-04 | Blueprint object count unchanged (no new tables) |
| INV-8-05 | Controllers thin — Application handlers own writes |

## Units

| Unit | Name | Status |
|------|------|--------|
| 8-U01 | RLS + Register + List/Show HTTP | CLOSED |
| 8-U02 | Update / deactivate + subject assign | CLOSED |
| 8-U03 | Final Closure Gate | CLOSED |
