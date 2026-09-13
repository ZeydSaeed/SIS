# PHASE HR — SOFT REACTIVATE BALLOT (HR-U04)

---

```text
Date: 2026-09-13
Human: «استمر» (after HR-U03)
Unit: HR-U04 Soft reactivate job_positions + employees
Status: LOCKED
Payroll: OUT
Schema change: NONE
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-HR-U04-001 | Scope | Soft reactivate only |
| HD-HR-U04-002 | Job position | `status = Active` |
| HD-HR-U04-003 | Employee | `status = Active` + clear `effective_to` |
| HD-HR-U04-004 | HTTP | `POST …/job-positions/{id}/reactivate`, `POST …/employees/{id}/reactivate` |
| HD-HR-U04-005 | Auth | `hr.manage` |
| HD-HR-U04-006 | Idempotency | Required |
| HD-HR-U04-007 | Already active | Idempotent success |
| HD-HR-U04-008 | Payroll | **OUT** |
