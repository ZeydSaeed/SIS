# PHASE 3C.19.3
# GET GRADUATION APPROVAL QUERY GATE

**Date:** 2026-09-11  
**Unit:** UNIT-3C19-03 `GetGraduationApprovalQuery`  
**Mode:** IMPLEMENTATION — ONE UNIT ONLY  
**Predecessor:** 3C.19.1 PASS · 3C.19.2 PASS  

---

## 1. Scope

```text
GetGraduationApprovalQuery
+ GetGraduationApprovalHandler
+ GraduationReadRepositoryInterface::findGraduationApprovals
+ EloquentGraduationReadRepository (read method)
+ GraduationApprovalsDTO / GraduationApprovalItemDTO
+ Unit + PostgreSQL school/RLS tests
+ Feature contract + this gate report
```

---

## 2. Contract

### Input

```text
schoolId + enrollmentId
```

### Output

`GraduationApprovalsDTO`:

- CompletionOutcome identity + preferred `completion_outcome_version_id` (nullable)
- `approvals[]` = all `graduation_approvals` rows for that version (ordered by `attempt_no`)

### Not-found

Missing CompletionOutcome → `CompletionOutcomeNotFoundException`.

### No approval yet

Outcome exists → DTO with `approvals = []` (and null version id when no version).

---

## 3. Approval cardinality semantics

| Fact | Evidence |
|------|----------|
| UNIQUE | `(completion_outcome_version_id, attempt_no)` |
| Not unique per enrollment | Schema allows multiple versions × attempts |
| Multi-step depth | `attempt_no` structural; HD-31 levels OPEN (not invented here) |

**Query returns 0..N attempt rows for the preferred version** — not a forced singleton.

---

## 4. Current-vs-history semantics

| Scope | Behavior |
|-------|----------|
| Preferred completion version | Same architectural preference as 3C.19.1 / 3C.19.2: `is_current_official DESC`, then `version_no DESC` |
| Within that version | **All** approval attempts (`ORDER BY attempt_no ASC`) — AUTHORITATIVE set for UNIQUE key |
| Cross-version enrollment history | **Not** this query → deferred `GetOutcomeHistory` |

Does **not** invent a synthetic “current approval” singleton via `MAX(id)` / `MAX(created_at)`.

---

## 5. Contract field classification

| Field | Source | Classification |
|-------|--------|----------------|
| schoolId / enrollmentId / completionOutcomeId | `completion_outcomes` | AUTHORITATIVE |
| completionOutcomeVersionId | preferred version id | AUTHORITATIVE + ARCHITECTURALLY DEFINED selection (3C.19.1) |
| approvals[].id | `graduation_approvals.id` | AUTHORITATIVE |
| approvals[].schoolId | `school_id` | AUTHORITATIVE |
| approvals[].enrollmentId | `enrollment_id` | AUTHORITATIVE |
| approvals[].completionOutcomeVersionId | FK | AUTHORITATIVE |
| approvals[].attemptNo | `attempt_no` | AUTHORITATIVE |
| approvals[].decisionStatus | `decision_status` SMALLINT | AUTHORITATIVE (raw; no labels) |
| approvals[].requestedAt / requestedBy | columns | AUTHORITATIVE |
| approvals[].decidedAt / decidedBy | columns | AUTHORITATIVE |
| approvals[].decisionReasonRef | `decision_reason_ref` | AUTHORITATIVE (opaque) |
| approvals[].correlationId | `correlation_id` | AUTHORITATIVE |
| approvals[].createdAt | `created_at` | AUTHORITATIVE |

**Not implemented:** Approved/Rejected/Graduated labels, SoD validity flags, awards, StudentStatus, EvidenceSet.

---

## 6. Authoritative sources

3C.12 schema · COLUMN-CATALOG · ApproveGraduation write path · 3C.19.1/19.2 version preference · HD-39 · HD-31-G (HTTP blocked) · HD-36 (no revoke in this unit) · C-02 (SoD not recalculated on read).

---

## 7. Relationship/version rule

```text
completion_outcomes
  → preferred completion_outcome_versions (3C.19.1 rule)
  → graduation_approvals WHERE completion_outcome_version_id = preferred
```

FK: `(completion_outcome_version_id, school_id)` → versions.

---

## 8. Query flow

```text
GetGraduationApprovalQuery
  → Handler
      → assertSchoolMatches
      → findGraduationApprovals
      → null → CompletionOutcomeNotFoundException
      → GraduationApprovalsDTO
```

---

## 9. Repository design

Single new method:

```text
findGraduationApprovals(schoolId, enrollmentId): ?GraduationApprovalsDTO
```

---

## 10. DTO

Immutable `GraduationApprovalsDTO` + `GraduationApprovalItemDTO`. No ORM leakage. No policy methods.

---

## 11. School isolation

Authority + SQL `school_id`/`enrollment_id` + RLS/FORCE RLS.

---

## 12. PostgreSQL RLS verification

`rls_actor_under_school_b_cannot_see_school_a_approvals` with `sis_rls_tester` NOBYPASSRLS:

- School A approval ids invisible under School B GUC  
- Repository find School A → null  
- School B → visible  

---

## 13. SoD boundary (C-02)

| Allowed | Forbidden |
|---------|-----------|
| Expose persisted `requested_by` / `decided_by` | Recalculate Evaluator≠Approver |
| Leave write-path SoD unchanged | Add sod_valid / weaken SoD |

---

## 14. HD-31-G boundary

```text
NO HTTP / Permission.php / permission strings
```

Internal Application query only.

---

## 15. Tests

| Suite | Result |
|-------|--------|
| Unit `GetGraduationApprovalHandlerTest` | 5/5 PASS |
| PG `GetGraduationApprovalPostgreSqlTest` | 6/6 PASS |

---

## 16. Architecture validation

```text
architecture:validate --fitness → PASS
architecture:feature-check Graduation → PASS
```

---

## 17. Security validation

SchoolContext + SQL scope + RLS NOBYPASSRLS. No Permission catalog change.

---

## 18. Performance observations

| Item | Value |
|------|-------|
| Logical SQL | 1 |
| N+1 | None |
| SELECT * | Avoided |
| Access path | Outcome UNIQUE + `graduation_approvals_version_idx` |
| New index | **NO** |

---

## 19. Files changed

| Path | Action |
|------|--------|
| `.../Queries/GetGraduationApprovalQuery.php` | Added |
| `.../Queries/GetGraduationApprovalHandler.php` | Added |
| `.../DTOs/GraduationApprovalsDTO.php` | Added |
| `.../DTOs/GraduationApprovalItemDTO.php` | Added |
| `.../Contracts/GraduationReadRepositoryInterface.php` | Extended |
| `.../EloquentGraduationReadRepository.php` | Extended |
| `tests/Unit/Graduation/GetGraduationApprovalHandlerTest.php` | Added |
| `tests/Feature/Database/PostgreSql/GetGraduationApprovalPostgreSqlTest.php` | Added |
| `.cursor/architecture/features/Graduation.md` | Marked ✅ |
| `.cursor/database/phase-3c-19/03-GET-GRADUATION-APPROVAL-GATE.md` | This report |

---

## 20. Files not changed

Write handlers · Permission.php · routes · RevokeGraduation · awards · StudentStatus · migrations · RLS policies · SoD service.

---

## 21. Findings

| ID | Finding |
|----|---------|
| F-01 | Query returns attempt **set** for preferred version (not singleton “current approval”) |
| F-02 | Cross-version history deferred to GetOutcomeHistory |
| F-03 | Multi-level approval **policy** remains OPEN; structure only |

---

## 22. Deferred items

GetGraduationAward · GetOutcomeHistory · GetProvenance · HTTP · HD-31-G · HD-36 revoke · SoD register lock (C-02).

---

## 23. Mutation check

```text
HTTP CHANGES: NONE
ROUTE CHANGES: NONE
CONTROLLER CHANGES: NONE
PERMISSION CHANGES: NONE
POLICY CHANGES: NONE

MIGRATIONS: NONE
SCHEMA CHANGES: NONE
INDEX CHANGES: NONE
RLS POLICY CHANGES: NONE

PRODUCTION DATA MUTATION: NONE

APPROVAL WRITE CHANGES: NONE
REVOKE CHANGES: NONE
StudentStatus CHANGES: NONE
PublishAward CHANGES: NONE
IssueAward CHANGES: NONE
EvidenceSet WRITE CHANGES: NONE
OUTBOX CHANGES: NONE
IDEMPOTENCY CHANGES: NONE
AUDIT WRITE CHANGES: NONE
```

---

## 24. Gate decision

```text
PHASE 3C.19.3 FINAL GATE: PASS
```

**STOP.** Do not implement GetGraduationAward or any next unit without new human authorization.
