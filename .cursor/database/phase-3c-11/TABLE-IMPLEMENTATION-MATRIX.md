# PHASE 3C.11 — TABLE IMPLEMENTATION MATRIX

**Source:** 3C.10 TABLE/COLUMN/CONSTRAINT catalogs + 3C.10A closures.  
**No columns invented beyond finalized design.**

Common to all school-scoped tables unless noted:

- Schema: `graduation`
- PK: `BIGINT GENERATED ALWAYS AS IDENTITY`
- RLS: YES + FORCE
- ON DELETE FKs: RESTRICT
- ON UPDATE: RESTRICT
- Partition: NO
- Hard delete official: FORBIDDEN

---

## eligibility_policies

| Field | Plan |
|-------|------|
| Purpose / Authority | Policy family SSOT |
| Domain identity | UNIQUE `(school_id, policy_code)` |
| school_id | YES NOT NULL FK schools |
| enrollment_id | NO |
| Write / Read | Admin catalog / resolve policy |
| 20y growth | Low |
| Watchlist partition | No |

## eligibility_policy_versions

| Field | Plan |
|-------|------|
| Purpose | Published/draft policy shell |
| Domain identity | `(policy_id, version_no)` |
| JSONB | nullable content_payload — no invented schema |
| Timestamps | created_at; effective_from/to; published_at |
| Actors | created_by opaque BIGINT |
| Partial UNIQUE | one current-effective per policy |
| Immutability | published payload immutable |
| Growth | Low |

## requirement_definitions / requirement_definition_versions

| Field | Plan |
|-------|------|
| Purpose | Requirement family + versioned rule shell |
| Domain identity | `(policy_id, requirement_code)` / `(def_id, version_no)` |
| JSONB | nullable rule_payload |
| unit_kind | SMALLINT extensible — **no seeded business list** |
| Immutability | published versions |

## completion_outcomes

| Field | Plan |
|-------|------|
| Purpose | Stable completion identity SSOT |
| Domain identity | UNIQUE `(school_id, enrollment_id)` |
| Composite FK | `(enrollment_id, school_id)` → enrollments |
| Denorm | student_id, academic_year_id, specialization_id |
| Pointer | current_official_version_id (FK after versions) |
| Mutable | pointer only |
| Growth | ~enrollments |

## completion_outcome_versions

| Field | Plan |
|-------|------|
| Purpose | Evaluation/official completion SSOT |
| Domain identity | `(completion_outcome_id, version_no)` |
| Partial UNIQUE | `is_current_official` |
| Lineage | supersedes_version_id / superseded_by_version_id |
| Status | lifecycle_status, evaluation_status, eligibility_status SMALLINT |
| No GPA column required | HD-22 |
| Fingerprints | optional; ≠ idempotency |
| Immutability | official payload frozen |
| Growth | Med — no partition |

## evidence_sets

| Field | Plan |
|-------|------|
| Purpose | 1:1 evidence bundle per completion version |
| PK | separate IDENTITY + UNIQUE(version_id) D-3C10-003 |
| Immutability | with parent official |

## evidence_items

| Field | Plan |
|-------|------|
| Purpose | Typed source refs only |
| UNIQUE | set + source_type + source_id + version_ref |
| CHECK | exclusion_reason nullability vs inclusion_status |
| Growth | Highest — **partition WATCHLIST** |
| Write | batch with evaluation |

## requirement_evaluations

| Field | Plan |
|-------|------|
| Purpose | Per-requirement result for a completion version |
| result_status | explicit SMALLINT (missing ≠ satisfied) |
| Growth | High — watchlist |
| Immutability | if parent official |

## graduation_approvals

| Field | Plan |
|-------|------|
| Purpose | Human approval SSOT |
| Domain identity | `(completion_outcome_version_id, attempt_no)` |
| Composite FK | enrollment+school |
| Actors | requested_by / decided_by opaque |
| Immutability | when decided |
| POLICY DEPENDENCY | roles/hierarchy — DO NOT invent |

## graduation_awards

| Field | Plan |
|-------|------|
| Purpose | Stable award identity |
| Domain identity | UNIQUE `(school_id, enrollment_id)` |
| Composite FK | enrollment+school |
| Denorm | student_id, academic_year_id as designed |

## graduation_award_versions

| Field | Plan |
|-------|------|
| Purpose | Issued award SSOT |
| Domain identity | `(award_id, version_no)` |
| Partial UNIQUE | `is_current_issued` |
| FK | approval_id, completion_outcome_version_id |
| Optional | award_number, honors_code — **POLICY INPUT, nullable** |
| Immutability | issued payload; flags for revoke/supersede only |

## outcome_supersessions

| Field | Plan |
|-------|------|
| Purpose | Append-only lineage edges |
| CHECK | no self-super |
| DELETE | NEVER |

## revocation_records

| Field | Plan |
|-------|------|
| Purpose | Append-only revoke events — ≠ deletion |
| Fields | revoked_at, revoked_by opaque, reason_ref opaque, correlation_id |
| DELETE | NEVER |

---

## Constraints plan (implementation order)

1. PKs + NOT NULLs at CREATE  
2. UNIQUE domain identities  
3. FKs RESTRICT (composite after unique parent indexes verified)  
4. CHECKs (version_no≥1, exclusion reason, decided_at, no self-super)  
5. Partial UNIQUEs for current official/issued/effective  
6. **No EXCLUSION** at launch (D-3C10-002)  

## Audit requirements

All authoritative mutations: correlation_id where designed; outbox stage; existing audit pipeline — no second audit SSOT.
