# Phase STU-DOC-U01 — Student documents physicalize — BALLOT

**Unit:** STU-DOC-U01  
**Parent:** Master audit — `students.student_documents` missing  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Physicalize blueprint `students.student_documents` with tenant isolation and soft lifecycle.

## Options (locked)

| # | Decision |
|---|----------|
| A | Create `students.student_documents` (metadata only — no BYTEA) |
| B | Enrichment: `school_id` + soft `status` (1=Active, 2=Voided) |
| C | FORCE RLS on `school_id`; reject hard DELETE |
| D | HTTP: `POST/GET …/students/{id}/documents`; `POST …/student-documents/{id}/void` |
| E | Auth: students.update (write) / students.view (list); Idempotency on writes |
| F | Binary upload — HOLD (reuse DOC-U04 later) |
| G | `documents.files` remains generic store — student_documents is typed student catalog |

## Out of scope

Payroll; Ranking/PDF; S3/AV.
