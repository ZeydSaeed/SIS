# Phase 3C.16 — Completion Evaluation

## Engine model

Caller supplies:

- `eligibility_policy_version_id` (pinned historical version)
- `requirement_results[]` with `requirement_definition_version_id` + `result_status`
- `calculation_version` string for reproducibility

## Result statuses (application convention)

| result_status | Meaning |
|---------------|---------|
| 1 | Satisfied |
| 2 | Not satisfied |
| 3 | Missing evidence |

Eligibility derivation: **all** results must be 1 → eligibility_status=2; else eligibility_status=3.

Empty requirement list → rejected (cannot invent pass).

## Explicitly not done

- No default GPA
- No hard-coded course/credit/attendance thresholds
- Does not write grades; may only be fed from Exams SSOT by callers later
