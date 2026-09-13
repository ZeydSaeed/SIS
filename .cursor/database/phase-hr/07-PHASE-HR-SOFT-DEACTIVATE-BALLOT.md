# PHASE HR — SOFT DEACTIVATE BALLOT (HR-U03)

---

```text
Date: 2026-09-13
Human: «استمر» (continuation after Slice-1)
Unit: HR-U03 Soft deactivate job_positions + employees
Status: LOCKED
Payroll: OUT
Schema change: NONE (status + effective_to already exist)
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-HR-U03-001 | Scope | Soft deactivate only — no hard delete |
| HD-HR-U03-002 | Job position | `status = Inactive` |
| HD-HR-U03-003 | Employee | `status = Inactive` + `effective_to = now` |
| HD-HR-U03-004 | HTTP | `POST …/job-positions/{id}/deactivate`, `POST …/employees/{id}/deactivate` |
| HD-HR-U03-005 | Auth | `hr.manage` + school scope |
| HD-HR-U03-006 | Idempotency | Required `X-Idempotency-Key` |
| HD-HR-U03-007 | Already inactive | Idempotent success (re-apply Inactive) |
| HD-HR-U03-008 | Payroll / contracts | **OUT** |
