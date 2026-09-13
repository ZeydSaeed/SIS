# Phase CUR-U09 — PATCH curriculum name — BALLOT

**Unit:** CUR-U09  
**Parent HOLD:** CUR-U08 — full curriculum name/grade PATCH  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Expand `PATCH …/curriculum/curricula/{id}` to allow updating **name**, while keeping specialization assign/clear.

## Options (locked)

| # | Decision |
|---|----------|
| A | Same PATCH endpoint; `name` and/or `specialization_id` (`sometimes`) |
| B | At least one field required; empty body → `curriculum.curriculum_update_empty` |
| C | `specialization_id` present+null clears; omitted leaves unchanged |
| D | `academic_year_id` / `grade_level_id` / `school_id` remain prohibited |
| E | Active curriculum only; same specialization rules as U07/U08 |
| F | Schema: NONE |

## Out of scope

Changing grade_level/academic_year after create; payroll; Ranking/PDF.
