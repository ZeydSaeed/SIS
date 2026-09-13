# PHASE STU-DOC — U02 DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 05 LOCKED
AuthZ: GRANTED via «استمر»
Schema ALTER: NONE
```

## Application

```text
UploadStudentDocument / GetStudentDocumentContent
Storage: DocumentObjectStoragePort (local sis_documents)
```

## HTTP

```text
POST /api/v1/students/{student}/documents/upload
GET  /api/v1/student-documents/{document}/content
```
