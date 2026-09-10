# Phase 3C.12B — Concurrency Control Map

**Authority:** LIVE/test catalog + repository code (verified).  
**Scope:** Graduation write surfaces present after Phase 3C.12.  
**Note:** No `app/Application/Graduation/**` CQRS handlers exist — application write orchestration is deferred.

## Protection taxonomy

| Label | Meaning |
|-------|---------|
| APPLICATION | App/domain PHP guard |
| DATABASE | UNIQUE / PK / FK / CHECK / partial unique index |
| TRANSACTIONAL | Relies on txn atomicity / lock wait → commit ordering |
| TENANT/RLS | ENABLE+FORCE RLS + fail-closed policy / composite school FK |
| IDEMPOTENCY | `audit.idempotency_keys` PK + `GraduationIdempotencyGuard` fingerprint |
| VERSIONING | version_no uniqueness, partial current flags, supersession FKs, immutability triggers |

## Write-operation map

| Operation | Identity | Idempotency | DB Constraint | Transaction | Lock | RLS | Retry Safe |
|-----------|----------|-------------|---------------|-------------|------|-----|------------|
| Insert `completion_outcomes` | `school_id + enrollment_id` | Keys optional (no handler); business UNIQUE independent of key | `completion_outcomes_school_enrollment_uq`; composite enrollment FKs; denorm trigger | Caller txn | Index unique lock on conflict | FORCE + school policy | YES — duplicate → 23505 |
| Insert `completion_outcome_versions` | `(completion_outcome_id, version_no)`; at most one `is_current_official` | Not wired in handlers | `completion_outcome_versions_uq`; partial unique `..._current_official_uidx` | Caller txn | Unique index | FORCE | YES for same version_no / dual current |
| Insert `evidence_sets` | One set per outcome version | — | `evidence_sets_version_uq` | Caller txn | Unique | FORCE + parent school trigger | YES |
| Insert `evidence_items` | Set + source tuple | — | `evidence_items_source_uq` | Caller txn | Unique | FORCE | YES |
| Insert `requirement_evaluations` | Version + requirement version | — | `requirement_evaluations_uq` | Caller txn | Unique | FORCE | YES |
| Insert `graduation_approvals` | `(completion_outcome_version_id, attempt_no)` + enrollment school FK | — | `graduation_approvals_attempt_uq` | Caller txn | Unique | FORCE | YES for same attempt |
| Insert `graduation_awards` | `school_id + enrollment_id` | Keys optional | `graduation_awards_school_enrollment_uq` | Caller txn | Unique | FORCE + denorm | YES |
| Insert `graduation_award_versions` | `(award_id, version_no)`; one current issued | — | `graduation_award_versions_uq`; partial `..._current_issued_uidx` | Caller txn | Unique | FORCE | YES |
| Insert `outcome_supersessions` | Lineage endpoints CHECKs + FKs | — | endpoint CHECK + FKs | Caller txn | Row/FK | FORCE | Depends on caller |
| Insert `revocation_records` | Award version + school | — | PK + composite FK | Caller txn | FK | FORCE | Insert-once semantics |
| Insert `audit.idempotency_keys` | `(key, command_name)` | IDEMPOTENCY (APPLICATION fingerprint helper) | PRIMARY KEY | Caller txn | PK lock | N/A (audit schema) | YES — second insert 23505 |
| Policy / requirement definition writes | School-scoped codes / version_no | — | school+code / policy+version UNIQUEs | Caller txn | Unique | FORCE | YES |

## Classification of verified protections

| Surface | Layers verified |
|---------|-----------------|
| Completion outcome uniqueness | DATABASE + TRANSACTIONAL + TENANT/RLS (FK) |
| Award uniqueness | DATABASE + TRANSACTIONAL + TENANT/RLS (FK) |
| Current official / current issued | DATABASE (partial UNIQUE) + VERSIONING |
| Version numbers | DATABASE + VERSIONING |
| Cross-school enrollment pairing | DATABASE (composite FK) + triggers |
| Idempotency key row | IDEMPOTENCY + DATABASE |
| Fingerprint conflict | APPLICATION (`GraduationIdempotencyGuard`) — unit only |
| Official/issued immutability | VERSIONING (BEFORE UPDATE/DELETE triggers) |
| CQRS command retry / response replay | **NOT IMPLEMENTED** — no handlers |

## Identity consistency verdict

```text
IDENTITY CONSISTENCY: PASS
```

Core official effects (`completion_outcomes`, `graduation_awards`) enforce `UNIQUE (school_id, enrollment_id)` at the database. Child/version rows bind via parent FKs and school-aligned composite FKs. Approvals/evaluations/evidence are version-scoped (by design), not a second copy of enrollment identity.

## Gaps (not defects of UNIQUE constraints)

1. Application CQRS does not yet call the idempotency guard in a real write path.
2. Outbox Graduation event emission is deferred — no concurrent outbox duplicate proof for Graduation.
3. True parallel-process race covered for `completion_outcomes`; other uniqueness paths proven via serialized dual-PDO / single-session retry (still enforce 23505).
