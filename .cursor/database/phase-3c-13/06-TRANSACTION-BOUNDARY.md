# Phase 3C.13 — Transaction Boundary

## Atomicity requirement

Graduation MUST avoid both failure modes:

```text
BAD-1: business commit + idempotency result lost
BAD-2: idempotency marked SUCCEEDED + business rolled back
```

## Ordered steps

| Step | Inside UoW txn? | Notes |
|------|-----------------|-------|
| Authentication | No (middleware) | Before handler |
| Authorization (Policy) | No (entry) | Fail closed before side effects |
| Tenant resolve / GUC already set | No (middleware) | Handler asserts match |
| Fingerprint compute | No | Pure |
| Idempotency find + fingerprint assert | Optional read outside; **re-check inside** if racing | Replay short-circuit |
| Domain read validation | Prefer inside or re-validate inside | Avoid TOCTOU |
| Business mutation | **YES** | Inserts / lineage only |
| Version mutation / pointers | **YES** | Same txn |
| Outbox stage | **YES** | Same `OutboxRepository::stage` |
| Idempotency store SUCCEEDED | **YES** | Same txn — **mandatory for Graduation** |
| Commit | UoW | All or nothing |
| HTTP response / replay mapping | No | After commit |

## Intended unit of work

```text
BEGIN
  assert tenant GUC
  [optional] reserve idempotency PROCESSING
  domain writes (outcomes/versions/approvals/awards/…)
  outbox.stage(event)
  idempotency.store(SUCCEEDED + fingerprint + result ids)
COMMIT
```

## Contrast with current Enrollment handler

Enrollment stores idempotency **after** commit → BAD-1 window.  
**Graduation design forbids copying that pattern.**

Enrollment gap classification for roadmap:

```text
TECHNICAL DEBT: Enrollment/Exams post-commit idempotency store
(Not fixed in 3C.13 — documentation only)
```

## Outbox atomicity

Matches ADR-013 / existing Enrollment-in-txn staging:  
business rows + outbox row commit together. Consumer is async and must be idempotent.

## RLS

GUC `app.current_school_id` must be set on the **same connection** executing the txn (middleware for HTTP). Handler does not disable RLS.

## Verdict

```text
TRANSACTION BOUNDARY: PASS
```
