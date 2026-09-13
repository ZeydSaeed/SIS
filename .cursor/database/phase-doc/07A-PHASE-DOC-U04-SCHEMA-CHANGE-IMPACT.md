# PHASE DOC — U04 SCHEMA CHANGE IMPACT

---

```text
Change: NONE (application + filesystem only)
Tables: documents.files (existing metadata)
Risk: LOW–MEDIUM (PII files on disk — private path + AuthZ)
```

## Checklist

- [x] No BYTEA column
- [x] No migration
- [x] storage_key remains SSOT pointer
- [x] FORCE RLS unchanged
- [x] Blueprint HTTP note updated
- [x] PG HTTP tests with Storage::fake
