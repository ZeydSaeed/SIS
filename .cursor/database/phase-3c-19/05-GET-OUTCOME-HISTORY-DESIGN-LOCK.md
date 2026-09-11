# PHASE 3C.19.5
# GET OUTCOME HISTORY — DESIGN LOCK

**Date:** 2026-09-11  
**Phase:** 3C.19.5  
**Unit:** `GetOutcomeHistory` (proposed)  
**Mode:** DESIGN LOCK ONLY  
**Predecessors:** `05-GET-OUTCOME-HISTORY-AUDIT.md` (AUDIT + SCOPE LOCK PASS) · 3C.19.1–4 PASS · 04A pointer-only award closure  

```text
MODE: DESIGN LOCK ONLY
IMPLEMENTATION: NOT AUTHORIZED
HTTP: NOT AUTHORIZED
DATABASE MUTATION: NOT AUTHORIZED
WRITE PATH: NOT AUTHORIZED
CODE CHANGES THIS PHASE: NONE
```

---

## 1. Executive Decision

The three OPEN items that blocked implementation readiness in the audit are hereby **LOCKED**:

| Design Lock | Decision |
|-------------|----------|
| **DL-1 Primary History Order** | Within each parent: `version_no ASC`; approvals within a completion version: `attempt_no ASC`. No `created_at` / `MAX(id)` / supersession-graph primary order. |
| **DL-2 Response Grain** | Historical aggregate with **bounded detail**: all persisted completion versions + lightweight evaluation/approval refs; award as **enrollment peer** with all award versions; no full evidence graph; supersession as persisted column refs only. |
| **DL-3 Missing Outcome Envelope** | Missing `completion_outcomes` ⇒ `completionOutcome = null` and empty completion versions; **award may still be returned**. Do not null the entire DTO solely because completion is absent. Do not throw `CompletionOutcomeNotFoundException`. |

```text
DESIGN: LOCKED
IMPLEMENTATION READINESS: STILL NOT AUTHORIZED
  — human must issue explicit APPROVED — IMPLEMENT GetOutcomeHistoryQuery
```

This document does **not** authorize implementation, HTTP, tests, schema, RLS, indexes, or write-path changes.

---

## 2. Locked Identity

```text
AUTHORITATIVE LOOKUP IDENTITY
─────────────────────────────
school_id + enrollment_id
```

| Entity | UNIQUE identity | Role in history |
|--------|-----------------|-----------------|
| `completion_outcomes` | `(school_id, enrollment_id)` | 0..1 completion head |
| `graduation_awards` | `(school_id, enrollment_id)` | 0..1 award head (peer) |
| `graduation_approvals` | `(completion_outcome_version_id, attempt_no)` | Per-version attempts |

**Not** primary history lookup keys:

```text
student_id
completion_outcome_id alone
completion_outcome_version_id alone
graduation_award_id alone
```

Conceptual contract:

```text
GetOutcomeHistory(schoolId, enrollmentId)
  → enrollment-scoped historical aggregate
```

---

## 3. Locked Historical Semantics

```text
CURRENT / PREFERRED READ
        ≠
HISTORICAL READ
```

| Mechanism | Preferred units (3C.19.1–3) | GetOutcomeHistory |
|-----------|----------------------------|-------------------|
| `is_current_official` filter / ORDER that drops other versions | Used for preferred selection | **FORBIDDEN** |
| `is_current_official` / `is_current_issued` as row facts | Selection aids | **Expose as stored** — never filter history out |
| `current_official_version_id` / `current_issued_version_id` | Preferred snapshot pointers | Head identity facts only — **do not collapse** history |

**LOCKED:**

```text
History is NOT preferred/current selection.
All persisted completion outcome versions remain candidates for history.
All persisted graduation award versions remain candidates for history.
```

Forbidden substitutes for “history”:

```text
MAX(id)
MAX(created_at)
MAX(version_no) as sole “history item”
is_current_official only
current_issued_version_id only
```

---

## 4. Locked History Ordering

### DL-1 — Primary history order (LOCKED)

| Collection | Primary ORDER BY | Authority |
|------------|------------------|-----------|
| Completion outcome versions | `version_no ASC` | `UNIQUE (completion_outcome_id, version_no)` |
| Graduation award versions | `version_no ASC` | `UNIQUE (graduation_award_id, version_no)` |
| Approval attempts within each completion version | `attempt_no ASC` | `UNIQUE (completion_outcome_version_id, attempt_no)` |

### Rationale

`version_no` / `attempt_no` are the authoritative persisted sequences within their parents. Write path increments `version_no` monotonically on insert; approvals write `attempt_no` under the version.

### Explicitly NOT primary history order

```text
created_at
evaluated_at
awarded_at
MAX(id)
MAX(created_at)
MAX(version_no)          — as a selector that collapses history
supersedes_version_id
superseded_by_version_id
outcome_supersessions graph traversal
```

Supersession columns/edges remain **persisted lineage/provenance facts only**. Their existence does **not** authorize graph traversal as the primary ordering mechanism.

### Cross-stream timeline

Mixing completion versions, award versions, and approvals into a single wall-clock event log is **NOT** defined by this Design Lock. History remains **sectioned** (completion vs award) with per-parent `version_no` / `attempt_no` order.

---

## 5. Locked Response Grain

### DL-2 — Historical aggregate with bounded detail (LOCKED)

```text
OutcomeHistoryDTO
├── schoolId
├── enrollmentId
│
├── completionOutcome          -- 0..1 head; null if no outcome row
│   ├── identity / head facts
│   └── versions[]             -- 0..N, version_no ASC
│       ├── raw persisted version facts
│       ├── evaluationRefs[]   -- lightweight only
│       └── approvalRefs[]     -- lightweight only, attempt_no ASC
│
├── award                      -- 0..1 peer head; null if no award row
│   ├── award identity / head facts
│   └── versions[]             -- 0..N, version_no ASC
│       └── raw persisted award-version facts
│
└── persisted lineage/provenance references
    (column-level supersedes*/supersededBy* on versions;
     not full outcome_supersessions graph expansion)
```

**Cardinality (LOCKED):**

| Fact | Cardinality |
|------|-------------|
| Completion outcome head | 0..1 |
| Completion outcome versions | 0..N |
| Evaluation refs per version | lightweight 0..N |
| Approval refs per version | 0..N |
| Award head | 0..1 |
| Award versions | 0..N |

**Correct structural model:**

```text
Enrollment (school_id + enrollment_id)
├── CompletionOutcome
│   └── CompletionOutcomeVersions
└── GraduationAward
    └── GraduationAwardVersions
```

**Incorrect:**

```text
CompletionOutcome
└── awards
```

---

## 6. Completion Version Contract

Return **ALL** persisted completion outcome versions for the enrollment-scoped outcome head, ordered:

```text
version_no ASC
```

Expose raw persisted facts such as:

```text
versionId
versionNo
lifecycleStatus
evaluationStatus
eligibilityStatus
isCurrentOfficial
policy / calc references
fingerprints
evaluatedAt
supersedesVersionId
supersededByVersionId
correlationId
createdAt
```

**Do not:**

- filter out non-current / superseded rows via `is_current_official`
- synthesize additional status labels
- recalculate eligibility
- invent missing versions

---

## 7. Evaluation Reference Contract

**LOCKED:** Do **not** embed the complete evaluation graph in `GetOutcomeHistory`.

Lightweight references only. Conceptual shape:

```text
evaluationRefs[]
  evaluationId
  requirementDefinitionVersionId
  resultStatus
```

Additional already-persisted lightweight identifying facts may be included only where justified by the existing read model and without expanding evidence/evaluation payloads.

**Detailed evaluation retrieval** remains the responsibility of:

```text
GetRequirementEvaluations
```

with its existing version-specific / preferred-version contract (3C.19.2). History must not duplicate that full contract.

---

## 8. Approval Reference Contract

Approval history is included as **lightweight per-version references/facts**.

For each completion version:

```text
approvalRefs[]
```

ordered:

```text
attempt_no ASC
```

Each reference may contain:

```text
approvalId
attemptNo
decisionStatus
```

and other already-persisted lightweight identifying facts only where justified by the existing read model.

**Do not:**

- duplicate the full `GetGraduationApproval` response contract (3C.19.3)
- recalculate SoD
- recalculate eligibility
- invent “current approval only” for history

---

## 9. Award Peer Contract

Award is a **sibling section** under enrollment history.

```text
Correct:
  OutcomeHistory
  ├── completionOutcome
  └── award

Incorrect:
  CompletionOutcome
  └── awards
```

| Condition | Behavior |
|-----------|----------|
| No `graduation_awards` row | `award = null` |
| Award identity exists | Return award head + `award.versions[]` |

Return **ALL** persisted award versions ordered:

```text
version_no ASC
```

**Do NOT** use `current_issued_version_id` to collapse history to one version.

Pointer-only rule from 3C.19.4 / 04A remains limited to:

```text
GetGraduationAward = pointer-only current snapshot
GetOutcomeHistory  = historical listing of all persisted award versions
```

An award version may **pin** a completion outcome version and an approval (`completion_outcome_version_id`, `graduation_approval_id`), but `GraduationAward` is **not** a child of `CompletionOutcome`.

---

## 10. Award Version Contract

Expose raw persisted award-version facts including:

```text
versionId
versionNo
graduationApprovalId
completionOutcomeVersionId
lifecycleStatus
isCurrentIssued
awardedAt
issuedBy
awardNumber
honorsCode
supersedesVersionId
supersededByVersionId
correlationId
createdAt
```

**Do not synthesize:**

```text
isGraduated
awardStatusLabel
currentAwardStatus
StudentStatus
```

`is_current_issued` and `lifecycle_status` remain stored facts on each version — expose them; do not use them to drop versions from history.

---

## 11. Evidence Boundary

**LOCKED:** Do **not** include the complete:

```text
evidence_sets
evidence_items
```

graph in `GetOutcomeHistory`.

Evidence remains outside the history aggregate’s detailed payload.

If persisted evidence references are ever useful later, they must remain **lightweight references only**. This Design Lock does **not** introduce a new evidence query and does **not** authorize embedding full evidence payloads.

Deep evidence remains a possible future `GetProvenance` / dedicated query responsibility.

---

## 12. Revocation Boundary

Do **not** invent a synthetic award status.

Persisted award version facts remain authoritative:

```text
lifecycle_status
is_current_issued
```

Revocation records may be exposed only as **lightweight historical facts** if already supported by the locked read model and if explicitly included in a future implementation plan without expanding scope here.

**LOCKED for this Design Lock:**

- Do **not** turn `revocation_records` into a separate write/read subsystem in this phase
- Do **not** require full `revocation_records` expansion in the history DTO as a precondition of this lock
- Deep revocation / actor provenance remains a possible future `GetProvenance` responsibility

Revoked versions (`lifecycle_status = 3`, `is_current_issued = false`) **remain in** `award.versions[]` history.

---

## 13. Supersession Boundary

Return persisted supersession **references/columns as stored**:

```text
supersedesVersionId
supersededByVersionId
```

**Do NOT:**

- reconstruct missing chains
- infer relationships from `version_no`, `created_at`, or `is_current_official`
- make `outcome_supersessions` graph traversal the primary history mechanism
- treat empty/NULL supersession as “no history” (version rows still exist via `version_no`)

Full `outcome_supersessions` edge-audit expansion is **out of grain** for this aggregate; deferred to possible `GetProvenance`.

Audit note preserved: current write path may leave supersession columns/edges unpopulated — history still lists versions by `version_no`.

---

## 14. Missing Outcome Contract

### DL-3 — Missing completion outcome envelope (LOCKED)

If:

```text
completion_outcomes
```

does not exist for:

```text
school_id + enrollment_id
```

then:

```text
completionOutcome = null
completion versions = []   (no versions section / empty list)
```

However, the complete history DTO **MUST** remain capable of returning an independently existing award:

```text
completionOutcome = null
award             = existing award (head + versions)
```

is **valid**.

**Do NOT:**

```text
null entire DTO
```

solely because completion outcome is absent when an award identity exists.

**Do NOT** throw:

```text
CompletionOutcomeNotFoundException
```

for absence of a completion outcome in this historical aggregate.

The historical aggregate is **enrollment-scoped** and may contain independently persisted award facts (award table has no FK requiring an outcome).

| Case | completionOutcome | award |
|------|-------------------|-------|
| Neither exists | `null` | `null` (empty aggregate still enrollment-scoped; exact empty-envelope shape left to implementation plan without violating this lock) |
| Outcome only | head + versions | `null` |
| Award only | `null` | head + versions |
| Both | head + versions | head + versions |

---

## 15. Security / RLS Lock

Preserve:

```text
GraduationAuthorityPort::assertSchoolMatches(schoolId)
```

and tenant scope.

Every history query must remain scoped by:

```text
school_id
enrollment_id
```

Child records must remain constrained through the appropriate school-scoped parent relationships.

PostgreSQL:

```text
RLS ENABLE
RLS FORCE
```

remain **unchanged**.

```text
No RLS modification is authorized.
HTTP / Permission.php / policies: NOT AUTHORIZED.
```

Do not rely on application auth alone.

---

## 16. Performance Lock

Future implementation (when separately authorized) must avoid N+1.

Preferred conceptual strategy:

```text
1. resolve enrollment-scoped heads (completion_outcomes, graduation_awards)
2. load completion versions (ORDER BY version_no ASC)
3. load lightweight child references in bounded batch queries
   (evaluationRefs, approvalRefs keyed by version ids)
4. load award versions (ORDER BY version_no ASC)
5. assemble DTO
```

**This Design Lock does not:**

- introduce indexes
- change schema for performance
- authorize any new covering index

Existing UNIQUE / supporting indexes from 3C.12 remain sufficient for ordered listing at expected cardinality (PERF findings in the audit are report-only).

---

## 17. Compatibility With 3C.19.1–3C.19.4

| Unit | Contract preserved | History relationship |
|------|--------------------|----------------------|
| 3C.19.1 GetCompletionStatus | Preferred completion version selection | History must **not** reuse preferred filter to drop versions |
| 3C.19.2 GetRequirementEvaluations | Full/version-scoped evaluations | History uses **evaluationRefs only**; details stay on 19.2 |
| 3C.19.3 GetGraduationApproval | Approvals for preferred version / full approval shape | History uses **approvalRefs** per version; no SoD recalc; no full 19.3 duplication |
| 3C.19.4 GetGraduationAward | Pointer-only current award snapshot (04A) | History lists **all** award versions; pointer does **not** collapse history |

```text
GetGraduationAward  ≠  GetOutcomeHistory
pointer-only        ≠  historical listing
```

No modification of 3C.19.1–4 handlers, DTOs, repository methods, or tests is authorized by this Design Lock.

---

## 18. Explicit Non-Goals

```text
Implement GetOutcomeHistoryQuery / Handler / DTOs / repository methods
HTTP / routes / controllers / Permission.php / policies
Migrations / schema / indexes / RLS / triggers
Write paths: EvaluateCompletion, ApproveGraduation, IssueAward,
             RevokeAward, PublishAward
Modify GetCompletionStatus / GetRequirementEvaluations /
        GetGraduationApproval / GetGraduationAward
Implement GetProvenance
Repair supersession chains
Repair award pointers (04A)
Recalculate SoD
Calculate / synthesize StudentStatus
Invent status labels
Authorize the next implementation unit
```

---

## 19. Implementation Preconditions

Implementation remains **NOT AUTHORIZED** until a human issues an explicit next authorization (e.g. `APPROVED — IMPLEMENT GetOutcomeHistoryQuery`) covering at least:

1. This Design Lock accepted (DL-1, DL-2, DL-3)
2. Application-feature workflow + Clean Architecture placement when coding begins
3. One read-repository method extension without altering 3C.19.1–4 semantics
4. Unit + PostgreSQL school/RLS tests per audit §17 (when tests are authorized)
5. No schema/index/RLS/HTTP/write changes unless separately authorized

Audit OPEN items **closed by this Design Lock:**

| Audit finding | Disposition |
|---------------|-------------|
| F-04 Response nesting grain | **LOCKED** — refs for eval/approval; award sibling; evidence not expanded; supersession columns only |
| F-05 Primary history ORDER | **LOCKED** — `version_no ASC` / `attempt_no ASC` |
| F-06 Missing-outcome envelope | **LOCKED** — null completion, award may remain |
| F-07 GetProvenance boundary | **LOCKED enough** — deep evidence/edges/revocation detail deferred |

---

## 20. Human Approval Gate

```text
HUMAN APPROVAL REQUIRED: YES

Design Lock deliverable:
  .cursor/database/phase-3c-19/05-GET-OUTCOME-HISTORY-DESIGN-LOCK.md

To proceed beyond DESIGN LOCK ONLY, a human must explicitly authorize
implementation of GetOutcomeHistory as a separate unit.

Until then:
  IMPLEMENTATION: NOT AUTHORIZED
  NEXT UNIT: NOT AUTHORIZED
```

---

## Critical Consistency Check

Verified against this Design Lock and prior authoritative decisions:

| Check | Status |
|-------|--------|
| History does not use preferred-version filtering | **PASS** (§3, §6) |
| History does not collapse versions | **PASS** (§3, §5, §9) |
| Award is not modeled as outcome child | **PASS** (§5, §9) |
| Award pointer does not collapse history | **PASS** (§9; 3C.19.4 preserved) |
| Supersession is not reconstructed | **PASS** (§13) |
| `created_at` is not primary history order | **PASS** (§4) |
| `MAX(id)` is not primary history order | **PASS** (§4) |
| `MAX(version_no)` is not used to select one history item | **PASS** (§3, §4) |
| Evaluations are not duplicated as full graph | **PASS** (§7) |
| Evidence is not duplicated as full graph | **PASS** (§11) |
| SoD is not recalculated | **PASS** (§8, §18) |
| StudentStatus is not synthesized | **PASS** (§10, §18) |
| Missing completion outcome does not erase independently existing award | **PASS** (§14) |

**No contradiction** with locked 3C.19.1–4 / 04A contracts was found. Prior preferred/pointer semantics remain unchanged; history is a distinct read.

---

## Mutation Check

```text
PHP CODE CHANGES: NONE
DTO CHANGES: NONE
REPOSITORY CHANGES: NONE
HANDLER CHANGES: NONE
TEST CHANGES: NONE
DATABASE CHANGES: NONE
MIGRATION CHANGES: NONE
INDEX CHANGES: NONE
RLS CHANGES: NONE
HTTP CHANGES: NONE
WRITE PATH CHANGES: NONE
```

Deliverable only:

```text
.cursor/database/phase-3c-19/05-GET-OUTCOME-HISTORY-DESIGN-LOCK.md
```

---

```text
PHASE 3C.19.5
GetOutcomeHistory

MODE:
DESIGN LOCK ONLY

DESIGN:
LOCKED

IMPLEMENTATION:
NOT AUTHORIZED

HTTP:
NOT AUTHORIZED

DATABASE MUTATION:
NOT AUTHORIZED

WRITE PATH:
NOT AUTHORIZED

TESTS:
NOT AUTHORIZED

NEXT UNIT:
NOT AUTHORIZED

HUMAN APPROVAL:
REQUIRED

FINAL:
STOP
```
