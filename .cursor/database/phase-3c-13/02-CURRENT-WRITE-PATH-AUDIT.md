# Phase 3C.13 — Current Write-Path Audit

**Mode:** AUDIT ONLY — no components created.  
**Authority:** Repository evidence (2026-09-11).

## Inventory

| Component | Exists | Location | Status | Graduation-ready |
|-----------|--------|----------|--------|------------------|
| Domain Graduation tree | PARTIAL | `app/Domain/Graduation/` | Support + Exceptions only | No |
| `GraduationIdempotencyGuard` | YES | `app/Domain/Graduation/Support/GraduationIdempotencyGuard.php` | Fingerprint helper; unused by handlers | Partial |
| `IdempotencyPayloadConflictException` | YES | `app/Domain/Graduation/Exceptions/IdempotencyPayloadConflictException.php` | Defined | Partial |
| Domain entities / VOs / events / specs / write guards | NO | — | Missing | No |
| Application Graduation CQRS | NO | `app/Application/Graduation/` absent | Missing | No |
| Graduation repositories | NO | — | Missing | No |
| Controllers / routes | NO | API has Enrollment/Grades only | Missing | No |
| Graduation Policy / permissions | NO | `Permission.php` has no graduation constants | Missing | No |
| `GraduationTenantProtection` | YES | `app/Database/GraduationTenantProtection.php` | Migration RLS helper | DB-only |
| Shared `UnitOfWork` | YES | `Application/Contracts` + `EloquentUnitOfWork` | Reusable | Yes |
| Shared `IdempotencyStore` | YES | → `audit.idempotency_keys` | Reusable; **no fingerprint API** | Extend for Graduation |
| Shared `OutboxRepository` | YES | → `audit.outbox_messages` | Reusable; Graduation events unregistered | Extend |
| School GUC middleware | YES | `SchoolContextMiddleware` (+ terminate clear) | HTTP path OK | Yes (HTTP); queue gap |
| Graduation schema (14 tables) | YES | migrations `2026_09_10_1701xx–1709xx` | LIVE + sis_test verified | DB-ready |
| Concurrency tests (3C.12B) | YES | `tests/Feature/Database/PostgreSql/Graduation*Concurrency*` | DB proofs | N/A |
| Graduation UI | NO | — | Missing | No |

## Classification of “handlers missing”

```text
CLASS: Architecture roadmap gap (not a database defect)
ROADMAP: Phase 3C.11 planned; 3C.12 implemented DB; 3C.12B verified DB; 3C.13 designs app write path
```

## Reference patterns (reuse, do not invent parallel stacks)

| Concern | Canonical example |
|---------|-------------------|
| Command + Handler + Result | `EnrollStudentCommand` / `EnrollStudentHandler` |
| Versioned immutable write | `EnterStudentGradeHandler` / `CorrectStudentGradeHandler` |
| Authz | `EnrollmentPolicy` / `GradePolicy` + `Permission::*` |
| Scaffold | `php artisan sis:make-feature Graduation` / `sis:make-command` |

## Critical anti-pattern to NOT copy

`EnrollStudentHandler` stores idempotency **after** `UnitOfWork::transaction` returns. That creates a window:

```text
business committed + idempotency row missing → lost response → retry may attempt second write
```

DB uniqueness may still save the entity, but **replayable result is lost**. Graduation design (see `05`/`06`) requires idempotency persistence **inside** the same transaction as business + outbox.

## Existing Enrollment/Exams idempotency gap vs Graduation Guard

| Behavior | Enrollment/Exams today | Graduation required (F-11A-003) |
|----------|------------------------|----------------------------------|
| Same key → replay | YES | YES |
| Same key + different payload | **Silent replay of old result** | **REJECT** via `GraduationIdempotencyGuard` |

## Verdict

```text
CURRENT WRITE PATH: PARTIAL
```

Database + shared infrastructure ready. Graduation Application write path does not exist yet — by design deferral, not regression.
