# PHASE CUR — U04 SUBJECT/CURRICULUM REACTIVATE + UPDATE BALLOT

---

```text
Date: 2026-09-13
Human: «استمر»
Unit: CUR-U04 Reactivate subject/curriculum + UpdateSubject
Status: LOCKED
Schema: NONE
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-CUR4-001 | Reactivate subject | `POST …/subjects/{id}/reactivate` → status=1 |
| HD-CUR4-002 | Reactivate curriculum | `POST …/curricula/{id}/reactivate` → status=1 |
| HD-CUR4-003 | Update subject | `PATCH …/subjects/{id}` — name, name_en, subject_type, credit_hours, max/pass_grade |
| HD-CUR4-004 | code change | PROHIBITED on PATCH (create-only) |
| HD-CUR4-005 | Update only when Active | Inactive → 404 unless reactivate first |
| HD-CUR4-006 | Auth | curriculum.manage / view unchanged |
| HD-CUR4-007 | Idempotency | Required on writes |
| HD-CUR4-008 | Enrollment prereq enforce | HOLD |
| HD-CUR4-009 | specialization_id | HOLD |
