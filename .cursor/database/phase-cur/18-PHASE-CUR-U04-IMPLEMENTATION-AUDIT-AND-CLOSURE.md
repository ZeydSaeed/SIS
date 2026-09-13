# PHASE CUR — U04 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: CUR-U04 Reactivate + UpdateSubject
AuthZ: 17 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: NONE
```

## Delivered

```text
PATCH /api/v1/curriculum/subjects/{id}
POST  …/subjects/{id}/reactivate
POST  …/curricula/{id}/reactivate
```

## Conditions / HOLD

```text
Enrollment prerequisite enforcement HOLD
specialization_id HOLD
```

## Validation

```text
PhaseCurReactivateUpdateHttpApiPostgreSqlTest → 1 passed / 12 assertions
architecture:validate --fitness → PASS
architecture:feature-check Curriculum → PASS
```
