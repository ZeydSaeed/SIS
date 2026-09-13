# PHASE 8.6 — DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
```

## Schema

```text
ALTER teachers.teacher_schools ADD left_at TIMESTAMPTZ NULL
Index: (school_id, academic_year_id) WHERE left_at IS NULL — optional partial later
Update body RLS EXISTS … AND ts.left_at IS NULL
```

## Application

```text
LeaveTeacherSchool → set left_at=now, is_primary=false
AssignTeacherSchool → if left row exists for target+year, clear left_at (rejoin)
belongsToSchool / list / isPrimary → left_at IS NULL
```
