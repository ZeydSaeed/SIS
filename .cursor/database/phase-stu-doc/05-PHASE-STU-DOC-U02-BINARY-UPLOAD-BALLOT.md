# Phase STU-DOC-U02 — Student document binary upload — BALLOT

**Unit:** STU-DOC-U02  
**Parent:** STU-DOC-U01 HOLD F (binary upload)  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Expose binary upload/download for `students.student_documents` using DOC-U04 object storage (no BYTEA, no schema ALTER).

## Options (locked)

| # | Decision |
|---|----------|
| A | Multipart upload writes object + `student_documents` row (typed catalog) |
| B | Reuse `DocumentObjectStoragePort` / local `sis_documents` disk |
| C | HTTP: `POST …/students/{id}/documents/upload`; `GET …/student-documents/{id}/content` |
| D | Auth: students.update (upload) / students.view (download); Idempotency on upload |
| E | MIME/size allowlist from `sis.documents` config (same as DOC-U04) |
| F | Metadata-only `POST …/documents` remains; `documents.files` remains independent |
| G | Schema ALTER — NONE |
| H | S3 / AV / PDF engine — HOLD |

## Out of scope

Payroll; Ranking/PDF; S3 credentials; virus scan; BYTEA.
