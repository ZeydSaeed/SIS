# PHASE CUR — U02 SCHEMA CHANGE IMPACT

---

```text
Change: reject hard DELETE trigger + subject_type CHECK on curriculum.subjects
Type: CONSTRAINT / TRIGGER (additive)
Risk: LOW
```

## Checklist

- [x] No column drops
- [x] Existing rows: subject_type historically seeded as 1 — CHECK 1..3 safe
- [x] Soft status remains app path
- [x] Blueprint HTTP note updated
