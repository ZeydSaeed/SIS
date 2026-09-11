# Phase 3C.16 — Idempotency + Transaction Design

## Model (mandatory)

```text
BEGIN
  find idempotency key
  fingerprint assert (Case A replay / Case B reject)
  domain validate + business write
  outbox.stage
  idempotency.store(result + fingerprint)
COMMIT
```

Authority assert runs immediately before the transaction (fail-closed; no business mutation without school match).

## Cases

| Case | Behavior |
|------|----------|
| A same key + same fingerprint | Replay persisted payload |
| B same key + different fingerprint | `IdempotencyPayloadConflictException` |
| C different keys + same business identity | DB UNIQUE → `GraduationBusinessConflictException` |
| D lost response | Retry same key → Case A |

## Fingerprint

`GraduationIdempotencyGuard::fingerprint(command, schoolId, canonicalPayload)` stored under `request_fingerprint`.
