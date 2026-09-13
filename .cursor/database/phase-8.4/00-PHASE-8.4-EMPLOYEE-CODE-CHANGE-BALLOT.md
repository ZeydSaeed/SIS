# PHASE 8.4 — EMPLOYEE CODE CHANGE BALLOT

---

```text
Date: 2026-09-13
Human: «استمر»
Unit: 8.4-U01 ChangeTeacherEmployeeCode
Status: LOCKED
Schema: NONE (value change, not column rename)
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-84-001 | Meaning of “rename” | Change `employee_code` **value** (not DB column rename) |
| HD-84-002 | HTTP | Dedicated `POST …/teachers/{id}/change-employee-code` |
| HD-84-003 | Keep UpdateTeacher | `employee_code` remains **prohibited** on PATCH |
| HD-84-004 | Uniqueness | Reject if taken; DB UNIQUE remains SSOT |
| HD-84-005 | Auth | `teachers.manage` + school membership |
| HD-84-006 | Idempotency | Required |
| HD-84-007 | Same code no-op | Success idempotent (treat as success) |
| HD-84-008 | Move primary / leave source | **HOLD** |
