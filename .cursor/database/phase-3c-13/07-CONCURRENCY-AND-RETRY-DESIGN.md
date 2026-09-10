# Phase 3C.13 — Concurrency and Retry Design

Builds on Phase 3C.12B proofs (real OS-process races → `23505`; business UNIQUE ≠ idempotency-only).

## Protection stack (mandatory)

```text
Application Idempotency (key + fingerprint)
        +
Database Business Uniqueness
        +
Transaction Integrity
```

PHP pre-checks are **not** sufficient.

## Matrix — Request A vs Request B

| A vs B | Expected |
|--------|----------|
| Same key, same fingerprint | One effect; other replays |
| Same key, different fingerprint | REJECT conflict — no execute |
| Different keys, same business identity | At most one official row; loser `23505` → BusinessConflict |
| Different keys, different enrollments | Both may succeed |
| Same enrollment, different schools | FK/denorm/RLS fail closed |
| Retry after commit (same key) | Replay SUCCEEDED |
| Retry after rollback | Safe re-execute |
| Retry after unknown commit | Same key → replay or safe complete |

## Database errors

| SQLSTATE | Meaning | App handling |
|----------|---------|--------------|
| `23505` | Unique violation | If idempotency PK → find+replay/conflict; if business UNIQUE → map AlreadyExists / recover if same actor intent |
| `40001` | Serialization failure | Retryable transient (limited) |
| `40P01` | Deadlock | Retryable transient (limited) |
| Other `23xxx` | Integrity | Usually terminal for this attempt |

## Retry policy

| Error / situation | Retryable? | Who | Max | Idempotency required? | Safe after commit? |
|-------------------|------------|-----|-----|----------------------|--------------------|
| `40001` / `40P01` | YES | Handler or queue worker | small (e.g. 3) w/ backoff | YES | N/A (rolled back) |
| Connection failure mid-flight | YES | Client/queue | bounded | YES same key | Only via replay |
| Timeout / unknown commit | YES | Client | bounded | YES same key | Replay if committed |
| `23505` business uniqueness | NO auto-retry as insert | Map to conflict; optional read-then-return if policy says “return existing” for identical intent | — | Prefer key replay | If other txn won, do not insert again |
| `23505` idempotency PK | Treat as concurrent same key | find → replay or PROCESSING wait | short | already | — |
| Fingerprint conflict | NO | Client must new key | — | — | — |
| Authz / tenant failure | NO | — | — | — | — |
| Already approved / revoked | NO | Domain error | — | may cache terminal | — |

**Do not** blindly retry all PostgreSQL exceptions.

## Application must not add

- Production advisory locks solely for test convenience  
- Weakening UNIQUE constraints  
- Catch-and-ignore `23505` without classification  

## Verdict

```text
CONCURRENCY / RETRY: PASS
```
