# Phase CUR-U05 — Enrollment subject + prerequisite enforcement — BALLOT

**Unit:** CUR-U05  
**Parent HOLD:** Curriculum — enrollment prerequisite enforcement  
**Authority:** Continuation of Phase CUR + Enrollment subject linking  
**Date:** 2026-09-13

## Question

Close Curriculum HOLD for **enrollment prerequisite enforcement** by enabling enrollment↔subject linking with a prerequisite gate.

## Options (locked)

| # | Decision |
|---|----------|
| A | Soft `enrollment.enrollment_subjects` + FORCE RLS + reject hard DELETE |
| B | HTTP: `POST/GET …/enrollments/{id}/subjects`; `POST …/enrollment-subjects/{link}/deactivate` |
| C | Gate: every **active** `curriculum.prerequisites` edge for target subject must be satisfied |
| D | Evidence **v1**: prior `enrollment_subjects` history (any status) for same student + school |
| E | Grade-based pass evidence → **CUR-U06 HOLD** |
| F | Auth via Enrollment update/view; Idempotency-Key on writes |

## Out of scope

Payroll; grade-pass evidence; Ranking/PDF.
