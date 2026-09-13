# PHASE HR — U04 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: HR-U04 Soft reactivate
AuthZ: 14 GRANTED («استمر»)
Audit: PASS WITH CONDITIONS
Closure: CLOSED WITH CONDITIONS
Date: 2026-09-13
Schema: NONE
```

## Delivered

```text
Commands: ReactivateJobPosition, ReactivateEmployee
Routes:
  POST /api/v1/hr/job-positions/{id}/reactivate
  POST /api/v1/hr/employees/{id}/reactivate
Employee: status=Active + effective_to cleared
```

## Conditions

```text
- Payroll still NOT AUTHORIZED
```

## Validation

```text
PhaseHrEmployeesHttpApiPostgreSqlTest → 7 passed / 60 assertions
architecture:feature-check Hr → PASS
architecture:validate --fitness complexity_gate → PASS
(security_architecture SEC-DEP-001 pre-existing composer audit — unrelated)
```
