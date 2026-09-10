# Phase 3C.14 — E2E Verification Plan (Future Phase 3C.16)

**Do not execute as implementation tests in 3C.14.**

## Target proof chain

```text
HTTP/API
 → Authorization
 → School Context (GUC)
 → Idempotency (key + fingerprint)
 → CQRS Handler
 → Transaction (business + outbox + idempotency)
 → PostgreSQL (UNIQUE / FK / RLS / triggers)
 → Commit
 → Response
 → Retry
 → Replay
```

## Required scenarios

| # | Scenario | Pass criteria |
|---|----------|---------------|
| 1 | Same key concurrency | One business effect; loser/replay consistent |
| 2 | Different keys, same enrollment identity | One official effect; `23505` or BusinessConflict |
| 3 | Lost response retry | Replay original result; row count unchanged |
| 4 | Same key / different payload | Reject; no second effect |
| 5 | Non-superuser RLS | Fail closed without GUC; school A ≠ school B |
| 6 | Cross-school command | Denied (authz and/or FK/RLS) |
| 7 | Multi-enrollment A/B/C/D | Independent outcomes per HD-39; no cross leak |
| 8 | Approval authorization | Only authorized human decides; no auto-approve |
| 9 | Version lineage | No dual current official/issued; supersession valid |
| 10 | StudentStatus (if sync authorized) | Projection lag OK; SSOT remains awards |

## Dependencies before 3C.16 can fully pass

- 3C.15 implementation auth + code  
- HD-31 role catalog for scenario 8  
- Eval content if scenario includes EvaluateCompletion  
- Non-superuser test role for scenario 5  
- SS-MULTI if scenario 10 enabled  

## Verdict

Plan documented; execution **NOT AUTHORIZED** in 3C.14.
