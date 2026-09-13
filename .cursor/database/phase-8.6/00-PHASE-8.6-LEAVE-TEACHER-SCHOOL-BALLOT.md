# PHASE 8.6 — LEAVE TEACHER SCHOOL BALLOT

---

```text
Date: 2026-09-13
Human: «استمر»
Unit: 8.6-U01 LeaveTeacherSchool (soft)
Status: LOCKED
Schema: ADD teacher_schools.left_at + RLS EXISTS filter
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-86-001 | Mechanism | Soft leave via `left_at` — **no hard DELETE** |
| HD-86-002 | Primary | Reject leave while `is_primary=true` (use set-primary first) |
| HD-86-003 | Visibility | Active membership = `left_at IS NULL` (app + body RLS EXISTS) |
| HD-86-004 | HTTP | `POST …/teachers/{id}/leave-school` |
| HD-86-005 | Body | `academic_year_id` (school = context) |
| HD-86-006 | Re-join | Via assign-school clears `left_at` if prior left row exists |
| HD-86-007 | Auth | `teachers.manage` |
| HD-86-008 | Idempotency | Required |
