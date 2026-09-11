# Phase 3C.16 — Domain Model

## Identity (LOCKED)

```text
school_id + enrollment_id
```

Student_id is denormalized for query convenience only — never primary Graduation identity.

## Aggregates / records

| Concept | Tables |
|---------|--------|
| CompletionOutcome | `completion_outcomes` (+ versions, requirement_evaluations) |
| GraduationApproval | `graduation_approvals` |
| GraduationAward | `graduation_awards` + `graduation_award_versions` |
| Revocation | `revocation_records` (+ lineage tables) |

## Mandatory invariants enforced in app

1. Evaluator ≠ Approver (`EvaluatorApproverSeparation`)
2. No silent PublishAward
3. No empty-requirement “all satisfied”
4. Missing evidence ≠ satisfied (DL-020)
5. No hard delete of official records
6. Enrollment independence (writes keyed by enrollment_id)

## Policy-open (not invented)

- HD-31 Permission catalog
- HD-20/21 requirement content thresholds
- HD-36 reason catalogs / approval revocation roles
- HD-38 publication
- SS-MULTI StudentStatus projection
