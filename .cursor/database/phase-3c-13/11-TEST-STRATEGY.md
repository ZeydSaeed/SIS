# Phase 3C.13 — Test Strategy

**Design only — tests not implemented in this phase** (except existing 3C.12B suite remains).

## Pyramid

### Unit

| Area | Assert |
|------|--------|
| Fingerprint stability / conflict | `GraduationIdempotencyGuard` (exists) |
| Command canonical payload builders | same input → same hash |
| Idempotency state transitions | PROCESSING→SUCCEEDED / conflict |
| Authorization rules | Policy allow/deny matrices |
| Domain write guards | already official / revoked / stale |

### Integration (`phpunit.database-pgsql.xml` + `PostgreSqlIntegrationTestCase`)

| Area | Assert |
|------|--------|
| Handler txn atomicity | business + outbox + idempotency same commit |
| Rollback | none of three persist |
| Repository inserts | constraints honored |
| Replay after success | Result equality |
| Fingerprint mismatch | exception, no second write |

### Concurrency

| Scenario | Assert |
|----------|--------|
| Same key parallel | one effect + replay |
| Different keys same identity | one effect + conflict |
| Retry after commit | replay, row count 1 |
| Extend 3C.12B process races | awards / current-issued optional |

### Security / RLS

| Scenario | Assert |
|----------|--------|
| Non-superuser role | fail closed without GUC |
| Cross-school | no mutation/visibility |
| Tenant context clear | no leak on pooled connection |

### E2E (later)

```text
HTTP → Command → Handler → Idempotency → Txn → PostgreSQL → Response replay
```

## Database-first reminder

Tests that only mock repositories **cannot** close concurrency gates; keep PG constraint tests from 3C.12B in regression set when handlers land.

## Verdict

```text
TEST STRATEGY: PASS
```
