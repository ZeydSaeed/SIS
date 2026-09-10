# Phase 3C.12B — Idempotency Verification

## Existing contract (invented semantics forbidden)

| Artifact | Role |
|----------|------|
| `audit.idempotency_keys` | PK `(key, command_name)`; stores `response_payload`, `expires_at` |
| `GraduationIdempotencyGuard` | SHA-256 fingerprint over `command + school_id + canonical payload`; conflict → `IdempotencyPayloadConflictException` |
| Application handlers | **Absent** — no Graduation CQRS command handlers |

## Expected behavior (DB + guard only)

| Scenario | Expected |
|----------|----------|
| Same key + same command, concurrent inserts | Exactly one row; loser `23505` |
| Same business identity, different keys | **Both keys may insert**; business UNIQUE still allows only one outcome/award |
| Retry after successful commit (lost response) | Second business insert → `23505`; persisted effect remains one |
| Fingerprint mismatch on reused key payload | Guard throws conflict (unit-tested) |
| Timeout / connection loss mid-txn before commit | No durable effect if rolled back; retry may succeed once |

## Tests executed

| Test | Result | Concurrency class |
|------|--------|-------------------|
| `concurrent_idempotency_key_primary_key_allows_only_one_row` | PASS | SERIALIZED dual-PDO |
| `different_idempotency_keys_cannot_create_duplicate_completion_outcome` | PASS | SERIALIZED — proves business UNIQUE ≠ idempotency only |
| `retry_after_commit_cannot_duplicate_completion_outcome` | PASS | Sequential retry |
| `GraduationIdempotencyGuardTest` | PASS | Unit |
| Parallel process race on completion_outcomes | PASS | **REAL CONCURRENCY** |

## Verdict

```text
IDEMPOTENCY: PARTIAL
```

**Why not full PASS:** Database + fingerprint helper are correct, and business uniqueness prevents duplicate official effects even when keys differ. End-to-end “reuse prior response_payload on retry” cannot be verified without Graduation application handlers.

## Defects

### F-12B-002 — Application idempotency path deferred

| Field | Value |
|-------|-------|
| Class | C (existing architecture gap) / F (idempotency coverage) |
| Detected | No `app/Application/Graduation` handlers |
| Evidence | Repo grep; Phase 3C.12 gate already marked handlers deferred |
| Impact | Concurrent same-key **response reuse** unproven at application layer |
| Production relevance | Schema protects against duplicate official rows; clients may still need handler-level replay |
| Recommended action | Implement CQRS handlers with guard + key table in a later authorized phase |
| Requires implementation approval | YES |
