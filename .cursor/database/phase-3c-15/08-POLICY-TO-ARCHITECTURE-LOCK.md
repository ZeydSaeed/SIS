# Phase 3C.15 — Policy → Architecture Lock

## Policy → Database

| Policy | DB alignment | Schema impact |
|--------|--------------|---------------|
| HD-39 identity | UNIQUE `(school_id,enrollment_id)`; composite FKs; RLS FORCE | **NONE** — already LIVE |
| HD-19 dual lineage | completion_* vs awards_* | NONE |
| DL-019 / HD-35 immutability | reject-delete + supersession | NONE |
| HD-36 mechanism | revocation_records | NONE |
| HD-20/21 content | nullable JSONB / requirement rows | NONE until content authored (data, not mandatory DDL) |
| HD-31 roles | opaque decided_by | NONE — no role enum invented |
| Award attrs | nullable columns (e.g. award_number, honors_code) | OPEN meaning; **SCHEMA IMPACT = NOT REQUIRED** for empty attrs |
| HD-38 | no dedicated publication table mandated | If humans require new publication entity later → `SCHEMA IMPACT = REQUIRED` then STOP for approval |

```text
POLICY / DATABASE: PASS
(no locked policy requires unapproved schema change)
```

Duplicate official effects: forbidden by UNIQUE + partial current indexes.  
Cross-school: forbidden by composite FK + RLS.  
Destructive history: forbidden by triggers/DL-019.

## Policy → CQRS readiness

| Command (candidate) | Required policy | Authz | Identity | Idempotency | Txn | Readiness |
|---------------------|-----------------|-------|----------|-------------|-----|-----------|
| EvaluateCompletion | HD-20/21-CONTENT | OPEN | school+enrollment | YES in-txn | UoW+outbox+idem | **BLOCKED** |
| PublishOfficialCompletion / CreateCompletionOutcome official | HD-35 human gate; roles OPEN | OPEN | same | YES | same | **BLOCKED** (roles) / structure READY WITH CONDITIONS |
| RequestGraduationApproval | HD-31 model | OPEN | version+attempt | YES | same | **BLOCKED** (permissions) |
| DecideGraduationApproval / ApproveGraduation | HD-31-ROLES | OPEN | approval_id | YES | same | **BLOCKED** |
| IssueAward | HD-31/32; attrs OPEN | OPEN | school+enrollment | YES | same | **BLOCKED** |
| RevokeAward / RevokeGraduation | HD-36-ROLES/REASONS | OPEN | award_version | YES | same | **BLOCKED** |
| PublishAward | HD-38 | OPEN | TBD | YES | same | **DEFERRED/BLOCKED** |
| Queries (current/history SSOT) | DL-022 read SSOT | school scope | enrollment | N/A | read | READY WITH CONDITIONS (after impl auth + permissions for view) |

## Idempotency impact

| Include in fingerprint? | Rule |
|-------------------------|------|
| Semantic business payload (enrollment, version ids, decision_status, policy_version_id, …) | YES |
| Role name / display label / authz metadata | **NO** unless it changes business result (it must not) |
| schema_version of canonical payload | YES |

Transaction remains:

```text
idempotency + business write + outbox + result = ONE TRANSACTION
```

(3C.13 — unchanged; do not copy Enrollment post-commit store.)

## Award attribute catalog

| Attribute | Class |
|-----------|-------|
| Separate Award entity | LOCKED |
| `award_number` column existence | LOCKED (schema); meaning OPEN |
| `honors_code` column existence | LOCKED (schema); meaning OPEN |
| Issue/classification/certificate semantics | OPEN |
| Publication metadata | OPEN (HD-38) |

## Verdict

```text
POLICY / CQRS: BLOCKED for official mutating commands
IDEMPOTENCY architecture: PASS
```
