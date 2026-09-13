# PHASE CUR — U01 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: CUR-U01 Subject Prerequisites
AuthZ: 02 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: CREATE curriculum.prerequisites (+ status soft lifecycle)
```

## Delivered

```text
POST /api/v1/curriculum/subjects/{subject}/prerequisites
GET  /api/v1/curriculum/subjects/{subject}/prerequisites
POST /api/v1/curriculum/prerequisites/{id}/deactivate
Cycle + self-edge rejection; soft status; hard DELETE trigger
Permissions curriculum.view / curriculum.manage
```

## Conditions / HOLD

```text
Subjects/curricula CRUD HTTP HOLD
Enrollment prerequisite enforcement HOLD
```

## Validation

```text
PhaseCurPrerequisitesHttpApiPostgreSqlTest → 1 passed / 17 assertions
architecture:validate --fitness → PASS
architecture:feature-check Curriculum → PASS
```
