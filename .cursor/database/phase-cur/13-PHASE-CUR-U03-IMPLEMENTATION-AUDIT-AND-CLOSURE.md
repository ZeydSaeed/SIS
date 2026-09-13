# PHASE CUR — U03 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: CUR-U03 Curricula HTTP
AuthZ: 12 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: FORCE RLS curricula; curriculum_subjects.status + RLS + reject DELETE
```

## Delivered

```text
POST/GET /api/v1/curriculum/curricula
POST …/curricula/{id}/deactivate
POST/GET …/curricula/{id}/subjects
POST …/curriculum-subjects/{link}/deactivate
```

## Conditions / HOLD

```text
specialization_id on create HOLD
Subject PATCH/reactivate HOLD
Enrollment prerequisite enforcement HOLD
```

## Validation

```text
PhaseCurCurriculaHttpApiPostgreSqlTest → 1 passed / 17 assertions
architecture:validate --fitness → PASS
architecture:feature-check Curriculum → PASS
```
