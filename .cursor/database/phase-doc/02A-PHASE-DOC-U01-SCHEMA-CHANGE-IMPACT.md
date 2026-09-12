# PHASE DOC — U01 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE documents.files + FORCE RLS + reject DELETE
Delta: ADD school_id for tenant isolation
Risk: LOW–MEDIUM
```

```text
[x] Blueprint update
[x] PK BIGINT IDENTITY
[x] No blob in DB
[x] FK uploaded_by → users SET NULL
[x] Indexes entity + hash
[x] FORCE RLS on school_id
```
