# PHASE CUR — U01 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE curriculum.prerequisites
Type: CREATE TABLE
Risk: LOW (additive; FKs RESTRICT; no RLS — global catalog like subjects)
```

## Checklist

- [x] Table defined in database-blueprint.md (amended with status)
- [x] PK BIGINT IDENTITY
- [x] FKs RESTRICT to curriculum.subjects
- [x] UNIQUE + self-edge CHECK
- [x] Soft status — no hard-delete of catalog edges
- [x] No speculative indexes beyond UNIQUE + subject_id lookup
- [x] Blueprint count unchanged (already counted in curriculum 4 tables)
