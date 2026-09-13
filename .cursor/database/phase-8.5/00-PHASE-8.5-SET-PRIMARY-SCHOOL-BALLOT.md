# PHASE 8.5 — SET PRIMARY SCHOOL BALLOT

---

```text
Date: 2026-09-13
Human: «استمر»
Unit: 8.5-U01 SetTeacherPrimarySchool
Status: LOCKED
Schema: NONE
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-85-001 | Scope | Move `is_primary` from source → target (context) for same year |
| HD-85-002 | Precondition | Membership exists at **both** schools for `academic_year_id` |
| HD-85-003 | Source | Must currently be primary for that year |
| HD-85-004 | Leave source | **OUT** — membership retained, only demoted |
| HD-85-005 | Auth | `teachers.manage` + manage both schools |
| HD-85-006 | HTTP | `POST …/teachers/{id}/set-primary-school` |
| HD-85-007 | Body | `source_school_id`, `academic_year_id` |
| HD-85-008 | Idempotency | Required; already-primary at target → success |
| HD-85-009 | Hard remove membership | **HOLD** |
