# PHASE 8.1 — TEACHER QUALIFICATIONS
# DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: recommended set applied under absolute continuation
```

## In

```text
- AddTeacherQualification (idempotent)
- ListTeacherQualifications (school-scoped via teacher_schools membership)
- Permissions: teachers.view (list), teachers.manage (add)
- Hard DELETE remains FORBIDDEN on teacher_qualifications
- No new tables / no status column in v1
```

## Out

```text
- Qualification remove/void (needs status + effective_to — separate AuthZ)
- Document binary upload / storage service (document_storage_key string only)
- RLS on teacher_qualifications body (no school_id — scoped via membership check)
- Payroll / Promotion / Transfers
```

## Invariants

| ID | Rule |
|----|------|
| INV-81-01 | Mutate only if teacher belongs to context school (+ year when provided) |
| INV-81-02 | No hard delete of qualifications |
| INV-81-03 | Controllers thin — handlers own writes |
| INV-81-04 | Idempotency key required on Add |

## Units

| Unit | Name | Status |
|------|------|--------|
| 8.1-U01 | Add + List Qualifications HTTP | CLOSED |
| 8.1-U02 | Final Closure Gate | CLOSED |
