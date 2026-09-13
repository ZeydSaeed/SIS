# PHASE STU-DOC — U01 DESIGN LOCK

---

```text
Status: LOCKED
Ballot: 00 LOCKED
AuthZ: GRANTED via «استمر»
```

## Schema

```text
students.student_documents
  + school_id, status
  FORCE RLS + reject hard DELETE
```

## Application

```text
RegisterStudentDocument / ListStudentDocuments / VoidStudentDocument
```
