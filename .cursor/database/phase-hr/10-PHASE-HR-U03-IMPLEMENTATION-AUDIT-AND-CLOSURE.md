# PHASE HR — U03 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: HR-U03 Soft deactivate job_positions + employees
AuthZ: 09 GRANTED («استمر»)
Audit: PASS WITH CONDITIONS
Closure: CLOSED WITH CONDITIONS
Date: 2026-09-13
Schema: NONE
```

## Delivered

```text
Commands: DeactivateJobPosition, DeactivateEmployee
Events: JobPositionDeactivated, EmployeeDeactivated
Routes:
  POST /api/v1/hr/job-positions/{id}/deactivate
  POST /api/v1/hr/employees/{id}/deactivate
Employee: status=Inactive + effective_to set
Job position: status=Inactive
```

## Conditions

```text
- Payroll still NOT AUTHORIZED
- Reactivate HTTP HOLD
```

## Validation

```text
PhaseHrEmployeesHttpApiPostgreSqlTest → 6 passed / 47 assertions
architecture:feature-check Hr → PASS
architecture:validate --fitness complexity_gate → PASS
```
