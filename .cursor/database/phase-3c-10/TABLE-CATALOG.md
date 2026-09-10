# PHASE 3C.10 — TABLE CATALOG

**Schema:** `graduation`  
**DDL:** NOT EXECUTED  

---

| Table | Schema | Purpose | SSOT / Projection | Scope | Identity | PK | Versioned | Immutable | RLS | Partitioned | Expected Growth | Retention | Delete Policy |
|-------|--------|---------|-------------------|-------|----------|----|-----------|-----------|-----|-------------|-----------------|-----------|---------------|
| eligibility_policies | graduation | Policy family | SSOT catalog family | school | school+policy_code | BIGINT identity | via versions | metadata only | YES | NO | Low | Permanent catalog | NEVER hard-delete if versions exist |
| eligibility_policy_versions | graduation | Published policy snapshot | SSOT policy content pin | school | policy+version_no | BIGINT identity | YES | YES when published | YES | NO | Low | Permanent | NEVER DELETE published |
| requirement_definitions | graduation | Requirement under policy | SSOT definition family | school | policy+req_code | BIGINT identity | via versions | metadata | YES | NO | Low–med | Permanent | RESTRICT |
| requirement_definition_versions | graduation | Requirement/rule shell | SSOT rule pin | school | def+version_no | BIGINT identity | YES | YES when published | YES | NO | Low–med | Permanent | NEVER DELETE published |
| completion_outcomes | graduation | Stable completion identity | SSOT outcome identity | school+enrollment | (school_id, enrollment_id) | BIGINT identity | via versions | identity stable | YES | NO | ~enrollments | Permanent | NEVER DELETE if versions |
| completion_outcome_versions | graduation | Evaluation/official completion | SSOT derived completion | school | outcome+version_no | BIGINT identity | YES | YES if official | YES | NO initially | Med | Permanent official | NEVER hard-delete official |
| requirement_evaluations | graduation | Per-requirement result | SSOT derived for version | school | version+req_ver | BIGINT identity | frozen w/ parent | YES if parent official | YES | NO | Higher | With parent | NEVER if official parent |
| evidence_sets | graduation | Evidence bundle | SSOT set for version | school | 1:1 version | BIGINT or =version_id | frozen | YES if official | YES | NO | Med | With parent | NEVER if official |
| evidence_items | graduation | Typed evidence refs | Reference only — not grade SSOT | school | set+source key | BIGINT identity | frozen | YES if official | YES | NO | Higher | With parent | NEVER if official |
| graduation_approvals | graduation | Human approval decisions | SSOT approval | school | version+attempt | BIGINT identity | attempts | YES when decided | YES | NO | Low–med | Permanent | NEVER DELETE decided |
| graduation_awards | graduation | Stable award identity | SSOT award identity | school+enrollment | (school_id, enrollment_id) | BIGINT identity | via versions | identity stable | YES | NO | ~completions | Permanent | NEVER if versions |
| graduation_award_versions | graduation | Issued award snapshot | SSOT official award | school | award+version_no | BIGINT identity | YES | YES if issued | YES | NO | Low–med | Permanent | NEVER hard-delete issued |
| outcome_supersessions | graduation | Lineage edges | SSOT lineage | school | pred+succ+kind | BIGINT identity | append | append-only | YES | NO | Low | Permanent | NEVER DELETE |
| revocation_records | graduation | Authorized revoke events | SSOT revoke event | school | award_ver+event | BIGINT identity | append | append-only | YES | NO | Low | Permanent | NEVER DELETE |

**Projections (no table here):** StudentStatus::Graduated — rebuild from current non-revoked award version via outbox.
