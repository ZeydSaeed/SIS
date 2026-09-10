# Phase 3C.13 — CQRS Design

**Design only — no handlers/commands created.**

## Target stack

```text
Interface (HTTP/Inertia later)
  → FormRequest + Policy (authorize)
  → Command DTO (immutable)
  → CommandHandler (Application)
      → Domain validation / write guards / entities
      → Repository ports
      → UnitOfWork { business writes + outbox stage + idempotency store }
  → Result (replayable)

Query
  → QueryHandler
  → Read ports / DTOs
  → no writes, no outbox, no idempotency keys
```

## Layer responsibilities

| Layer | Owns | Must not own |
|-------|------|--------------|
| Domain | Invariants, VOs, exceptions, repository interfaces, domain events, write guards | Illuminate, HTTP, SQL strings in entities |
| Application | Orchestration, authz coordination, idempotency+fingerprint, UoW boundary, Results | Eloquent models, controller concerns |
| Infrastructure | Eloquent repos, IdempotencyStore, OutboxRepository, UnitOfWork | Business rules |
| Interface | Auth middleware, map HTTP→Command, status codes | Business mutation |

## Command contract (pattern)

Every sensitive Graduation command SHALL carry:

| Field | Required | Notes |
|-------|----------|-------|
| `actorUserId` | YES | Audit / decided_by / issued_by |
| `schoolId` | YES | Must equal tenant GUC |
| `enrollmentId` | when entity-scoped | Composite identity with school |
| `academicYearId` | when academic | Sis-core rule |
| `idempotencyKey` | YES for official effects | Client-stable UUID/string |
| `correlationId` | YES | Request tracing |
| payload… | per command | Canonical for fingerprint |

Example shape (not implemented):

```text
PublishOfficialCompletionCommand
├── actorUserId
├── schoolId
├── enrollmentId
├── academicYearId
├── completionOutcomeVersionId
├── idempotencyKey
├── correlationId
└── (no raw mutable grade data — grades remain Exams SSOT)
```

## Per-command design questions (template answers)

### PublishOfficialCompletion (exemplar)

1. **Purpose:** Mark evaluated completion version as current official for enrollment.  
2. **Identity:** `school_id + enrollment_id` (outcome) + `version_id`.  
3. **Idempotent:** YES.  
4. **Same operation:** same command_name + same key + same fingerprint.  
5. **Conflict:** same key + different fingerprint; or second official via different key → DB partial UNIQUE / business rules.  
6. **Key reuse different payload:** REJECT (`IdempotencyPayloadConflictException`).  
7. **Replayable result:** `{ outcome_id, version_id, school_id, from_cache }`.

### IssueGraduationAward

1. Issue award version after approval.  
2. `school_id + enrollment_id` (+ approval_id in fingerprint).  
3. YES.  
4–7. Same key/fingerprint rules; result `{ award_id, award_version_id }`; DB UNIQUE blocks dual awards.

### DecideGraduationApproval

1. Human approve/reject.  
2. `approval_id` + school.  
3. YES.  
4–7. Replay decision; reject re-decide when already decided (domain + DB CHECKs).

*(Remaining commands follow the same seven-question template; matrix in `03`.)*

## Handler algorithm (mandatory)

```text
1. Authorize (Policy) — outside or at edge of handler entry
2. Assert tenant: command.schoolId == SchoolContext / GUC
3. Compute fingerprint = GraduationIdempotencyGuard::fingerprint(...)
4. If key present:
     cached = IdempotencyStore::find(key, command_name)
     if cached: assertFingerprintMatch → return Result::fromIdempotency(cached)
5. Domain pre-checks (read-only OK outside txn if re-validated inside)
6. unitOfWork.transaction:
     a. Re-validate concurrency-sensitive state
     b. Persist business rows (INSERT versions; never illegal UPDATE)
     c. outbox.stage(DomainEvent)
     d. idempotency.store(key, command_name, payload WITH fingerprint + result ids)
7. Return Result::success(...)
```

On `23505` inside txn: rollback; classify (idempotency PK vs business UNIQUE); recover via find/replay or map to conflict error — see `07`.

## Query boundary

Controllers must not call repositories for Graduation writes.  
Read models may use dedicated query handlers; no shared “service” that both writes and lists.

## Controllers

Thin only:

```text
authorize → build Command → handler.handle → map Result → HTTP
```

No DB::, no Eloquent, no business branching beyond HTTP mapping.

## Scaffold plan (future implementation phase)

```text
php artisan sis:make-feature Graduation
php artisan sis:make-command Graduation PublishOfficialCompletion
...
```

Register bindings in `ArchitectureServiceProvider`.  
Fitness: `architecture:validate --fitness`, `architecture:feature-check Graduation`.

## Verdict

```text
CQRS DESIGN: PASS
```

Aligned with ARCHITECTURE-STACK + application-feature skill; stricter idempotency than current Enrollment handlers.
