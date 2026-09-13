# PHASE CUR — U02 SUBJECT CATALOG HTTP BALLOT

---

```text
Date: 2026-09-13
Human: «استمر»
Unit: CUR-U02 Create / List / Soft-deactivate subjects
Status: LOCKED
Schema: reject hard DELETE trigger on curriculum.subjects (additive)
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-CUR2-001 | Surface | Global subject catalog HTTP (no school_id on row) |
| HD-CUR2-002 | Auth | `curriculum.view` / `curriculum.manage` + school context |
| HD-CUR2-003 | Create fields | code, name, name_en?, subject_type, credit_hours?, max_grade?, pass_grade? |
| HD-CUR2-004 | subject_type | 1=Core, 2=Elective, 3=Practical — CHECK 1..3 |
| HD-CUR2-005 | Grades | Defaults max=100 pass=50; require pass_grade ≤ max_grade |
| HD-CUR2-006 | Soft deactivate | status=2; no hard DELETE (trigger) |
| HD-CUR2-007 | Reactivate | OUT — HOLD for U03 if needed |
| HD-CUR2-008 | List | Active only (optional status filter OUT) |
| HD-CUR2-009 | Idempotency | Required on writes |
| HD-CUR2-010 | Curricula HTTP / enrollment prereq enforce | HOLD |
| HD-CUR2-011 | Update profile PATCH | HOLD (create+deactivate only) |
