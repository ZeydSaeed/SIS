# PHASE DOC — DESIGN LOCK (binary upload)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: DOC-BINARY
Ballot: 05 LOCKED
```

## In

```text
- Port DocumentObjectStoragePort (put/get/exists)
- LocalDocumentObjectStorageAdapter on disk sis_documents
- UploadDocument: multipart → store object → insert documents.files metadata
- GetDocumentContent: stream by document id + school RLS row
- Config max_bytes + allowed_mimes
- Server-computed sha256
```

## Out

```text
- BYTEA / large objects in PostgreSQL
- S3 production wiring (port-ready HOLD)
- Virus scan
- PDF/certificate render
- Schema ALTER
```

## Units

| Unit | Name | Status |
|------|------|--------|
| DOC-U04 | Upload + Download + Local storage | AUTHORIZED |
| DOC-U05 | Closure | PENDING |
