# PHASE CUR — U03 CURRICULA HTTP BALLOT

---

```text
Date: 2026-09-13
Human: «استمر»
Unit: CUR-U03 Create/List/Deactivate curricula + link/soft-unlink subjects
Status: LOCKED
Schema: FORCE RLS curricula; curriculum_subjects.status + RLS via parent; reject hard DELETE
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-CUR3-001 | Scope | School-scoped curricula (`school_id` from context) |
| HD-CUR3-002 | Create | name, academic_year_id, grade_level_id |
| HD-CUR3-003 | specialization_id | PROHIBITED in v1 (HOLD) |
| HD-CUR3-004 | Link | POST …/curricula/{id}/subjects |
| HD-CUR3-005 | Unlink | Soft `status` on curriculum_subjects (blueprint amend) |
| HD-CUR3-006 | Auth | curriculum.view / curriculum.manage |
| HD-CUR3-007 | Idempotency | Required on writes |
| HD-CUR3-008 | RLS | FORCE on curricula; curriculum_subjects via EXISTS parent school |
| HD-CUR3-009 | Enrollment prereq enforce / subject PATCH | HOLD |
