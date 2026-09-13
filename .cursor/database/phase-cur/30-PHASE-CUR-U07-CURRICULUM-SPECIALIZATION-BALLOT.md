# Phase CUR-U07 — Curriculum specialization_id — BALLOT

**Unit:** CUR-U07  
**Parent HOLD:** CUR-U03 HD-CUR3-003 specialization_id prohibited  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Allow optional `specialization_id` on curriculum create, scoped to an active vocational specialization in the same school.

## Options (locked)

| # | Decision |
|---|----------|
| A | `specialization_id` nullable optional on `POST …/curriculum/curricula` |
| B | Must reference `vocational.specializations` active + same `school_id` |
| C | Reject inactive / wrong-school / missing → `curriculum.specialization_invalid` |
| D | Expose `specialization_id` on list payload |
| E | Schema: NONE (column + FK already live) |
| F | PATCH assign/clear specialization → HOLD (CUR-U08) |

## Out of scope

Payroll; Ranking/PDF; enrollment specialization assignment.
