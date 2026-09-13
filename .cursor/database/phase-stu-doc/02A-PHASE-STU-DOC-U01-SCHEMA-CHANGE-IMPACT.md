# PHASE STU-DOC — U01 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE students.student_documents + FORCE RLS + reject DELETE
Type: CREATE + POLICY + TRIGGER
Risk: LOW
Blast: New table only; documents.files unchanged
Blueprint: students remains 4 tables (was sketch-only)
```
