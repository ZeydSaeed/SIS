# HR-PAY-U05 AuthZ Slice Final Closure Gate

**Status:** PASS WITH CONDITIONS · Schema NONE  
**Branch:** `feature/phase-hr-pay-slice1`  
**Date:** 2026-09-14

## Closed in AuthZ slice

| Unit | Outcome |
|------|---------|
| HR-PAY-U01 Design lock | PASS |
| HR-PAY-U02 Permission catalog | PASS |
| HR-PAY-U03 Role wiring + PG registration test | PASS |
| HR-PAY-U04 HrPayrollPolicy + Gates | PASS |
| HR-PAY-U05 AuthZ slice closure | PASS WITH CONDITIONS |

## Delivered

- Permissions: `hr.payroll.view`, `hr.payroll.manage`
- Roles: `hr_payroll_viewer`, `hr_payroll_manager`
- Gates: `viewHrPayroll`, `manageHrPayroll`
- Policy: `HrPayrollPolicy` (school-scoped via `HrSchoolAccessService`)

## Conditions / remaining HOLDs

- **No payroll tables/migrations** until new human ballot + `database-change` skill
- No pay-run HTTP commands
- Contracts / leaves / shifts remain HOLD
- Carry-forward absolute HOLDs from Phase 10 start AuthZ

## Recommended next step

Human ballot for payroll schema Slice-1 (tables + academic_year scoping) on this branch or a follow-on branch after approval.
