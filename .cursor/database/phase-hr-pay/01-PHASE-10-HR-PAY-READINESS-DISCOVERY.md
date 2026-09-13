# Phase 10 HR-PAY — Readiness Discovery (Schema NONE)

Opened after enrichment wave closure. Payroll remains **blocked** until ballot.

## Known baseline

- HR employees + job_positions soft deactivate/reactivate: CLOSED WITH CONDITIONS
- Payroll / contracts / leaves / shifts: NOT closed — need new ballot
- AuthZ design lock: **HR-PAY-U01 LOCKED** (`hr.payroll.view` / `hr.payroll.manage`)

## Discovery checklist

- [x] Map blueprint payroll objects (if any) vs live migrations — **none in blueprint/migrations today**
- [x] Confirm tenant + academic_year scoping rules for pay runs — **deferred**; AuthZ will reuse school context + HrSchoolAccessService until pay-run ballot
- [x] Idempotency + outbox pattern for pay run posting — **deferred** to post-schema ballot (mirror finance payments)
- [x] AuthZ roles vs finance overlap — **independent** permissions; no finance.* reuse (HD-PAY-006)
