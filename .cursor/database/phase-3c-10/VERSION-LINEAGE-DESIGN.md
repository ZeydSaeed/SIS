# PHASE 3C.10 — VERSION LINEAGE & SUPERSESSION / REVOCATION

## Version identity

| Layer | Identity |
|-------|----------|
| Logical subject | (school_id, enrollment_id) |
| Stable entity | completion_outcomes.id / graduation_awards.id |
| Version | *_versions.id + (parent_id, version_no) |

## Supersession representation (recommended)

**Hybrid:**

1. Self-referencing columns on version rows: `supersedes_version_id`, `superseded_by_version_id`  
2. Explicit append-only `outcome_supersessions` edges for auditability and multi-kind lineage (completion vs award)

Rationale: self-refs support fast “current chain”; lineage table supports reconstruction and prevents silent pointer-only history loss.

## Integrity rules

| Rule | Mechanism |
|------|-----------|
| No self-supersession | CHECK predecessor ≠ successor |
| No cycles | Trigger/application walk + refuse; optional deferred cycle check |
| At most one current official | Partial UNIQUE `(completion_outcome_id) WHERE is_current_official` |
| At most one current issued award | Partial UNIQUE `(graduation_award_id) WHERE is_current_issued` |
| Official V1 → candidate → human → supersede | Lifecycle CHECK + app workflow (HD-35) — do not invent bypass |
| Ambiguous current | Forbidden by partial UNIQUE |

## Revocation ≠ deletion

```text
revocation_records (append)
    ↓
award_version.lifecycle_status → revoked
    ↓
is_current_issued → false
    ↓
optional new corrected award version after new approval
```

Preserve original issued row forever.

Revocation fields live primarily on **revocation_records**; award version carries status flags only.

## Temporal model (minimum)

| Column | Meaning |
|--------|---------|
| created_at | Transaction/recorded time |
| evaluated_at / awarded_at / decided_at / revoked_at | Domain event time |
| effective_from / effective_to | Policy/requirement validity only |
| updated_at | **Avoid** on immutable versions; do not use as validity |

Not full bitemporal unless later required (HUMAN DECISION).

## Historical reconstruction

Walk: enrollment → outcome → version at time Z (by evaluated_at / created_at) → evaluations → evidence → policy version → approval → award version → supersessions → revocations.
