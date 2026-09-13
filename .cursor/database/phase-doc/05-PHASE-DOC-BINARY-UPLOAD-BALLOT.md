# PHASE DOC — BINARY UPLOAD BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» after FIN void payment
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-DOC-BIN-001 | Open binary upload now? | A yes · B hold | **A yes** |
| HD-DOC-BIN-002 | Store blobs in PostgreSQL? | A yes · B object storage key only | **B object storage** |
| HD-DOC-BIN-003 | Driver v1 | A local disk · B S3 now | **A local** (`sis_documents`) |
| HD-DOC-BIN-004 | Download HTTP? | A yes · B upload-only | **A yes** (authz) |
| HD-DOC-BIN-005 | Virus scan / AV | A yes · B HOLD | **B HOLD** |
| HD-DOC-BIN-006 | PDF render / certificates | A open · B HOLD | **B HOLD** |
| HD-DOC-BIN-007 | Max size | — | **10 MiB** (config) |
| HD-DOC-BIN-008 | MIME allowlist | — | **pdf, jpeg, png, webp, plain** |
| HD-DOC-BIN-009 | Schema ALTER | A yes · B none | **B none** |
| HD-DOC-BIN-010 | Metadata-only Register | A keep · B remove | **A keep** |

## Implications

```text
IN: DocumentObjectStoragePort + Local adapter
    UploadDocument + GetDocumentContent HTTP
OUT: S3 credentials wave, AV, PDF engine, BYTEA in DB
```
