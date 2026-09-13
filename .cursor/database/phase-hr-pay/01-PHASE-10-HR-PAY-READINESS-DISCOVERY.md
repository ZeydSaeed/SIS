# Phase 10 HR-PAY — Readiness Discovery (Schema NONE)

Opened after enrichment wave closure. Payroll remains **blocked** until ballot.

## Known baseline

- HR employees + job_positions soft deactivate/reactivate: CLOSED WITH CONDITIONS
- Payroll / contracts / leaves / shifts: NOT closed — need new ballot

## Discovery checklist

- [ ] Map blueprint payroll objects (if any) vs live migrations
- [ ] Confirm tenant + academic_year scoping rules for pay runs
- [ ] Idempotency + outbox pattern for pay run posting
- [ ] AuthZ roles vs finance overlap
