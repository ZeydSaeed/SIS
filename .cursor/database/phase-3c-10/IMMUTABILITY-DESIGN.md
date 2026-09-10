# PHASE 3C.10 — IMMUTABILITY DESIGN

**Triggers/privileges NOT created — design only.**

## Classification

| Entity | Immutable when | Mutability allowed |
|--------|----------------|--------------------|
| eligibility_policy_versions | published/effective | draft edits only |
| requirement_definition_versions | published | draft only |
| completion_outcome_versions | lifecycle = official | lineage flags only (current, superseded_by) |
| requirement_evaluations | parent official | none |
| evidence_sets / items | parent official | none |
| graduation_approvals | decided (approved/rejected) | none |
| graduation_award_versions | issued | revoke/supersede flags + revocation_records only |
| outcome_supersessions | always after insert | none |
| revocation_records | always after insert | none |
| completion_outcomes / graduation_awards identities | insert | current_* pointer only |

## Enforcement stack (recommended combination)

1. **Append-only versioning** — corrections = new version + supersession (HD-35).  
2. **Partial UNIQUE** — one current official / one current issued.  
3. **BEFORE UPDATE/DELETE triggers** (POSTGRESQL-SPECIFIC) — reject mutation of protected columns when status ∈ immutable set.  
4. **Privilege separation** — app role: INSERT + limited UPDATE columns; no DELETE on official tables.  
5. **Application guards** — necessary but **insufficient alone** for legally/academically authoritative rows.

## Trigger design (logical — not implemented)

```text
IF OLD.lifecycle_status IN (official, issued, published)
   AND TG_OP = 'UPDATE'
   AND changing protected payload columns
THEN RAISE EXCEPTION immutable_official_record
```

Allowed updates on official completion version: `is_current_official`, `superseded_by_version_id`, `lifecycle_status` → superseded only via controlled path.

## DELETE

Default: **NEVER HARD DELETE** official academic rows. RLS + revoke DELETE privilege.

## Application-only immutability

**Rejected** as sole control for official completion, approval, award, evidence, evaluations.
