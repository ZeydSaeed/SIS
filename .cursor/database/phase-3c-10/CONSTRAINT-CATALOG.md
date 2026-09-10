# PHASE 3C.10 — CONSTRAINT CATALOG

**DDL NOT EXECUTED**

| Constraint | Table | Type | Definition (logical) | Protects | Notes |
|------------|-------|------|----------------------|----------|-------|
| pk_* | all | PK | id IDENTITY | Row identity | |
| uq_completion_outcome_enrollment | completion_outcomes | UNIQUE | (school_id, enrollment_id) | One outcome per enrollment/school | HD-39 |
| uq_graduation_award_enrollment | graduation_awards | UNIQUE | (school_id, enrollment_id) | One award identity per enrollment | |
| uq_policy_code | eligibility_policies | UNIQUE | (school_id, policy_code) | Policy family | |
| uq_policy_version | eligibility_policy_versions | UNIQUE | (policy_id, version_no) | Monotonic versions | |
| uq_req_def_code | requirement_definitions | UNIQUE | (policy_id, requirement_code) | | |
| uq_req_def_version | requirement_definition_versions | UNIQUE | (requirement_definition_id, version_no) | | |
| uq_cov_version | completion_outcome_versions | UNIQUE | (completion_outcome_id, version_no) | | |
| uq_gav_version | graduation_award_versions | UNIQUE | (graduation_award_id, version_no) | | |
| uq_approval_attempt | graduation_approvals | UNIQUE | (completion_outcome_version_id, attempt_no) | | |
| uq_evidence_source | evidence_items | UNIQUE | (evidence_set_id, source_type, source_id, COALESCE(source_version_ref,'')) | Duplicate evidence | Exact expression TBD in impl |
| fk_co_enrollment_school | completion_outcomes | FK composite | (enrollment_id, school_id)→enrollments(id,school_id) RESTRICT | Cross-school | PG-SPECIFIC pattern LIVE |
| fk_ga_enrollment_school | graduation_awards | FK composite | same | Cross-school | |
| fk_approval_enrollment_school | graduation_approvals | FK composite | same | Cross-school | |
| fk_cov_outcome | completion_outcome_versions | FK | outcome_id→outcomes RESTRICT | Orphans | |
| fk_cov_policy_ver | completion_outcome_versions | FK | policy version RESTRICT | Provenance | Same school CHECK/trigger |
| fk_re_cov | requirement_evaluations | FK | version RESTRICT | | |
| fk_re_reqver | requirement_evaluations | FK | req def version RESTRICT | | |
| fk_es_cov | evidence_sets | FK UNIQUE | version_id 1:1 | | |
| fk_ei_es | evidence_items | FK | set RESTRICT | | |
| fk_approval_cov | graduation_approvals | FK | completion version RESTRICT | | |
| fk_gav_award | graduation_award_versions | FK | award RESTRICT | | |
| fk_gav_approval | graduation_award_versions | FK | approval RESTRICT | | |
| fk_rev_gav | revocation_records | FK | award version RESTRICT | | |
| fk_super_endpoints | outcome_supersessions | FK | pred/succ version ids RESTRICT | Lineage | |
| chk_version_no_pos | *_versions | CHECK | version_no ≥ 1 | | |
| chk_exclusion_reason | evidence_items | CHECK | included ⇒ reason NULL; excluded ⇒ reason NOT NULL | NULL semantics | |
| chk_decided_fields | graduation_approvals | CHECK | decided statuses require decided_at | | |
| chk_no_self_super | outcome_supersessions | CHECK | predecessor_id ≠ successor_id | Cycles basic | |
| excl_policy_effective | eligibility_policy_versions | EXCLUSION? | overlapping effective ranges per policy | Temporal | **HUMAN DECISION** — optional PG exclusion |

### Partial UNIQUE (indexes doubling as constraints)

| Name | Table | Predicate | Protects |
|------|-------|-----------|----------|
| uq_one_current_official_completion | completion_outcome_versions | is_current_official AND school_id | One official current per outcome — include outcome_id in unique |
| uq_one_current_issued_award | graduation_award_versions | is_current_issued AND NOT revoked | One current issued award version |

Exact partial UNIQUE columns: `(completion_outcome_id) WHERE is_current_official` and `(graduation_award_id) WHERE is_current_issued AND lifecycle_status = issued`.

### ON DELETE

All critical FKs: **RESTRICT**. No CASCADE on historical academic tables.
