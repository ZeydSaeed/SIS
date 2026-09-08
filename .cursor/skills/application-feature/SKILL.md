---
name: application-feature
description: >-
  Mandatory workflow when creating ANY application feature — controller, service,
  model, use case, command, query, domain class, job, or listener in SIS.
  Enforces Clean Architecture, DDD, CQRS, and ARCHITECTURE-STACK.md automatically.
---

# Application Feature — Mandatory Workflow

> Every new feature MUST follow this workflow. No business logic in controllers. No Laravel in Domain.

## Step 0 — Read (before writing code)

| File | When |
|------|------|
| `.cursor/architecture/ARCHITECTURE-STACK.md` | Always |
| `.cursor/architecture/ARCHITECTURE-STACK.md` | Always |
| `.cursor/architecture/laravel-architecture.md` | Legacy reference only — do not follow Service-first patterns |
| `.cursor/rules/clean-architecture.mdc` | Always |
| `.cursor/rules/architecture-governance.mdc` | Always — no Laravel-style features |
| `.cursor/architecture/FEATURE-DONE.md` | Definition of Done checklist |
| `.cursor/architecture/database-blueprint.md` | If touching DB/models |
| `.cursor/skills/database-change/SKILL.md` | If migration needed |

## Step 1 — Classify the feature

| Type | Create |
|------|--------|
| **Write** (create/update/delete) | Command + Handler + Result + Domain event (via Outbox) |
| **Read** (list/show/report) | Query + Handler + DTO |
| **Domain entity** | Entity with encapsulated behavior (`canEnroll()`, `activate()`) |
| **Value Object** | `Domain/Shared/ValueObjects/` or context-specific |
| **Domain rule** | Specification or Domain service |
| **Persistence** | Repository interface (Domain) + Eloquent adapter (Infrastructure) |
| **HTTP** | Controller + Form Request + Policy |
| **Async** | Job implementing ShouldQueue |
| **Auth** | Policy + middleware scope |

## Step 2 — Scaffold (use generators)

```bash
# Full bounded context skeleton
php artisan sis:make-feature {Context}
# Example: php artisan sis:make-feature Teacher --command=RegisterTeacher --query=GetTeacherProfile

# Write use case
php artisan sis:make-command {Context} {Name}
# Example: php artisan sis:make-command Enrollment EnrollStudent

# Read use case
php artisan sis:make-query {Context} {Name}
# Example: php artisan sis:make-query Student GetStudentProfile
```

**Short user prompt is enough** (e.g. "أنشئ Feature تسجيل طالب") — Rules + this skill enforce architecture. CI validates via `architecture:validate --fitness`.

## Step 3 — Layer checklist

```
[ ] Domain has zero Illuminate/Application imports
[ ] Domain Entity encapsulates behavior (not anemic data bag)
[ ] Controller only authorizes + delegates to handler
[ ] Handler returns Result object (not bare bool/int) for writes
[ ] DTOs in Application/{Context}/DTOs/ for read responses
[ ] Domain events staged via OutboxRepository inside transaction
[ ] Sensitive commands accept idempotencyKey
[ ] academic_year_id + school_id on academic operations
[ ] Transaction owned by Handler via UnitOfWork only
[ ] Heavy ops dispatched to queue — not HTTP
[ ] Repository interface in Domain/Application port, implementation in Infrastructure
[ ] architecture:validate --fitness passes
[ ] architecture:graph shows zero violations
```

## Step 4 — Wire DI

Register bindings in `app/Providers/ArchitectureServiceProvider.php`:

```php
$this->app->bind(StudentRepositoryInterface::class, EloquentStudentRepository::class);
```

## Step 5 — Test

- Unit test: Domain entities, specifications, value objects
- Unit test: Handler with mocked ports
- Feature test: HTTP endpoint (if applicable)
- Run: `php artisan architecture:validate`

## Anti-Patterns (reject)

- Controller with `DB::` or `Model::create`
- Domain importing Eloquent or Application DTOs
- Application handler importing Http Request or Eloquent models
- God handler with validation + DB + notifications + audit
- Direct `event()` dispatch inside transaction — use Outbox
- Repository wrapping every model without need
- CQRS on trivial CRUD with no performance need

## Bounded Contexts (folder names)

`Student`, `Enrollment`, `Attendance`, `Exams`, `Grades`, `School`, `Teacher`, `Curriculum`, `Document`, `Report`, `Security`, `Shared`
