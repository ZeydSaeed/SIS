# PHASE CUR — U03 SCHEMA CHANGE IMPACT

---

```text
Change: FORCE RLS curricula; ADD curriculum_subjects.status; RLS + reject DELETE
Type: ALTER + POLICY + TRIGGER
Risk: LOW–MEDIUM (additive status default 1)
```

## Checklist

- [x] status default 1 — backfill not required for empty/additive
- [x] RLS school isolation on curricula
- [x] curriculum_subjects isolation via parent curriculum school_id
- [x] Hard DELETE forbidden
- [x] Blueprint updated
