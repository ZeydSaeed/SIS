# HR-PAY-U01 Design Lock Ballot — Phase 10 Slice-1 AuthZ · Schema NONE

**Status:** LOCKED (recommended defaults for AuthZ-only slice)  
**Branch:** `feature/phase-hr-pay-slice1`  
**Date:** 2026-09-14

## Decisions

| ID | Question | Chosen |
|----|----------|--------|
| HD-PAY-001 | Open Phase 10 HR-PAY AuthZ slice now? | **A yes** — permissions/policy only |
| HD-PAY-002 | Create payroll tables in this slice? | **B HOLD** — ballot required later |
| HD-PAY-003 | Permission split | **A** `hr.payroll.view` + `hr.payroll.manage` (separate from `hr.view`/`hr.manage`) |
| HD-PAY-004 | Role | **A** new `hr_payroll_manager` (+ viewer optional later) |
| HD-PAY-005 | School scoping | **A** reuse `HrSchoolAccessService` |
| HD-PAY-006 | Overlap with finance.* | **B** no reuse — payroll AuthZ independent until pay-run design |
| HD-PAY-007 | Contracts / leaves / shifts | **B HOLD** (unchanged from HD-HR-008) |
| HD-PAY-008 | HTTP payroll commands in this slice? | **B none** — AuthZ foundation only |

## Implications

```text
IN (AuthZ slice):
  Permission::HR_PAYROLL_VIEW / HR_PAYROLL_MANAGE
  config security.permissions + roles.hr_payroll_manager
  HrPayrollPolicy + Gate viewHrPayroll / manageHrPayroll
  PG permission registration test

OUT:
  payroll_* tables · migrations · pay run HTTP · salary amounts · finance merge
```

## Absolute HOLDs

- No `hr.payroll_*` (or equivalent) tables without new human ballot + `database-change` skill
- Carry-forward HOLDs from Phase 10 start AuthZ doc
