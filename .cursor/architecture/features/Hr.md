# Feature: Hr

> Phase HR Slice-1 — employees foundation (no payroll).

## Definition of Done

See [.cursor/architecture/FEATURE-DONE.md](../FEATURE-DONE.md).

## Bounded Context

- **Context:** Hr
- **Primary aggregate:** Employee (+ JobPosition catalog)
- **Scoped by:** school_id via employee_schools / job_positions; academic_year_id on membership

## Planned Use Cases

| Type | Name | Status |
|------|------|--------|
| Command | CreateJobPosition | ✅ |
| Command | RegisterEmployee | ✅ |
| Command | DeactivateJobPosition | ✅ |
| Command | DeactivateEmployee | ✅ |
| Command | ReactivateJobPosition | ✅ |
| Command | ReactivateEmployee | ✅ |
| Query | ListJobPositions | ✅ |
| Query | ListEmployees | ✅ |

## Out of scope (Slice-1)

- Payroll, contracts, leaves, shifts
- persons SSOT rewrite
- Automatic teacher→employee migration
