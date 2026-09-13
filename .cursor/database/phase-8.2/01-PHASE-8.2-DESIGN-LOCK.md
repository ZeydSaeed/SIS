# PHASE 8.2 — DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
Schema: NO MIGRATION
```

## In

```text
AttachTeacherQualificationDocument command
POST /api/v1/teachers/{teacher}/qualifications/{qualification}/attach-document
Body: academic_year_id, document_id
Sets teachers.teacher_qualifications.document_storage_key
```

## Out

```text
Inline multipart upload in Teachers API
employee_code rename
multi-school transfer
payroll
```
