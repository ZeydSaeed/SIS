# PHASE DOC — DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
```

## In

```text
- Physicalize documents.files
- ADD school_id (RLS delta — blueprint sketch lacked tenant column)
- FORCE RLS school isolation
- Reject hard DELETE
- RegisterDocumentMetadata (idempotent) — storage_key string only, NO binary upload
- ListDocumentsByEntity
- Permissions: documents.view / documents.manage
```

## Out

```text
- Binary upload / S3 driver / virus scan
- Soft-delete void (no status column in v1 — append-only + reject delete)
- Finance / communication / workflow
- PDF render engine
```

## Invariants

| ID | Rule |
|----|------|
| INV-DOC-01 | DB stores metadata only — blobs in object storage |
| INV-DOC-02 | school_id + FORCE RLS |
| INV-DOC-03 | No hard delete |
| INV-DOC-04 | Controllers thin |

## Units

| Unit | Name | Status |
|------|------|--------|
| DOC-U01 | Schema + RLS | CLOSED |
| DOC-U02 | Register + List HTTP | CLOSED |
| DOC-U03 | Final Closure Gate | CLOSED (see 04) |
