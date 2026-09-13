# PHASE HR — U01 (+ U02) IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: HR-U01 Schema+RLS · HR-U02 Position/Employee HTTP
AuthZ: 04 GRANTED («استمر»)
Audit: PASS WITH CONDITIONS
Closure: CLOSED WITH CONDITIONS
Date: 2026-09-13
```

## Delivered

```text
Schema hr: job_positions, employees, employee_schools
FORCE RLS + reject hard DELETE triggers
Migration: 2026_09_13_040000_phase_hr_u01_create_hr_employees_foundation.php

Commands: CreateJobPosition, RegisterEmployee
Queries: ListJobPositions, ListEmployees
Routes:
  POST/GET /api/v1/hr/job-positions
  POST/GET /api/v1/hr/employees
Permissions: hr.view, hr.manage (roles hr_viewer / hr_manager)
Audit: SEC_HR_DATA_ACCESS / SEC_HR_DATA_MODIFIED
Blueprint: 91 → 94 (+3 hr.*)
```

## Conditions / HOLDs

```text
- Payroll / contracts / leaves: NOT AUTHORIZED
- Cross-school employee_number / teacher_id uniqueness relies on DB UNIQUE when RLS hides peers
- Qual binary / persons rewrite: OUT
```

## Validation

```text
architecture:validate --fitness → PASS (ARCH-103 fixed via RegisterEmployeeGuard)
architecture:feature-check Hr → PASS
security:validate → PASS
complexity_gate → PASS
PhaseHrEmployeesHttpApiPostgreSqlTest → 5 passed / 36 assertions
```

```text
HR-U01/U02: CLOSED WITH CONDITIONS
Slice-1 employees foundation live; payroll remains OUT.
```
