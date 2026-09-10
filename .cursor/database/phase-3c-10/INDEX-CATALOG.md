# PHASE 3C.10 — INDEX CATALOG

**DDL NOT EXECUTED.** Do not index every FK blindly.

| Index | Table | Columns | INCLUDE | Predicate | Query | Rationale | Risk |
|-------|-------|---------|---------|-----------|-------|-----------|------|
| pk | * | id | — | — | point lookup | Standard | — |
| uq_co_school_enroll | completion_outcomes | school_id, enrollment_id | — | — | identity | Domain UNIQUE | — |
| idx_co_school_year | completion_outcomes | school_id, academic_year_id | enrollment_id, student_id | — | school year board | Tenancy+year list | Low overlap with uq |
| idx_co_student | completion_outcomes | student_id, school_id | — | — | student multi-enrollment | HD-39 | — |
| uq_cov_outcome_ver | completion_outcome_versions | completion_outcome_id, version_no | — | — | history | | |
| uq_cov_current_official | completion_outcome_versions | completion_outcome_id | — | is_current_official | current complete? | Partial unique | Concurrency OK with txn |
| idx_cov_school_status | completion_outcome_versions | school_id, eligibility_status, evaluated_at | — | — | ops queues | | Avoid leading status alone |
| idx_cov_policy | completion_outcome_versions | eligibility_policy_version_id | — | — | policy impact | | |
| idx_re_version | requirement_evaluations | completion_outcome_version_id, result_status | requirement_definition_version_id | — | why incomplete | Covering-ish | |
| idx_re_reqver | requirement_evaluations | requirement_definition_version_id | — | — | requirement analytics | | |
| idx_ei_set_source | evidence_items | evidence_set_id, source_type, source_id | — | — | provenance | Supports UNIQUE | |
| idx_ei_source_lookup | evidence_items | school_id, source_type, source_id | — | — | impact detection | Correction fan-out | |
| uq_ga_school_enroll | graduation_awards | school_id, enrollment_id | — | — | identity | | |
| idx_ga_school_year | graduation_awards | school_id, academic_year_id | — | — | year awards | Denorm year on award | |
| uq_gav_award_ver | graduation_award_versions | graduation_award_id, version_no | — | — | history | | |
| uq_gav_current | graduation_award_versions | graduation_award_id | — | is_current_issued | current award | Partial | |
| idx_gav_awarded_at | graduation_award_versions | school_id, awarded_at | — | lifecycle issued | reporting | | |
| idx_approval_school_status | graduation_approvals | school_id, decision_status, requested_at | — | — | pending approvals | | |
| idx_approval_version | graduation_approvals | completion_outcome_version_id | — | — | approval by completion | | |
| idx_rev_award_ver | revocation_records | graduation_award_version_id | — | — | revoke lookup | | |
| idx_super_pred | outcome_supersessions | predecessor_id, successor_id | — | — | lineage | | |
| idx_policy_school | eligibility_policies | school_id, policy_code | — | — | catalog | UNIQUE covers | Redundant if UNIQUE only — skip extra |
| idx_polver_effective | eligibility_policy_versions | policy_id, effective_from, effective_to | — | published/effective | resolve policy | | |

### Redundant index risks

- Do **not** add separate BTREE on `(school_id, enrollment_id)` if UNIQUE already exists.  
- Do **not** duplicate FK indexes already provided by UNIQUE parent keys.  
- Avoid `INCLUDE` until query plans measured — mark covering candidates as **conditional**.

### Tenancy leading column

Prefer `school_id` leading for school-wide ops lists; prefer `completion_outcome_id` / `enrollment` for point reconstructions. Mixed as above by query.
