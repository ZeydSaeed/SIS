# Phase 3C.12B — Database Constraint Verification

**Authoritative source:** LIVE PostgreSQL catalog `sis` (read-only PDO query, 2026-09-10).  
Migrations consulted only for naming; catalog wins.

## Summary counts (LIVE `sis`)

| Metric | Value |
|--------|------:|
| `graduation` base tables | 14 |
| RLS + FORCE enabled | 14 |
| User tables (non-system schemas) | 85 |

## Critical uniqueness (business effects)

| Constraint / index | Definition |
|--------------------|------------|
| `completion_outcomes_school_enrollment_uq` | UNIQUE `(school_id, enrollment_id)` |
| `graduation_awards_school_enrollment_uq` | UNIQUE `(school_id, enrollment_id)` |
| `completion_outcome_versions_uq` | UNIQUE `(completion_outcome_id, version_no)` |
| `completion_outcome_versions_current_official_uidx` | UNIQUE `(completion_outcome_id) WHERE is_current_official` |
| `graduation_award_versions_uq` | UNIQUE `(graduation_award_id, version_no)` |
| `graduation_award_versions_current_issued_uidx` | UNIQUE `(graduation_award_id) WHERE is_current_issued AND lifecycle_status = 1` |
| `graduation_approvals_attempt_uq` | UNIQUE `(completion_outcome_version_id, attempt_no)` |
| `requirement_evaluations_uq` | UNIQUE `(completion_outcome_version_id, requirement_definition_version_id)` |
| `evidence_sets_version_uq` | UNIQUE `(completion_outcome_version_id)` |
| `evidence_items_source_uq` | UNIQUE `(evidence_set_id, source_type, source_id, source_version_ref)` |

## Composite tenancy FKs (sample)

- `completion_outcomes_enrollment_school_fk` → `enrollment.enrollments(id, school_id)`
- `graduation_awards_enrollment_school_fk` → same
- Child version/approval/evidence FKs include `(id, school_id)` where designed

## Triggers (LIVE)

Reject-delete on official history tables; denorm BIU on outcomes/awards; school-parent BIU on children; official/issued immutability UPDATE guards — catalog lists 21 non-internal graduation triggers.

## Idempotency store

`audit.idempotency_keys` PRIMARY KEY `(key, command_name)` (architecture support migration).

## Verdict

```text
DATABASE INTEGRITY: PASS
```

No catalog drift vs Phase 3C.12A Graduation baseline (14 / 14 RLS+FORCE).
