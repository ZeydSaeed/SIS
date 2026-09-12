# PHASE PT — U01 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE promotion.rules + promotion.records
         + FORCE RLS school isolation
         + reject hard DELETE triggers
Type: CREATE + SECURITY
Blueprint objects: 2 (already counted) — records gains school_id column (delta)
Risk: MEDIUM
```

## Checklist

```text
[x] Tables defined in database-blueprint.md (update school_id on records)
[x] PK BIGINT IDENTITY
[x] academic_year_id on records
[x] FK restrictOnDelete for academic refs
[x] No TINYINT — SMALLINT statuses
[x] TIMESTAMPTZ timestamps
[x] No hard-delete — reject triggers
[x] CHECK on promotion_status / grade direction
[x] FORCE RLS on school_id
[x] No partition (low volume decision tables)
[x] Index only blueprint + uniqueness for decision grain
[x] Transfers NOT created this unit
```

## Blast radius

```text
- New empty tables only
- No existing Application writers
- Enrollment / GPA / Graduation untouched
```
