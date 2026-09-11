# PHASE 3C.19.4
# GET GRADUATION AWARD — AUDIT + SCOPE LOCK

**Date:** 2026-09-11  
**Phase:** 3C.19.4  
**Unit:** `GetGraduationAward` (proposed)  
**Mode:** AUDIT + SCOPE LOCK ONLY  
**Predecessors:** 3C.19.1 PASS · 3C.19.2 PASS · 3C.19.3 PASS  

```text
IMPLEMENTATION: NOT AUTHORIZED
HTTP: NOT AUTHORIZED
DATABASE MUTATION: NOT AUTHORIZED
WRITE PATH: NOT AUTHORIZED
CODE CHANGES THIS PHASE: NONE
```

---

## Executive Summary

`GetGraduationAward` is **implementation-ready for a future authorized unit**, provided the contract below is ratified.

Critical schema finding (differs from 3C.19.1–3C.19.3):

```text
Graduation Award identity is NOT keyed by completion_outcome_version.
Award identity = UNIQUE (school_id, enrollment_id) on graduation.graduation_awards.
Award versions hang under that award and pin completion_outcome_version_id.
```

Therefore the completion-outcome preferred-version rule  
(`is_current_official DESC, version_no DESC`) is **not** the primary award lookup path.

Award “current” semantics are AUTHORITATIVE via:

1. `graduation_awards.current_issued_version_id` (pointer), and/or  
2. `graduation_award_versions.is_current_issued` + partial unique where `lifecycle_status = 1`.

Revocation is persisted (`lifecycle_status = 3`, `is_current_issued = false`, `revocation_records`) — readable as facts; revoke policy not in scope.

HTTP / Permission.php remain unauthorized (HD-31-G).

---

## Scope

| In this phase | Out |
|---------------|-----|
| Read-only audit + scope lock document | Any PHP/DTO/repo/route/migration/RLS/write change |
| Lock proposed query semantics for human ratification | Implementing `GetGraduationAwardQuery` |

---

## Authoritative Schema Evidence

| Object | Evidence |
|--------|----------|
| `graduation.graduation_awards` | Migration `2026_09_10_170500_phase3c12_graduation_approvals_awards.php` |
| UNIQUE | `graduation_awards_school_enrollment_uq` = `(school_id, enrollment_id)` |
| Pointer | `current_issued_version_id` → award version (deferred FK) |
| `graduation.graduation_award_versions` | Same migration |
| UNIQUE | `(graduation_award_id, version_no)` |
| Partial UNIQUE | `graduation_award_versions_current_issued_uidx` on `(graduation_award_id)` WHERE `is_current_issued AND lifecycle_status = 1` |
| Pin to completion | `completion_outcome_version_id` FK `(id, school_id)` → `completion_outcome_versions` |
| Pin to approval | `graduation_approval_id` FK → `graduation_approvals` |
| RLS | `GraduationTenantProtection::protect` ENABLE + FORCE |
| Indexes | `graduation_awards_school_year_idx`; `graduation_award_versions_school_awarded_idx` |
| Revocation | `revocation_records` + write path sets `lifecycle_status = 3`, clears `is_current_issued` |
| Write path | `IssueAwardHandler` / `insertAwardWithVersion` creates award + v1 with `is_current_issued = true` |

COLUMN-CATALOG: `lifecycle_status` meaning issued/superseded/revoked; `honors_code` / `award_number` optional policy-open attributes (return raw if exposed).

---

## Award Cardinality

| Layer | Cardinality | Constraint |
|-------|-------------|------------|
| Award identity | **0..1 per (school_id, enrollment_id)** | `UNIQUE (school_id, enrollment_id)` |
| Award versions | **0..N per award** | `UNIQUE (graduation_award_id, version_no)` |
| Current issued version | **0..1 per award** | Partial unique WHERE `is_current_issued AND lifecycle_status = 1` |
| Attempt-based? | **No** | No `attempt_no` on awards (unlike approvals) |

**Not** 0..N awards per enrollment.  
**Is** versioned under a single enrollment-scoped award identity.

---

## Identity

| Identity | Role |
|----------|------|
| `school_id + enrollment_id` | **Authoritative Graduation/Award business identity** (HD-39; award UNIQUE) |
| `graduation_awards.id` | Stable award surrogate PK |
| `graduation_award_versions.id` | Version surrogate PK |
| `student_id` / `academic_year_id` | Denormalized on award row (AUTHORITATIVE columns; not identity) |
| `completion_outcome_id` | **Not** on awards table |
| `completion_outcome_version_id` | On **award version** only (issuance pin) |
| `graduation_approval_id` | On **award version** (issuance basis) |

Query input for consistency with 3C.19.1–3:

```text
schoolId + enrollmentId
```

Do **not** use `student_id` as tenant/Graduation identity.

---

## Preferred Version Rule

### Does `is_current_official DESC, version_no DESC` apply?

**No — not as the primary award selector.**

That rule applies to `completion_outcome_versions` (3C.19.1–3). Awards use a **different** current flag:

| Mechanism | Classification |
|-----------|----------------|
| `graduation_awards.current_issued_version_id` | AUTHORITATIVE pointer |
| `graduation_award_versions.is_current_issued` | AUTHORITATIVE flag |
| Partial unique current issued (`lifecycle_status = 1`) | AUTHORITATIVE DB rule |

### Proposed selection order (for future implementation — pending human ratification)

```text
1. If current_issued_version_id IS NOT NULL → load that version
2. Else LATERAL prefer is_current_issued DESC, version_no DESC
   (architectural continuity with “prefer current flag then version_no”;
    flag name differs from completion’s is_current_official)
```

Do **not** invent “latest by awarded_at” as primary rule.

Do **not** filter awards by preferred completion_outcome_version first — that would mis-key award identity.

---

## Relationship / Foreign Keys

### Actual graph (authoritative)

```text
enrollment (school_id + enrollment_id)
        │
        ▼
graduation_awards  (0..1)
        │
        ▼
graduation_award_versions  (0..N)
        ├──► graduation_approvals (issuance basis)
        └──► completion_outcome_versions (pin)
                    └──► completion_outcomes
```

### Incorrect assumption (rejected)

```text
completion_outcomes
  → completion_outcome_versions
  → graduation_awards     ❌ awards are NOT children of outcome versions
```

### Write-path confirmation

`IssueAward` creates award by `school_id + enrollment_id`, then version v1 pinning approval’s `completion_outcome_version_id`. Duplicate award identity → UNIQUE conflict.

---

## What GetGraduationAward Should Return

| Option | Verdict |
|--------|---------|
| All awards for enrollment | **No** — at most one award identity |
| All award versions (full history) | **Deferred** to `GetOutcomeHistory` / provenance |
| Award set for preferred completion version | **No** as primary shape — wrong grain |
| **0..1 award identity + preferred/current award version snapshot** | **YES — recommended locked shape** |

### Recommended result shape (not implemented)

```text
GraduationAwardDTO (nullable / or envelope with found=false)
  - award identity fields from graduation_awards
  - current/preferred version snapshot fields from graduation_award_versions
  - raw lifecycle_status, is_current_issued
  - pinned completion_outcome_version_id, graduation_approval_id
```

### No-award behavior (recommended)

```text
No graduation_awards row for school+enrollment
  → null / empty “no award” result
  → NOT CompletionOutcomeNotFoundException
```

Rationale: awards table does not require a completion_outcomes row FK; prior queries that throw on missing outcome are completion-centric. Award query is award-centric. (Human may ratify alternate coupling — see Findings.)

### Revoked current

If all versions revoked (`lifecycle_status = 3`, no `is_current_issued`): still return award identity + factual version snapshot per selection order (or null version fields if none). Do **not** invent `graduated=false` / StudentStatus sync.

---

## Contract Field Classification

| Field | Source | Class |
|-------|--------|-------|
| schoolId | `graduation_awards.school_id` | AUTHORITATIVE |
| enrollmentId | `graduation_awards.enrollment_id` | AUTHORITATIVE |
| awardId | `graduation_awards.id` | AUTHORITATIVE |
| studentId | `graduation_awards.student_id` | AUTHORITATIVE (denorm) |
| academicYearId | `graduation_awards.academic_year_id` | AUTHORITATIVE |
| specializationId | `graduation_awards.specialization_id` | AUTHORITATIVE (nullable) |
| currentIssuedVersionId | `graduation_awards.current_issued_version_id` | AUTHORITATIVE |
| createdBy / createdAt | award row | AUTHORITATIVE |
| awardVersionId | `graduation_award_versions.id` | AUTHORITATIVE |
| versionNo | `version_no` | AUTHORITATIVE |
| graduationApprovalId | `graduation_approval_id` | AUTHORITATIVE |
| completionOutcomeVersionId | pin on version | AUTHORITATIVE |
| lifecycleStatus | SMALLINT raw | AUTHORITATIVE |
| isCurrentIssued | BOOLEAN | AUTHORITATIVE |
| awardedAt | TIMESTAMPTZ | AUTHORITATIVE |
| issuedBy | opaque BIGINT | AUTHORITATIVE |
| awardNumber | nullable VARCHAR | AUTHORITATIVE (policy open) |
| honorsCode | nullable SMALLINT | AUTHORITATIVE (policy open; no invent labels) |
| supersedesVersionId / supersededByVersionId | lineage cols | AUTHORITATIVE |
| correlationId | version | AUTHORITATIVE |
| Status labels (“Issued”, “Graduated”) | — | **INVENTED — forbidden** |
| StudentStatus | `students.status` | **FORBIDDEN** |
| SoD validity | — | **FORBIDDEN** on read |
| Publication state | HD-38 | **OUT OF SCOPE** |
| revocation_records join | optional facts | OPTIONAL; not required for v1 contract (prefer lifecycle_status) |

---

## Security / RLS

| Layer | Requirement |
|-------|-------------|
| Application | `GraduationAuthorityPort::assertSchoolMatches(schoolId)` |
| SQL | `WHERE school_id = ? AND enrollment_id = ?` on awards |
| PostgreSQL | ENABLE + FORCE RLS on `graduation_awards` / `graduation_award_versions` / `revocation_records` |
| HTTP | **NOT AUTHORIZED** (HD-31-G) |
| Permission.php | **NOT AUTHORIZED** |
| Test | `sis_rls_tester` NOBYPASSRLS (pattern from 3C.19.1–3) |

---

## Performance

| Item | Evidence |
|------|----------|
| Primary access | UNIQUE `(school_id, enrollment_id)` on awards — ideal for query |
| Version by pointer | PK lookup via `current_issued_version_id` |
| Fallback version scan | Small N per award; UNIQUE `(award_id, version_no)` |
| Supporting indexes | `graduation_awards_school_year_idx`; `graduation_award_versions_school_awarded_idx` |
| New index for this query? | **Not required** for school+enrollment lookup |
| N+1 / SELECT * risk | Avoid by single join/lateral + explicit columns (same pattern as prior units) |

No index creation authorized in a future implementation phase without separate approval.

---

## Architecture (intended — not implemented)

```text
GetGraduationAwardQuery(schoolId, enrollmentId)
  → GetGraduationAwardHandler
      → assertSchoolMatches
      → GraduationReadRepositoryInterface::findGraduationAward(...)
  → EloquentGraduationReadRepository
  → PostgreSQL
  → GraduationAwardDTO | null
```

Extend existing read port with **one** method only when implementation is authorized.

---

## Test Plan (minimum for future implementation)

| # | Test | Intent |
|---|------|--------|
| 1 | Unit: maps award + current version fields | Field mapping |
| 2 | Unit: null when no award | No-award semantics |
| 3 | Unit: raw lifecycle_status, no labels | No policy invention |
| 4 | Unit: school mismatch before read | Authority |
| 5 | Unit: DTO has no student_status / sod_valid | Boundaries |
| 6 | PG: issued award returned for school+enrollment | Happy path |
| 7 | PG: preferred/current version via `is_current_issued` / pointer | Cardinality/current |
| 8 | PG: older award versions not preferred when current exists | Version rule |
| 9 | PG: revoked version facts (`lifecycle_status=3`) without inventing status | Revocation facts |
| 10 | PG: RLS actor School B cannot see School A award | Cross-school |
| 11 | PG: repository under School B GUC returns null for School A identity | Isolation |

---

## Explicit Non-Goals

```text
HTTP / routes / controllers / Permission.php / policies
Migrations / indexes / RLS policy changes
IssueAward / RevokeAward / PublishAward changes
StudentStatus sync or reads
SoD recalculation
Invented labels or graduation decisions
Full award-version history (GetOutcomeHistory)
EvidenceSet / Approval list embedding (unless only pinned IDs)
HD-38 publication
Implementing this query in Phase 3C.19.4
```

---

## Findings

| ID | Finding | Impact |
|----|---------|--------|
| F-01 | Award grain = enrollment UNIQUE, not completion-version child | Do not copy 19.2/19.3 join shape blindly |
| F-02 | Current award version uses `is_current_issued` / pointer, not `is_current_official` | Separate preferred-version rule |
| F-03 | 0..1 award + 0..N versions | Singleton award identity; version snapshot for “Get” |
| F-04 | Revocation persisted without hard delete | Expose raw lifecycle; optional revocation_records later |
| F-05 | No-award ≠ missing CompletionOutcome | Distinct empty semantics recommended |
| F-06 | `honors_code` / `award_number` policy-open | Return raw only |

---

## Deferred Items

| Item | Until |
|------|-------|
| Actual `GetGraduationAwardQuery` implementation | Human authorization after this audit |
| `GetOutcomeHistory` / full version lineage | Separate unit |
| `GetProvenance` | Separate unit |
| Revocation_records detail join | Optional later contract |
| HTTP + HD-31-G | Permission catalog lock |
| HD-38 publication fields | HD-38 |
| Re-issue after revoke policy | HD-36 OPEN |

---

## Implementation Readiness

| Criterion | Status |
|-----------|--------|
| Schema evidence | COMPLETE |
| Cardinality locked by UNIQUE | COMPLETE |
| Identity locked | COMPLETE |
| Preferred award-version rule | **PROPOSED** (pointer → then is_current_issued/version_no) — needs human ratification |
| Relationship corrected vs naive tree | COMPLETE |
| Security/RLS path known | COMPLETE |
| HTTP blocked | COMPLETE |
| Index gap for school+enrollment | NONE |
| Ready to implement after human approve | **YES — with ratification of no-award + version selection notes** |

```text
IMPLEMENTATION READINESS: READY WITH CONDITIONS
CONDITIONS: Human ratifies this audit’s award-centric contract
            (especially no-award semantics and award version preference order)
```

---

## Human Approval Required

Before any implementation of `GetGraduationAwardQuery`, human must approve:

1. This audit’s **award-centric** identity (`school_id + enrollment_id` → awards, not outcome-version-first).  
2. Result shape: **0..1 award + preferred award-version snapshot**.  
3. No-award → null/empty (**not** CompletionOutcomeNotFound by default).  
4. Version preference: `current_issued_version_id` then `is_current_issued`/`version_no`.  
5. Continued ban on HTTP / Permission / StudentStatus / labels / write-path changes.

---

## Mutation Check

```text
CODE MUTATION: NONE
DATABASE MUTATION: NONE
MIGRATION: NONE
SCHEMA: NONE
INDEX: NONE
RLS: NONE
HTTP: NONE
PERMISSION: NONE
WRITE PATH: NONE
```

Only deliverable:

```text
.cursor/database/phase-3c-19/04-GET-GRADUATION-AWARD-AUDIT.md
```

---

```text
PHASE 3C.19.4
GetGraduationAward

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

FINAL:
STOP
```
