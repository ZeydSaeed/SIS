# PHASE 3C.19.5
# GET OUTCOME HISTORY — AUDIT + SCOPE LOCK

**Date:** 2026-09-11  
**Phase:** 3C.19.5  
**Unit:** `GetOutcomeHistory` (proposed)  
**Mode:** AUDIT + SCOPE LOCK ONLY  
**Predecessors:** 3C.19.1–4 PASS · 04A pointer-only award closure · 3C.12 schema · 3C.10 VERSION-LINEAGE / COLUMN-CATALOG · 3C.18 named-query list  

```text
MODE: AUDIT + SCOPE LOCK ONLY
IMPLEMENTATION: NOT AUTHORIZED
HTTP: NOT AUTHORIZED
DATABASE MUTATION: NOT AUTHORIZED
WRITE PATH: NOT AUTHORIZED
CODE CHANGES THIS PHASE: NONE
```

---

## 1. Executive Summary

`GetOutcomeHistory` is the cross-version historical read for Graduation. It is **not** a preferred/current snapshot query.

| Prior unit | Role | History relationship |
|------------|------|----------------------|
| 3C.19.1 GetCompletionStatus | Preferred completion version | Must **not** filter history |
| 3C.19.2 GetRequirementEvaluations | Evaluations for preferred version | History may expose **all** version-scoped evaluations |
| 3C.19.3 GetGraduationApproval | Approvals for preferred version | Cross-version approval history deferred **here** |
| 3C.19.4 GetGraduationAward | Award identity + **pointer-only** version | Full award-version lineage deferred **here** |

**Critical structural facts:**

```text
AUTHORITATIVE IDENTITY (lookup):
  school_id + enrollment_id

Completion grain:
  completion_outcomes UNIQUE (school_id, enrollment_id)
    → completion_outcome_versions 0..N
      → requirement_evaluations / evidence_sets / graduation_approvals

Award grain (PEER, not child of outcome):
  graduation_awards UNIQUE (school_id, enrollment_id)
    → graduation_award_versions 0..N
      (pins completion_outcome_version_id + graduation_approval_id)
```

**Write-path gap:** Current CQRS write path increments `version_no` and inserts evaluations/approvals/awards, but **does not populate** `supersedes_version_id` / `superseded_by_version_id`, **does not write** `outcome_supersessions`, and **does not promote** `is_current_official = true`. History can still expose persisted rows; supersession **edges** are schema-ready but currently empty in app writes.

**Implementation readiness:** **BLOCKED** — response grain (nesting vs references), history ordering ratification, and inclusion of evidence / supersessions / revocation_records remain **OPEN** for human Design Lock.

---

## 2. Scope

| In this phase | Out |
|---------------|-----|
| Read-only audit + scope lock document | Any PHP / DTO / repo / route / migration / RLS / write change |
| Lock what is schema-authoritative vs OPEN | Implementing `GetOutcomeHistoryQuery` |
| Protect 3C.19.1–4 contracts from rewrite | Changing preferred-version semantics of prior units |

---

## 3. Authoritative Schema Evidence

| Object | Migration / source |
|--------|-------------------|
| `completion_outcomes` / `completion_outcome_versions` | `2026_09_10_170300_phase3c12_graduation_completion_outcomes.php` |
| `evidence_sets` / `evidence_items` / `requirement_evaluations` | `2026_09_10_170400_phase3c12_graduation_evidence_evaluations.php` |
| `graduation_approvals` / `graduation_awards` / `graduation_award_versions` | `2026_09_10_170500_phase3c12_graduation_approvals_awards.php` |
| `outcome_supersessions` / `revocation_records` | `2026_09_10_170600_phase3c12_graduation_lineage.php` |
| Supporting indexes | `2026_09_10_170700_phase3c12_graduation_supporting_indexes.php` |
| RLS ENABLE + FORCE verify | `2026_09_10_170800_phase3c12_graduation_rls_verify.php` |
| Reject-delete + immutability triggers | `2026_09_10_170900_phase3c12_graduation_triggers.php` |
| Column meanings | `.cursor/database/phase-3c-10/COLUMN-CATALOG.md` |
| Lineage design | `.cursor/database/phase-3c-10/VERSION-LINEAGE-DESIGN.md` |
| Write path | `EloquentGraduationWriteRepository` (3C.16) |

### Key constraints (evidence)

| Constraint | Definition |
|------------|------------|
| `completion_outcomes_school_enrollment_uq` | UNIQUE `(school_id, enrollment_id)` |
| `completion_outcome_versions_uq` | UNIQUE `(completion_outcome_id, version_no)` |
| `completion_outcome_versions_current_official_uidx` | Partial UNIQUE `(completion_outcome_id) WHERE is_current_official` |
| `completion_outcome_versions_supersedes_fk` / `_superseded_by_fk` | Self-FK lineage columns (nullable) |
| `requirement_evaluations_uq` | UNIQUE `(completion_outcome_version_id, requirement_definition_version_id)` |
| `evidence_sets_version_uq` | UNIQUE `(completion_outcome_version_id)` — 0..1 set per version |
| `graduation_approvals_attempt_uq` | UNIQUE `(completion_outcome_version_id, attempt_no)` |
| `graduation_awards_school_enrollment_uq` | UNIQUE `(school_id, enrollment_id)` |
| `graduation_award_versions_uq` | UNIQUE `(graduation_award_id, version_no)` |
| `graduation_award_versions_current_issued_uidx` | Partial UNIQUE WHERE `is_current_issued AND lifecycle_status = 1` |
| Award version pins | NOT NULL `graduation_approval_id`, NOT NULL `completion_outcome_version_id` |

### RLS

All listed graduation academic tables: **ENABLE RLS + FORCE RLS** via `GraduationTenantProtection::protect` (verified `170800`).

---

## 4. Identity

```text
AUTHORITATIVE IDENTITY
──────────────────────
school_id + enrollment_id
```

**Schema evidence:**

| Entity | UNIQUE identity |
|--------|-----------------|
| `completion_outcomes` | `(school_id, enrollment_id)` |
| `graduation_awards` | `(school_id, enrollment_id)` |
| `graduation_approvals` | carries `enrollment_id` + school; UNIQUE is version+attempt |

**Not** primary history lookup keys:

```text
student_id                         — denorm only
completion_outcome_id alone        — surrogate under school+enrollment
completion_outcome_version_id      — version grain, not enrollment identity
graduation_award_id alone          — surrogate under school+enrollment
```

Consistent with HD-39 and 3C.19.1–4 query inputs — verified independently against UNIQUE constraints, not merely copied from prior units.

**Missing outcome:** If no `completion_outcomes` row exists, history of **completion** versions is empty. Award identity may still exist independently (award table has no FK to outcomes). Whether “no outcome ⇒ null vs empty envelope with optional award section” is **OPEN** for Design Lock.

---

## 5. History Cardinality

Do **not** collapse to a singleton.

| Fact set | Cardinality | Constraint |
|----------|-------------|------------|
| CompletionOutcome head | **0..1** per school+enrollment | UNIQUE |
| Completion outcome versions | **0..N** | UNIQUE `(outcome_id, version_no)` |
| Requirement evaluations | **0..N** per version | UNIQUE `(version_id, requirement_definition_version_id)` |
| Evidence sets | **0..1** per version | UNIQUE `(completion_outcome_version_id)` |
| Evidence items | **0..N** per set | UNIQUE source composite |
| Approval attempts | **0..N** per version | UNIQUE `(version_id, attempt_no)` |
| Award head | **0..1** per school+enrollment | UNIQUE |
| Award versions | **0..N** per award | UNIQUE `(award_id, version_no)` |
| Revocation records | **0..N** per award version | No UNIQUE (append-only possible) |
| Outcome supersession edges | **0..N** | Separate audit table |

Forbidden substitutes for history:

```text
MAX(id)
MAX(created_at)
MAX(version_no)          — as sole “history”
is_current_official only — preferred read, not history
current_issued_version_id only — award pointer (3C.19.4), not full lineage
```

---

## 6. Version / Lineage Ordering

### Candidates

| Candidate | Schema status | Write-path status |
|-----------|---------------|-------------------|
| `version_no` | UNIQUE with parent; INTEGER ≥ 1 | Evaluate: `max(version_no)+1` (monotonic insert) |
| `created_at` | Present; not UNIQUE ordered | Set on insert; not proven sole history order |
| `evaluated_at` / `awarded_at` | Domain event times; nullable on completion eval | Not UNIQUE; not required monotonic |
| `supersedes_version_id` / `superseded_by_version_id` | Nullable self-FKs | **Not written** by current handlers |
| `outcome_supersessions` | Append-only edges (kind 1=completion, 2=award) | **Not written** by current handlers |
| `attempt_no` | UNIQUE with version (approvals) | Written by ApproveGraduation |

### Locked vs open

```text
LISTING SEQUENCE WITHIN A PARENT (completion versions / award versions):
  version_no ASC
  — SCHEMA-COMPATIBLE + WRITE-PATH MONOTONIC
  — recommended candidate for Design Lock ratification

SUPERSESSION GRAPH TRAVERSAL AS PRIMARY HISTORY ORDER:
  HISTORY ORDER (SUPERSESSION):
  NOT AUTHORITATIVELY DEFINED
  — columns/table exist; app writes do not populate them today

CROSS-STREAM TIMELINE (mix completion versions + award versions + approvals by wall-clock):
  NOT AUTHORITATIVELY DEFINED
  — different parents; no single ordered event log table
```

**Do not invent** `ORDER BY created_at DESC` or supersession walk as the locked primary rule without human ratification.

**Approvals within a version:** UNIQUE `(completion_outcome_version_id, attempt_no)` supports `attempt_no ASC` listing (same structural basis as 3C.19.3) — for **that version’s** attempts only.

---

## 7. Current vs Historical Semantics

```text
CURRENT / PREFERRED READ
        ≠
HISTORICAL READ
```

| Mechanism | Preferred units (19.1–3) | History (this unit) |
|-----------|-------------------------|---------------------|
| `ORDER BY is_current_official DESC, version_no DESC LIMIT 1` | Used | **FORBIDDEN as filter that drops other versions** |
| `is_current_official` / `is_current_issued` | Selection aids | **Persisted facts** on each row — expose, do not filter-out |
| Pointers (`current_official_version_id`, `current_issued_version_id`) | Preferred snapshot | Optional identity fields on head — **not** the only version returned |

History must return **all** persisted versions for the enrollment-scoped heads (subject to final grain lock), including non-current, superseded, and revoked rows.

---

## 8. Supersession

### Persisted representation (AUTHORITATIVE storage)

**Hybrid (3C.10 design + 3C.12 DDL):**

1. Self-refs on `completion_outcome_versions` and `graduation_award_versions`:  
   `supersedes_version_id`, `superseded_by_version_id`
2. Append-only `outcome_supersessions` with `lineage_kind` ∈ {1=completion, 2=award} and predecessor/successor FKs

### Application write path (3C.16)

| Operation | Supersession written? |
|-----------|----------------------|
| EvaluateCompletion | **No** — new version only; `is_current_official=false` |
| IssueAward | **No** — v1 + pointer |
| RevokeAward | **No** — status flags + `revocation_records` only |
| PublishAward | Gated / not promoting official |

### History implication

- Expose supersession columns / edges **as stored** (often NULL / empty today).
- Do **not** invent or repair chains.
- Do **not** treat empty supersession as “no history” — version rows still exist via `version_no`.

**Inclusion of `outcome_supersessions` in GetOutcomeHistory response:** **OPEN** (GetProvenance may own edge audit — see Deferred).

---

## 9. Revocation

### Award revocation (AUTHORITATIVE)

| Fact | Source |
|------|--------|
| Soft revoke | `graduation_award_versions.lifecycle_status = 3` |
| Clears current issued | `is_current_issued = false` |
| Append record | `revocation_records` (award-version scoped) |
| Pointer | `graduation_awards.current_issued_version_id` **not cleared** by current RevokeAward (04A) |
| Hard delete | Forbidden (reject-delete triggers) |

COLUMN-CATALOG award `lifecycle_status`: issued / superseded / revoked.

### Completion versions

No `revocation_records` for completion. Lifecycle SMALLINT (candidate / official / superseded per COLUMN-CATALOG). Immutability trigger when `lifecycle_status = 2` (official) blocks payload rewrite.

### History implication

Return raw `lifecycle_status`, `is_current_issued`, and optionally revocation row facts. **No** synthetic `isActive` / `isGraduated`.

**Whether GetOutcomeHistory embeds `revocation_records` or only version flags:** **OPEN** (full provenance may be GetProvenance).

---

## 10. Approval Relationship

```text
completion_outcome_versions.id
        ↓ FK
graduation_approvals.completion_outcome_version_id
        + attempt_no
        UNIQUE (completion_outcome_version_id, attempt_no)
```

Also stores `school_id` + `enrollment_id` (denorm / scope).

### Contract grain — OPEN

| Option | Notes |
|--------|-------|
| A. Nest all approval attempts under each completion version | Mirrors 3C.19.3 shape per version; larger payload |
| B. Return approval id + attempt_no refs only | Thin history; details via GetGraduationApproval-style follow-up |
| C. Flat list of approvals for enrollment across versions | Needs explicit version id on each row |

**Do not invent** “current approval only” for history.

**Recommendation (not locked):** Option A or C with raw `decision_status` — human must ratify before implementation.

---

## 11. Award Relationship

### Correct model (LOCKED by schema)

```text
WRONG:
  completion_outcomes → graduation_awards   ❌ no such FK

CORRECT:
  enrollment (school_id + enrollment_id)
        ├── completion_outcomes (0..1)
        │         └── completion_outcome_versions (0..N)
        │                   ↑ pin
        └── graduation_awards (0..1)
                  └── graduation_award_versions (0..N)
                            ├── completion_outcome_version_id (NOT NULL pin)
                            └── graduation_approval_id (NOT NULL pin)
```

Awards are **enrollment-scoped peers**, not children of completion outcomes. Award versions **reference** a completion version and an approval.

### Contract grain — OPEN

| Option | Notes |
|--------|-------|
| Include award head + **all** award versions as sibling section under enrollment history | Matches “Outcome History” walk in 3C.10 § Historical reconstruction |
| Defer full award lineage entirely to a future award-history unit / GetProvenance | GetGraduationAward already covers pointer-only current snapshot |
| Nest award versions under the pinned completion version | Convenient but **mis-models** award identity (0..1 award still enrollment-owned) |

**Recommendation (not locked):** Sibling section under enrollment — never claim awards are outcome children.

Pointer-only rule from 04A applies to **GetGraduationAward**, not to history listing of all award versions.

---

## 12. Contract Field Classification

### AUTHORITATIVE (safe to expose raw)

| Area | Fields |
|------|--------|
| Outcome head | schoolId, enrollmentId, completionOutcomeId, studentId, academicYearId, specializationId, currentOfficialVersionId, createdAt, createdBy |
| Completion versions | versionId, versionNo, lifecycleStatus, evaluationStatus, eligibilityStatus, isCurrentOfficial, policy/calc pins, fingerprints, evaluatedAt, supersedesVersionId, supersededByVersionId, correlationId, createdAt |
| Requirement evaluations | id, versionId, requirementDefinitionVersionId, resultStatus, evaluatedAt, notesRef |
| Approvals | id, versionId, attemptNo, decisionStatus, requested/decided actors & times, reason_ref, correlationId |
| Award head | awardId, currentIssuedVersionId, denorm identity cols |
| Award versions | versionId, versionNo, approvalId, completionOutcomeVersionId, lifecycleStatus, isCurrentIssued, awardedAt, issuedBy, awardNumber, honorsCode, supersedes*, correlationId |
| Revocation (if included) | revokedAt, revokedBy, reason_ref, correlationId |
| Supersession edges (if included) | lineageKind, predecessor/successor ids |

### FORBIDDEN / INVENTED

```text
isActive, isGraduated, isCurrentOutcome, isValid, isValidSoD
awardStatusLabel, graduationStatus, StudentStatus
synthetic reconstructed missing versions
repaired supersession chains
recalculated SoD / eligibility decisions
```

### OPEN inclusion

```text
evidence_sets / evidence_items
outcome_supersessions rows
revocation_records rows (vs flags only)
full nested evaluations vs evaluation counts/ids
```

---

## 13. Security / RLS

| Layer | Requirement |
|-------|-------------|
| Application | `GraduationAuthorityPort::assertSchoolMatches(schoolId)` (same as 3C.19.1–4) |
| SQL | Scope heads by `school_id` + `enrollment_id`; child rows by parent + `school_id` |
| PostgreSQL | Existing ENABLE + FORCE RLS — **do not modify** |
| HTTP / Permission.php | **NOT AUTHORIZED** (HD-31-G) |
| Test pattern | `sis_rls_tester` NOBYPASSRLS; School B cannot see School A history |

Do not rely on application auth alone.

---

## 14. Performance

### Existing access aids

| Path | Index / constraint |
|------|-------------------|
| Outcome by school+enrollment | UNIQUE `completion_outcomes_school_enrollment_uq` |
| Versions by outcome | UNIQUE `(completion_outcome_id, version_no)` |
| Evaluations by version | `requirement_evaluations_version_status_idx` |
| Approvals by version | `graduation_approvals_version_idx` |
| Award by school+enrollment | UNIQUE `graduation_awards_school_enrollment_uq` |
| Award versions by award | UNIQUE `(graduation_award_id, version_no)` |
| Revocations by award version | `revocation_records_award_version_idx` |
| Status / awarded filters | school_status / school_awarded supporting indexes |

### Findings (report only — no index creation)

| ID | Finding |
|----|---------|
| PERF-01 | History may join many child tables — prefer few queries keyed by version ids; avoid N+1 |
| PERF-02 | No dedicated `(completion_outcome_id) INCLUDE …` covering history payload — UNIQUE on version_no is sufficient for ordered list at expected N |
| PERF-03 | If evidence_items included at scale, revisit `evidence_items_source_lookup_idx` usage — **finding only** |
| PERF-04 | Do **not** CREATE INDEX in implementation phase without separate database-change authorization |

---

## 15. Proposed DTO Shape

**Not implemented.** Candidate for human ratification:

```text
OutcomeHistoryDTO
  schoolId
  enrollmentId
  completionOutcome: ?CompletionOutcomeHeadDTO
  completionVersions: list<CompletionVersionHistoryItemDTO>
      // each: version raw facts + is_current_official as stored
      // OPEN: nested evaluations[]
      // OPEN: nested approvals[]  OR approvalRefs[]
      // OPEN: evidenceSet?
  award: ?AwardHeadDTO          // peer — NOT under a version
  awardVersions: list<AwardVersionHistoryItemDTO>
      // pins: completionOutcomeVersionId, graduationApprovalId
      // OPEN: nested revocationRecords[]
  // OPEN: supersessionEdges: list<OutcomeSupersessionDTO>
```

**Missing outcome:** **OPEN** — `null` entire DTO vs empty lists with optional award-only section.

---

## 16. Proposed Repository Contract

```text
GetOutcomeHistoryQuery(schoolId, enrollmentId)
        ↓
GetOutcomeHistoryHandler
        → assertSchoolMatches(schoolId)
        → GraduationReadRepositoryInterface::findOutcomeHistory(...)
        ↓
EloquentGraduationReadRepository
        ↓
PostgreSQL (RLS preserved)
        ↓
?OutcomeHistoryDTO
```

Extend existing read port with **one** method when authorized. Do not alter 3C.19.1–4 method semantics.

---

## 17. Future Test Plan

Minimum tests **when implementation is authorized** (do not write now):

1. School + enrollment isolation (authority + SQL scope)  
2. Multiple completion outcome versions all returned  
3. Historical / non-current versions **not** filtered out by `is_current_official`  
4. Current official (if any) still identifiable via persisted `is_current_official` / pointer facts  
5. Supersession columns/edges returned as stored (including NULL/empty) — no repair  
6. Revoked award version facts (`lifecycle_status=3`, `is_current_issued=false`) retained in history  
7. Approval attempts across **multiple** completion versions (relationship correct)  
8. Award versions as enrollment peer + pin fields; **not** modeled as outcome children  
9. No synthetic StudentStatus / SoD / invented labels  
10. RLS: School B actor cannot see School A history; repo returns null/empty for cross-school  

Also: negative test that history ≠ 19.1 preferred LIMIT 1 behavior.

---

## 18. Explicit Non-Goals

```text
HTTP / routes / controllers / Permission.php / policies
Migrations / indexes / RLS policy changes
Write handlers / IssueAward / RevokeAward / PublishAward
StudentStatus sync or reads
SoD recalculation
GetProvenance implementation
Invented status labels or reconstructed missing versions
Repair of stale award pointers (04A)
Modification of passed units 3C.19.1 / 19.2 / 19.3 / 19.4
```

---

## 19. Findings

| ID | Finding | Severity | Disposition |
|----|---------|----------|-------------|
| F-01 | Preferred-version ORDER BY must not drive history | HIGH | Locked distinction in §7 |
| F-02 | Awards are enrollment peers, not outcome children | HIGH | Locked in §11 |
| F-03 | Supersession columns/table unused by current write path | MED | Expose raw; no invent |
| F-04 | Response nesting grain (approvals / evaluations / evidence / revocations) **OPEN** | HIGH | Blocks implementation |
| F-05 | Primary history ORDER not fully locked (version_no ASC candidate vs supersession) | HIGH | Blocks until Design Lock |
| F-06 | No-outcome vs award-only envelope **OPEN** | MED | Design Lock |
| F-07 | GetProvenance boundary vs history (edges, revocation detail, evidence) **OPEN** | MED | Deferred |
| F-08 | Official promotion path absent — many versions may have `is_current_official=false` | INFO | History still lists all versions |

---

## 20. Deferred Items

```text
GetProvenance (deep evidence / supersession edge audit / actor provenance)
HTTP + HD-31-G permissions
Write-path supersession population / official promotion (HD-35 / Publish)
Evidence payload policy for history
Index creation (PERF findings only)
Any change to GetGraduationAward pointer-only contract
```

---

## 21. Implementation Readiness

```text
IMPLEMENTATION READINESS: BLOCKED
```

| Prerequisite | Status |
|--------------|--------|
| Authoritative lookup identity | **LOCKED** — `school_id + enrollment_id` |
| Award ≠ outcome-child relationship | **LOCKED** |
| Current ≠ historical semantics | **LOCKED** |
| Cardinality 0..N versions | **LOCKED** |
| History ORDER (primary) | **NOT FULLY AUTHORITATIVELY DEFINED** — `version_no ASC` candidate only |
| Supersession as history backbone | **NOT AUTHORITATIVELY DEFINED** (unpopulated writes) |
| Approval nesting vs refs | **OPEN** |
| Award section inclusion shape | **OPEN** |
| Evidence / revocation_records / outcome_supersessions inclusion | **OPEN** |
| Missing-outcome return contract | **OPEN** |

Do **not** implement until humans ratify a Design Lock covering F-04, F-05, F-06 (and preferably F-07 boundary).

---

## 22. Human Approval Required

```text
HUMAN APPROVAL REQUIRED: YES

Before UNIT-3C19-05 implementation authorization, lock at least:

1. HISTORY ORDER:
   - Ratify version_no ASC for listing within parent, OR
   - Explicitly leave supersession-primary as out-of-scope until writes populate edges

2. RESPONSE GRAIN:
   - Approvals: nest vs reference vs flat
   - Evaluations: nest vs omit (call GetRequirementEvaluations per version later)
   - Award: sibling full version list vs omit
   - Evidence / revocation_records / outcome_supersessions: include vs GetProvenance

3. MISSING OUTCOME:
   - null vs empty envelope (possibly award-only)

Then issue explicit:
  APPROVED — IMPLEMENT GetOutcomeHistoryQuery
```

---

## 23. Mutation Check

```text
CODE MUTATION: NONE
DATABASE MUTATION: NONE
HTTP: NONE
WRITE PATH: NONE
RLS: NONE
INDEX: NONE
TESTS: NONE
DTO / REPOSITORY: NONE

Deliverable only:
.cursor/database/phase-3c-19/05-GET-OUTCOME-HISTORY-AUDIT.md
```

---

```text
PHASE 3C.19.5
GetOutcomeHistory

MODE:
AUDIT + SCOPE LOCK ONLY

IMPLEMENTATION:
NOT AUTHORIZED

HTTP:
NOT AUTHORIZED

DATABASE MUTATION:
NOT AUTHORIZED

WRITE PATH:
NOT AUTHORIZED

NEXT UNIT:
NOT AUTHORIZED

FINAL:
STOP
```
