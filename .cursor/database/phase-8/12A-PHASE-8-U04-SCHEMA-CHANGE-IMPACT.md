# PHASE 8 — U04 SCHEMA CHANGE IMPACT

---

```text
Change: ENABLE/FORCE RLS + policies on teachers.teachers, teacher_qualifications
Type: CONSTRAINT / SECURITY (no column ALTER)
Risk: MEDIUM (writer-path must keep school context)
```

## Checklist

- [x] No column drop
- [x] Fail-closed without app.current_school_id
- [x] teachers INSERT allowed with school setting (pre-membership)
- [x] qualifications require membership
- [x] Existing delete-reject triggers unchanged
- [x] Blueprint security notes updated
- [x] PG RLS actor test

## Blast radius

```text
- RegisterTeacher insert path
- Qualification add/list/void reads
- Direct SQL without school setting
```
