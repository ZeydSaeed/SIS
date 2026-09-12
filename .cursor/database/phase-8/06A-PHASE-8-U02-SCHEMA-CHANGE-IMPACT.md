# PHASE 8 — U02 SCHEMA CHANGE IMPACT

---

```text
Change: DROP reject-delete trigger/function on teachers.teacher_subjects only
Reason: Subject assignment rows are links, not official academic ledgers;
         unlink requires DELETE under UNIQUE(teacher, subject, year, school)
Risk: LOW
Blueprint objects: unchanged
```

```text
[x] teachers.teachers / teacher_schools / qualifications DELETE remains forbidden
[x] teacher_subjects FORCE RLS unchanged
```
