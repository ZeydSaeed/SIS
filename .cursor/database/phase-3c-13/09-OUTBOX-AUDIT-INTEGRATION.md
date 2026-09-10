# Phase 3C.13 — Outbox / Audit Integration

## Existing architecture (FINAL storage)

| Store | Table | Port |
|-------|-------|------|
| Outbox | `audit.outbox_messages` | `OutboxRepository::stage` |
| Idempotency | `audit.idempotency_keys` | `IdempotencyStore` |
| Audit trail | existing audit patterns | do not invent parallel |

**No new outbox. No new idempotency table.** (Phase 3C.11 OUTBOX-IDEMPOTENCY plan — still binding.)

## Atomicity contract

```text
UnitOfWork transaction:
  Graduation business writes
  + OutboxRepository::stage(DomainEvent)
  + IdempotencyStore::store(SUCCEEDED…)
COMMIT
```

Consumer (`ProcessOutboxJob`) is **outside** the business txn; must tolerate at-least-once delivery and rebuild from SSOT if lagging.

## Proposed Graduation events (governance pending — names not registered)

From 3C.11 plan (PROPOSED):

| Event | When | StudentStatus? |
|-------|------|----------------|
| `completion.evaluated` | EvaluateCompletion | NO |
| `completion.eligibility_determined` | eligibility set | NO |
| `graduation.approval_requested` | RequestApproval | NO |
| `graduation.approval_decided` | DecideApproval | NO |
| `graduation.award_issued` | IssueAward | YES — may set Graduated |
| `graduation.outcome_superseded` | Supersede | MAYBE |
| `graduation.award_revoked` | Revoke | YES — may clear Graduated |
| `graduation.publication_*` | — | **DEFERRED** |

Payloads: **ids only** — no invented GPA/thresholds/role names.

## Implementation notes (future)

1. Add Domain event classes under `app/Domain/Graduation/Events/`.  
2. Register rehydrate arms in outbox consumer **only when consumers authorized**.  
3. StudentStatus side effects stay in **Students** context handlers — Graduation must not silently invent multi-enrollment policy.  
4. Do not duplicate audit by writing the same fact to a second custom table.

## Concurrency

Outbox row uniqueness follows existing outbox PK/identity; business uniqueness remains on graduation tables. Do not use outbox as uniqueness substitute.

## Verdict

```text
OUTBOX / AUDIT: PASS
```

Condition: event name governance + ProcessOutboxJob registration remain pending human approval.
