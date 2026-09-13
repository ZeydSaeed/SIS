# PHASE CUR — U02 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: CUR-U02 Subject Catalog HTTP
AuthZ: 07 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: subjects type/status/grades CHECK + reject hard DELETE
```

## Delivered

```text
POST /api/v1/curriculum/subjects
GET  /api/v1/curriculum/subjects
POST /api/v1/curriculum/subjects/{id}/deactivate
```

## Conditions / HOLD

```text
PATCH / reactivate subjects HOLD
Curricula HTTP HOLD
Enrollment prerequisite enforcement HOLD
```

## Validation

```text
PhaseCurSubjectsHttpApiPostgreSqlTest → 1 passed / 13 assertions
architecture:validate --fitness → PASS
architecture:feature-check Curriculum → PASS
```
