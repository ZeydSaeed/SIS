# PHASE CUR — U08 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: CUR-U08 PATCH curriculum specialization
AuthZ: GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: NONE
```

## Delivered

```text
PATCH /api/v1/curriculum/curricula/{id}  { specialization_id: int|null }
UpdateCurriculumSpecialization + guard + event
```

## Conditions / HOLD

```text
Full curriculum name/grade PATCH — HOLD
Payroll — no ballot
```

## Validation

```text
PhaseCurPatchCurriculumSpecializationHttpApiPostgreSqlTest → 1 passed / 13 assertions
architecture:validate --fitness → PASS
architecture:feature-check Curriculum → PASS
```
