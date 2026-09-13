# Phase CUR-U08 — PATCH curriculum specialization — BALLOT

**Unit:** CUR-U08  
**Parent HOLD:** CUR-U07 — PATCH assign/clear specialization  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Allow updating `specialization_id` on an **active** curriculum (assign or clear).

## Options (locked)

| # | Decision |
|---|----------|
| A | `PATCH …/curriculum/curricula/{id}` — only `specialization_id` |
| B | Key **present** (nullable): integer → assign; `null` → clear |
| C | Target must be active in school; else `curriculum.curriculum_not_found` |
| D | Non-null value: active specialization same school (reuse SpecializationCatalogPort) |
| E | Idempotency-Key required |
| F | Schema: NONE |

## Out of scope

Full curriculum name/grade PATCH; payroll; Ranking/PDF.
