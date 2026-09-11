# Phase 3C.16 — Implementation Plan

**Mode:** IMPLEMENTATION WITH HARD SAFETY GATES  
**Authorization:** Human authorized 3C.16 implementation (not policy invention)

## Order executed

1. Pre-implementation gate (`01`)
2. Domain contracts / ports / SoD / exceptions / events
3. Write repository + fail-closed authority
4. CQRS commands/handlers (in-txn idempotency)
5. Outbox rehydrate arms
6. Tests (unit + PostgreSQL write path)
7. Architecture/security validation
8. Final gate (`18`)

## Implemented commands

| Command | Status |
|---------|--------|
| CreateCompletionOutcome | DONE |
| EvaluateCompletion | DONE (policy-row inputs only) |
| ApproveGraduation | DONE (+ SoD + eligibility gate) |
| IssueAward | DONE |
| RevokeAward | DONE (opaque reason_ref) |
| PublishAward | POLICY-GATED (HD-38) |
| RevokeGraduation (approval) | NOT IMPLEMENTED (HD-36/HD-31 OPEN) |

## Explicit non-goals

- Invent Permission.php graduation.* strings
- StudentStatus projection sync
- Default GPA / evaluation content catalogs
- Publication workflow
- Destructive schema changes
