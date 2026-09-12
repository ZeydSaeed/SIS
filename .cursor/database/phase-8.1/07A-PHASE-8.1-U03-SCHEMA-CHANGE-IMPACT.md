# PHASE 8.1 — U03 SCHEMA CHANGE IMPACT

---

```text
Change: ALTER teachers.teacher_qualifications
Type: ALTER (additive)
Risk: LOW
```

## Checklist

- [x] Table in database-blueprint.md (updated)
- [x] Additive columns only (no drop)
- [x] status = smallInteger (not TINYINT)
- [x] No hard-delete path
- [x] Index (teacher_id, status) justified for list/void lookups
- [x] Backfill existing rows Active + effective_from=created_at
- [x] Migration versioned
- [x] No RLS change on body (HOLD)

## Blast radius

```text
- AddTeacherQualification insert
- ListTeacherQualifications read mapping
- New VoidTeacherQualification write
- PG tests Phase81*
```

## Rollback

```text
migrate:rollback step — drops added columns/index (dev/test only)
```
