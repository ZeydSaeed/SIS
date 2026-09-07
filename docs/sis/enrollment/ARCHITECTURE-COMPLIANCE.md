# Enrollment Module — Architecture Compliance

**Date:** 2026-09-07  
**Reference:** `.cursor/architecture/ARCHITECTURE-STACK.md`, `FEATURE-DONE.md`, `laravel-architecture.md`

---

## Layer Compliance Matrix

| Rule | Expected | Current | Status |
|------|----------|---------|--------|
| Domain has no Http/Eloquent/DB imports | Clean | Enrollment domain clean | **PASS** |
| Application handlers own transactions | UoW in handler | `EnrollStudentHandler` uses UoW | **PASS** |
| Controllers delegate to handlers | Thin controllers | **No controller** | **N/A** |
| No Eloquent in controllers | Enforced | No controller | **N/A** |
| CQRS write: Command + Handler + Result | Required | Present | **PASS** |
| CQRS read: Query + Handler + DTO | Required for reads | **Missing queries** | **FAIL** |
| Domain entity with behavior | Required per FEATURE-DONE | Anemic `CreateEnrollmentData` only | **FAIL** |
| Repository port + adapter | Optional | Present for write | **PASS** |
| Domain event + outbox | Required for side effects | `StudentEnrolled` staged | **PASS** |
| Idempotency on sensitive writes | Required | `EnrollStudentCommand.idempotencyKey` | **PASS** |
| Policy + FormRequest for HTTP | Required when endpoints exist | **Missing** | **FAIL** |
| Feature contract validation | `architecture:feature-check Enrollment` | Passes in ArchitectureHardeningTest | **PASS** |

---

## Dependency Direction

```text
Presentation (missing)
    ↓
Application (EnrollStudentHandler)
    ↓
Domain (Specifications, Events, Ports)
    ↓
Infrastructure (EloquentEnrollmentRepository, EnrollmentRecord)
```

**Violations found:** None in existing enrollment code paths.

**Cross-context read:** `Domain\Enrollment\Repositories\StudentReadRepositoryInterface` → Student infrastructure adapter — **acceptable** per CQRS-lite patterns.

---

## Anti-Patterns Checked

| Anti-pattern | Found? | Location |
|--------------|--------|----------|
| Business logic in controller | No | No controller |
| Direct DB in controller | No | — |
| `$request->all()` to model | No | No HTTP |
| Security checks in UI only | No | No UI |
| Infrastructure leakage into Domain | No | — |
| Circular dependencies | No | — |
| Legacy service bypass | Indirect | `AttendanceBatchService` references `enrollment_id` (`@architecture-legacy-allowed`) |

---

## FEATURE-DONE Checklist (Enrollment)

| # | Item | Status |
|---|------|--------|
| 1 | Bounded context identified | **PASS** — Enrollment |
| 2 | Domain Entity with behavior | **FAIL** |
| 5 | Command + Handler + Result | **PASS** |
| 6 | Query + Handler + DTO | **FAIL** |
| 7 | Repository port + adapter | **PASS** |
| 8 | UnitOfWork transaction | **PASS** |
| 9 | Domain event + Outbox | **PASS** |
| 10 | Idempotency key | **PASS** |
| 11 | Policy authorization | **FAIL** |
| 12 | FormRequest validation | **FAIL** |
| 13 | Feature tests | **PARTIAL** — unit only |
| 14 | Security tests | **PARTIAL** — RLS only |

---

## Architecture Validation Commands

| Command | Expected (audit) | Result |
|---------|------------------|--------|
| `php artisan architecture:validate` | PASS | Not re-run this audit (no code changes) |
| `php artisan architecture:feature-check Enrollment` | PASS | Passes via ArchitectureHardeningTest |
| `php artisan architecture:validate --fitness` | PASS | Inherited from project baseline |

---

## Required Architecture Work (Implementation Phase)

1. Add `EnrollmentPolicy` + register in `SecurityServiceProvider`
2. Add `EnrollStudentRequest` (FormRequest) — no `authorize(): true`
3. Add `EnrollmentController` delegating to `EnrollStudentHandler` only
4. Add read queries: `ListEnrollmentsQuery`, `GetEnrollmentQuery` + handlers
5. Consider `Enrollment` domain entity for status transitions (withdraw/transfer future)
6. Wire audit listener to `SecurityAuditLogger` instead of raw log
7. Scaffold via `php artisan sis:make-feature Enrollment --query=...` for new read paths

---

## Schema Drift

| Item | Blueprint | Migration | Action |
|------|-----------|-----------|--------|
| Partial unique (student, year) active | Documented | Missing | Operational precondition OP-003 |
| Table count security schema | 8 with audit_logs | 4 enrollment + security tables | Blueprint updated in 3.10.1 |
