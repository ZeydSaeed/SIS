# Phase 3C.14 — Infrastructure Reuse Audit

| Infrastructure | Classification | Notes |
|----------------|----------------|-------|
| `UnitOfWork` / `EloquentUnitOfWork` | **SAFE TO REUSE** | Handler-owned txn |
| `IdempotencyStore` / `audit.idempotency_keys` | **REUSE WITH ADAPTATION** | Must store **inside** txn; assert fingerprint via `GraduationIdempotencyGuard`; optional `tryReserve` later |
| `OutboxRepository` / `audit.outbox_messages` | **SAFE TO REUSE** | Stage in same txn; register Graduation events later |
| `SchoolContext` + middleware GUC set/clear | **SAFE TO REUSE** | HTTP; queue must set/clear explicitly |
| `RequireSchoolContextMiddleware` | **SAFE TO REUSE** | Fail closed |
| CQRS `Command` / `CommandHandler` / Results | **SAFE TO REUSE** | Mirror Exams/Enrollment shape |
| DTO / Result `fromIdempotency` pattern | **SAFE TO REUSE** | Include fingerprint fields |
| `Permission` + Policy classes | **REUSE WITH ADAPTATION** | Pattern only — **no** Graduation permissions yet |
| Enrollment business rules | **NOT SUITABLE** | Do not copy semantics |
| Enrollment post-commit idempotency | **NOT SUITABLE** | Explicit anti-pattern for Graduation |
| `GraduationIdempotencyGuard` | **SAFE TO REUSE** | Already Graduation-specific |
| `GraduationTenantProtection` | **SAFE TO REUSE** | Migration helper already applied — do not reopen |
| ProcessOutboxJob | **REUSE WITH ADAPTATION** | Add Graduation arms when events governed |
| Student domain `StudentStatus` | **REUSE WITH ADAPTATION** | Projection target only; sync policy open |

## Shared infrastructure modification in 3C.14

```text
NONE — documentation only
```

Future adaptation of `IdempotencyStore` (fingerprint-aware APIs) requires separate implementation authorization — prefer Graduation handler composition over breaking Enrollment until coordinated.
