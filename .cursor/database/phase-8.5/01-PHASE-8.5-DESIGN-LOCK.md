# PHASE 8.5 — DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
Schema: NO MIGRATION
```

## In

```text
SetTeacherPrimarySchool
POST /api/v1/teachers/{teacher}/set-primary-school
Demote source is_primary=false; promote target is_primary=true
```

## Out

```text
Delete/leave source membership
employee_code column rename
Payroll
```
