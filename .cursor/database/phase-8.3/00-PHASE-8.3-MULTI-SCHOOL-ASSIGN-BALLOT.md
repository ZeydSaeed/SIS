# PHASE 8.3 — MULTI-SCHOOL TEACHER ASSIGN BALLOT

---

```text
Date: 2026-09-13
Human: «استمر»
Unit: 8.3-U01 AssignTeacherSchool (secondary membership)
Status: LOCKED
Schema: NONE
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-83-001 | Scope | Add `teacher_schools` membership at **target** (= context school) |
| HD-83-002 | Proof of identity | Actor must manage **source_school_id** where teacher already has membership |
| HD-83-003 | RLS strategy | Bind source to verify membership; bind target to insert (no SECURITY DEFINER) |
| HD-83-004 | is_primary | Always `false` for this unit (no primary move) |
| HD-83-005 | HTTP | `POST /api/v1/teachers/{id}/assign-school` |
| HD-83-006 | Body | `source_school_id`, `academic_year_id` |
| HD-83-007 | Duplicate | Reject if already member for target+year |
| HD-83-008 | employee_code rename | **HOLD** |
| HD-83-009 | Full transfer (move primary / leave source) | **HOLD** |
