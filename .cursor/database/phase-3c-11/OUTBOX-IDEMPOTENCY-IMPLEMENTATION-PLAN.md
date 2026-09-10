# PHASE 3C.11 — OUTBOX & IDEMPOTENCY IMPLEMENTATION PLAN

**No new outbox. No new idempotency store. Events NOT registered in code.**

## Storage contracts (FINAL)

| Store | Table | Status |
|-------|-------|--------|
| Outbox | `audit.outbox_messages` | FINAL — reuse `OutboxRepository::stage` |
| Idempotency | `audit.idempotency_keys` | FINAL — reuse `IdempotencyStore` |

```text
STORAGE CONTRACT = FINAL
EVENT NAME = PROPOSED / GOVERNANCE PENDING
```

---

## Proposed event contracts (governance pending)

Align placeholders with Phase 3C.9 dotted names (3C.10 SCREAMING superseded as style).

| Event (PROPOSED) | Source entity | Transition | Payload (ids only) | StudentStatus? |
|------------------|---------------|------------|--------------------|----------------|
| completion.evaluated | CompletionOutcomeVersion | evaluated | school, enrollment, outcome, version, correlation | NO |
| completion.eligibility_determined | CompletionOutcomeVersion | eligibility set | + eligibility_status | NO |
| graduation.approval_requested | GraduationApproval | requested | school, approval, version | NO |
| graduation.approval_decided | GraduationApproval | approved/rejected | + decision_status | NO |
| graduation.award_issued | GraduationAwardVersion | issued | school, award, version, enrollment | YES — may set Graduated |
| graduation.outcome_superseded | Supersession | edge | pred, succ, kind | MAYBE |
| graduation.award_revoked | RevocationRecord | revoked | award_version | YES — may clear Graduated |
| graduation.publication_* | — | — | — | DEFERRED |

**Do not invent** thresholds, GPA, or role names in payloads.

### Per-event fields (common)

| Field | Plan |
|-------|------|
| Transaction | Same UnitOfWork as state write |
| school context | In payload + session GUC already set |
| Aggregate / version identity | Explicit ids |
| Ordering | Per aggregate best-effort via occurred_at; no distributed TX |
| Consumer | Retry via outbox attempts; rebuild from SSOT if lag |
| Idempotency of consumer | Consumer-side dedupe by event id / business key |

---

## Idempotency operations

LIVE PK: `(key, command_name)` on `audit.idempotency_keys`.

| Operation | command_name (example) | Key scope | Replay |
|-----------|------------------------|-----------|--------|
| Publish official completion | Graduation.PublishCompletion | school+enrollment+client key | Return existing version ids |
| Decide approval | Graduation.DecideApproval | school+approval+key | No double decide |
| Issue award | Graduation.IssueAward | school+enrollment+approval+key | Return existing award version |
| Revoke award | Graduation.RevokeAward | school+award_version+key | Return existing revoke |
| Supersede | Graduation.SupersedeCompletion | school+outcome+key | Return new version if first |

| Behavior | Plan |
|----------|------|
| Transaction | Idempotency write + domain write + outbox **same transaction** (match Enrollment/Exams handlers) |
| Failure before commit | No key stored → safe retry |
| Failure after commit | Replay returns cached Result |
| Consumer | Separate; rebuildable from SSOT |

Do not create competing idempotency tables.
