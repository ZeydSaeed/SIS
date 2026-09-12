# PHASE 8 — U02 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: 8-U02 Update / deactivate + subject assign (+ unlink)
Rejected for now: Qualifications HTTP · payroll · promotion/transfers
Status: GRANTED
```

## Scope (IN)

```text
UpdateTeacher (profile fields; not employee_code rename in v1)
DeactivateTeacher (status → Inactive)
AssignTeacherSubject (school+year scoped; idempotent)
UnlinkTeacherSubject (DELETE assignment row only — not teacher identity)
HTTP PATCH /teachers/{id}, POST /teachers/{id}/deactivate
     POST /teachers/{id}/subjects, DELETE /teachers/{id}/subjects
Migration: drop reject-delete trigger on teacher_subjects only (assignment unlink)
PG tests
```

## Scope (OUT)

```text
Qualifications · change employee_code · multi-school transfer · payroll
```
