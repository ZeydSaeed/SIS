# PHASE 8.6 — U01 SCHEMA CHANGE IMPACT

---

```text
Change: ADD teachers.teacher_schools.left_at + refresh body RLS EXISTS
Type: ALTER + POLICY replace
Risk: LOW–MEDIUM (additive; RLS tighter)
```

## Checklist

- [x] Additive column nullable — no backfill required (NULL = active)
- [x] Hard DELETE unchanged (still rejected)
- [x] Body RLS EXISTS requires left_at IS NULL
- [x] App membership queries filter left_at IS NULL
- [x] Blueprint updated
- [ ] Performance: no speculative index in this unit
