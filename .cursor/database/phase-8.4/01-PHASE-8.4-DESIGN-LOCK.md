# PHASE 8.4 — DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
Schema: NO MIGRATION
```

## In

```text
ChangeTeacherEmployeeCode command
POST /api/v1/teachers/{teacher}/change-employee-code
Body: employee_code
Normalize: trim + uppercase
```

## Out

```text
Column rename / API field rename to another name
Move primary school
Payroll
```
