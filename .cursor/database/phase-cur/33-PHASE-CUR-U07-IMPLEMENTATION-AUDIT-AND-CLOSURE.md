# PHASE CUR — U07 IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: CUR-U07 Curriculum specialization_id on create
AuthZ: GRANTED («استمر»)
Audit: PASS
Closure: CLOSED
Date: 2026-09-13
Schema: NONE
```

## Delivered

```text
Optional specialization_id on POST …/curriculum/curricula
SpecializationCatalogPort (active + same school)
List payload includes specialization_id
```

## Conditions / HOLD

```text
PATCH specialization assign/clear → CUR-U08
Payroll — no ballot
```

## Validation

```text
PhaseCurCurriculumSpecializationHttpApiPostgreSqlTest → 1 passed
PhaseCurCurriculaHttpApiPostgreSqlTest → 1 passed (regression)
architecture:validate --fitness → PASS
architecture:feature-check Curriculum → PASS
```
