# PHASE 3C.11 — INDEX IMPLEMENTATION PLAN

**Indexes NOT created.** Source: 3C.10 INDEX-CATALOG + 3C.10A classifications.

## Rules

- No index merely because FK exists if UNIQUE already covers  
- No speculative INCLUDE  
- NO PARTITION AT LAUNCH  

---

## Index register

| Index name (proposed) | Table | Columns | Predicate | INCLUDE | Class | Query | Write cost | 20y |
|----------------------|-------|---------|-----------|---------|-------|-------|------------|-----|
| PK | * | id | — | — | REQUIRED | point | low | ok |
| uq_completion_outcomes_school_enrollment | completion_outcomes | school_id, enrollment_id | — | — | REQUIRED | identity | low | ok |
| idx_completion_outcomes_school_year | completion_outcomes | school_id, academic_year_id | — | enrollment_id, student_id optional later | RECOMMENDED | year board | low | ok |
| idx_completion_outcomes_student_school | completion_outcomes | student_id, school_id | — | — | RECOMMENDED | multi-enroll | low | ok |
| uq_cov_outcome_version | completion_outcome_versions | completion_outcome_id, version_no | — | — | REQUIRED | history | low | ok |
| uq_cov_current_official | completion_outcome_versions | completion_outcome_id | is_current_official | — | REQUIRED | Q1 | med concurrency | ok |
| idx_cov_school_status_eval | completion_outcome_versions | school_id, eligibility_status, evaluated_at | — | — | RECOMMENDED | queues | med | ok |
| idx_cov_policy_version | completion_outcome_versions | eligibility_policy_version_id | — | — | RECOMMENDED | impact | low | ok |
| idx_re_version_status | requirement_evaluations | completion_outcome_version_id, result_status | — | req_def_ver optional | REQUIRED | Q2/Q3 | med | watch |
| idx_re_req_def_ver | requirement_evaluations | requirement_definition_version_id | — | — | DEFERRED | analytics | — | measure |
| uq_evidence_source | evidence_items | set, type, source_id, ver_ref | — | — | REQUIRED | provenance | med | watch |
| idx_ei_source_lookup | evidence_items | school_id, source_type, source_id | — | — | RECOMMENDED | fan-out | med | watch |
| uq_ga_school_enrollment | graduation_awards | school_id, enrollment_id | — | — | REQUIRED | identity | low | ok |
| idx_ga_school_year | graduation_awards | school_id, academic_year_id | — | — | RECOMMENDED | year | low | ok |
| uq_gav_award_version | graduation_award_versions | graduation_award_id, version_no | — | — | REQUIRED | history | low | ok |
| uq_gav_current_issued | graduation_award_versions | graduation_award_id | is_current_issued | — | REQUIRED | Q8 | med | ok |
| idx_gav_school_awarded | graduation_award_versions | school_id, awarded_at | issued | — | RECOMMENDED | report | low | ok |
| idx_approval_school_status | graduation_approvals | school_id, decision_status, requested_at | — | — | RECOMMENDED | pending | low | ok |
| idx_approval_version | graduation_approvals | completion_outcome_version_id | — | — | REQUIRED | Q7 | low | ok |
| idx_rev_award_version | revocation_records | graduation_award_version_id | — | — | REQUIRED | Q9 | low | ok |
| idx_super_pred_succ | outcome_supersessions | predecessor_id, successor_id | — | — | RECOMMENDED | lineage | low | ok |
| idx_polver_effective | eligibility_policy_versions | policy_id, effective_from, effective_to | — | — | RECOMMENDED | resolve | low | ok |
| extra BTREE on policy (school,code) | eligibility_policies | — | — | — | **REDUNDANT** | — | — | **omit** |
| INCLUDE coverings | various | — | — | — | DEFERRED | — | — | measure |

## Partition watchlist

| Table | Signal | Threshold (ASSUMPTION) | Key candidate | Complexity |
|-------|--------|------------------------|---------------|------------|
| evidence_items | rows, index size, seq scans | revisit ~>5M rows or measured p95 | academic_year_id RANGE/LIST | HIGH (FK/UNIQUE) |
| requirement_evaluations | same | same | completion year denorm | HIGH |

```text
NO PARTITION AT LAUNCH
```
