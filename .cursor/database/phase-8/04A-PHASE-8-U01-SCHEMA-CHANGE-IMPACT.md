# PHASE 8 — U01 SCHEMA CHANGE IMPACT (RLS)

---

```text
Change: ENABLE/FORCE RLS on teachers.teacher_schools + teachers.teacher_subjects
         + reject hard DELETE triggers on teachers.* (4 tables)
Type: SECURITY / CONSTRAINT (no new tables, no column changes)
Blueprint objects: unchanged
Risk: MEDIUM (existing test inserts must set app.current_school_id)
```

## Checklist

```text
[x] Tables in blueprint
[x] No DROP of academic history columns
[x] No new indexes (policy only)
[x] FORCE RLS matches school isolation pattern (vocational/timetable)
[x] teachers.teachers intentionally without school RLS (HD-8-002)
```
