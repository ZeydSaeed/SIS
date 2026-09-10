# Phase 3C.13 — Write Operation Matrix

Derived from Phase 3C.11 APPLICATION-INTEGRATION-PLAN + LIVE 14-table schema.  
**No invented operations.** Policy-dependent ops marked; do not implement until human lock.

## Legend

| Flag | Meaning |
|------|---------|
| YES | Required for safe production write |
| OPTIONAL | Allowed without key for admin drafts only |
| POLICY DEPENDENCY | Do not implement until HD/roles/content resolved |
| DEFERRED | Explicitly deferred (e.g. publication_*) |

---

## Commands (writes)

| Command (planned name) | Actor | Tenant | Logical identity | Input (canonical) | Authz | Validation | Txn | Idempotency | DB mutation | Outbox | Result | Retry |
|------------------------|-------|--------|------------------|-------------------|-------|------------|-----|-------------|-------------|--------|--------|-------|
| `UpsertDraftEligibilityPolicy` | school admin TBD | school_id | school + policy_code | policy fields | TBD | draft rules | YES | OPTIONAL | eligibility_policies / versions | optional | policy_id, version_id | safe if keyed |
| `PublishEligibilityPolicyVersion` | publish authority TBD | school_id | policy_id + version_no | version_id | TBD | lifecycle | YES | YES | set current_effective | optional | version_id | replay |
| `UpsertRequirementDefinition` | school admin TBD | school_id | policy + requirement_code | requirement fields | TBD | codes | YES | OPTIONAL | requirement_* | optional | ids | safe if keyed |
| `EvaluateCompletion` | system/registrar TBD | school_id | school + enrollment | enrollment_id, policy_version_id, evidence refs | TBD | enrollment ownership; policy content | YES | YES | outcome + version + evidence + evaluations | `completion.evaluated` (+ eligibility if set) | outcome_id, version_id | replay / 23505 recover |
| `PublishOfficialCompletion` | TBD (HD-35) | school_id | school + enrollment | outcome_version_id | TBD | eligibility gate | YES | YES | mark official / current pointer | eligibility/recorded event | version_id | replay |
| `RequestGraduationApproval` | requester TBD | school_id | version + attempt_no | outcome_version_id | TBD | official version exists | YES | YES | graduation_approvals insert | `approval_requested` | approval_id | replay |
| `DecideGraduationApproval` | human approver (HD-31) | school_id | approval_id | decision_status, reason_ref | **POLICY DEPENDENCY** | pending only | YES | YES | update decision fields | `approval_decided` | approval_id, status | replay; reject double decide |
| `IssueGraduationAward` | issuer after approved | school_id | school + enrollment | approval_id, award attrs | TBD (HD-32) | approved; no current award conflict | YES | YES | awards + award_versions | `award_issued` | award_id, version_id | replay; DB UNIQUE |
| `SupersedeCompletionOutcome` | elevated TBD | school_id | outcome aggregate | predecessor_version_id, new payload | TBD (HD-35) | immutability; single current | YES | YES | new version + supersession edge | `outcome_superseded` | new_version_id | replay |
| `RevokeGraduationAward` | authority TBD | school_id | award_version | award_version_id, reason | TBD (HD-36) | issued; not already revoked | YES | YES | revocation_records + version lifecycle | `award_revoked` | revocation_id | replay |
| `RebuildStudentStatusFromGraduation` | ops elevated | school_id | student/enrollment | enrollment_id | elevated | multi-enrollment policy | YES | YES | Students context only | per StudentStatus plan | status ids | **POLICY DEPENDENCY** — Students module |

## Queries (reads — not write path, listed for CQRS split)

| Query | Purpose | Authz |
|-------|---------|-------|
| `GetCurrentOfficialGraduationState` | Current outcome/award for enrollment | school |
| `GetGraduationHistory` | Versions / supersessions / revocations | elevated/audit |

## Non-commands (must not become mutable UPDATEs)

| Table / concept | Write style |
|-----------------|-------------|
| Official completion versions | Insert new version / supersede — not mutate official rows |
| Issued award versions | Insert / revoke lineage — not rewrite issued facts |
| Reject-delete protected tables | Soft lineage only |

## Identity summary

```text
Official completion / award aggregate identity:
  school_id + enrollment_id

Version identity:
  parent_id + version_no  (+ partial UNIQUE current flags)

Approval identity:
  completion_outcome_version_id + attempt_no
```

## POLICY DEPENDENCY — DO NOT IMPLEMENT (until human locks)

1. EvaluateCompletion engine **content** (HD-20 / requirement semantics)  
2. DecideGraduationApproval **role matrix** (HD-31)  
3. RebuildStudentStatus multi-enrollment rules  
4. Any `graduation.publication_*` events (DEFERRED)

Scaffold-only / structural commands may be designed; business rule bodies remain blocked.
