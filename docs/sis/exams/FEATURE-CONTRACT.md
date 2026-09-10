# Exams Feature Contract (Phase 3B.1)

**Bounded context:** `Exams`  
**Folders:** `app/Domain/Exams`, `app/Application/Exams`, `app/Infrastructure/Persistence/Exams`

## Allowed

- CQRS Commands/Handlers/Results for Enter / Correct / Void / Finalize
- Queries/Handlers + `StudentGradeDTO`
- Domain events + Outbox staging inside UnitOfWork
- Shared `IdempotencyStore`, `SchoolContext`, `SecurityAuditLoggerInterface`
- Repository ports in Domain/Application; Eloquent adapters in Infrastructure
- Thin `GradeController` + FormRequests + `GradePolicy`

## Forbidden

- God `GradeService`
- Business logic in controllers / React UI (3B.1 has no UI)
- Direct Infrastructure imports from Domain
- Phase 3C coupling (`term_results`, `annual_results`, transcripts, GPA, ranking)
- Hard-delete APIs/commands
- Trusting client `max_score` / `school_id` / denormalized identity fields
- Duplicate audit/outbox/idempotency buses

## Validation

```bash
php artisan architecture:feature-check Exams
php artisan architecture:validate --fitness
php artisan security:validate
```
