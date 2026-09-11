# Phase 3C.16 — Application Architecture

## Layers

```text
Application/Graduation/Commands/*Handler
  → GraduationAuthorityPort (fail-closed)
  → UnitOfWork.transaction
      → GraduationWriteRepositoryInterface
      → OutboxRepository.stage
      → IdempotencyStore.find/store
Domain/Graduation (events, exceptions, SoD, fingerprint guard)
Infrastructure (Eloquent repo, FailClosedGraduationAuthority)
```

## Shared infrastructure reused

- `UnitOfWork` / `EloquentUnitOfWork`
- `IdempotencyStore` / `EloquentIdempotencyStore` (`audit.idempotency_keys`)
- `OutboxRepository` / `EloquentOutboxRepository`
- `SchoolContext` + PostgreSQL GUC `app.current_school_id`
- RLS + FORCE RLS on graduation tables (unchanged)

## Forbidden patterns avoided

- Controller → DB bypass: no HTTP surface in this phase
- Enrollment-style post-commit idempotency: Graduation finds/stores **inside** the same transaction as business + outbox
- StudentStatus as Graduation SSOT: not written
