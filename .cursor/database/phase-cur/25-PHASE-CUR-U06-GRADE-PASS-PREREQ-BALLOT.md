# Phase CUR-U06 — Grade-pass prerequisite evidence — BALLOT

**Unit:** CUR-U06  
**Parent HOLD:** CUR-U05 — grade-based pass evidence  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Extend enrollment prerequisite satisfaction so a **passing finalized grade** on the prerequisite subject counts as evidence (in addition to U05 history).

## Options (locked)

| # | Decision |
|---|----------|
| A | **OR** composition: history (U05) **OR** grade-pass (U06) |
| B | Pass = current (`is_current`) + Finalized (`status=4`) + not absent + `score >= curriculum.subjects.pass_grade` |
| C | Scope: same `student_id` + `school_id` (any academic year) |
| D | Cross-context via Enrollment `PrerequisitePassEvidencePort` (no Exams Domain import in Enrollment Domain) |
| E | No schema migration — application evidence only |
| F | Entered/Submitted/Voided/absent/below-pass do **not** satisfy |

## Out of scope

Payroll; Ranking/PDF; changing U05 HTTP routes; DEFAULT `student_grades` partition.
