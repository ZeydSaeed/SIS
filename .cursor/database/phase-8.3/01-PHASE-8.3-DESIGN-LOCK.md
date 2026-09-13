# PHASE 8.3 — DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
Schema: NO MIGRATION
```

## In

```text
AssignTeacherSchool command
POST /teachers/{teacher}/assign-school
source_school_id + academic_year_id
Secondary membership is_primary=false
```

## Out

```text
employee_code rename
Move primary / remove source membership
Payroll
```
