# PHASE TV — U12 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE vocational.workshops + FORCE RLS + reject DELETE
Type: CREATE TABLE
Risk: LOW (additive catalog)
Blueprint objects: 90 → 91
```

## Checklist

- [x] Defined in blueprint before migrate
- [x] PK BIGINT IDENTITY
- [x] school_id + FORCE RLS
- [x] FK school RESTRICT; room SET NULL
- [x] CHECK safety_capacity <= capacity
- [x] No ON DELETE CASCADE on academic history
- [x] Reject hard DELETE trigger
- [x] Index school_id + UNIQUE(school_id, code)
- [x] PG HTTP + constraint tests
- [x] Blueprint updated
