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
| `.cursor/architecture/laravel-architecture.md` | Always |
| `.cursor/rules/clean-architecture.mdc` | Always |
| `.cursor/architecture/database-blueprint.md` | If touching DB/models |
| `.cursor/skills/database-change/SKILL.md` | If migration needed |

## Step 1 — Classify the feature

| Type | Create |
|------|--------|
| **Write** (create/update/delete) | Command + Handler + optional Domain event |
| **Read** (list/show/report) | Query + Handler + DTO |
| **Domain rule** | Specification or Domain service |
| **Persistence** | Repository interface (Domain) + Eloquent adapter (Infrastructure) |
| **HTTP** | Controller + Form Request + Policy |
| **Async** | Job implementing ShouldQueue |
| **Auth** | Policy + middleware scope |

## Step 2 — Scaffold (use generators)

```bash
# Write use case
php artisan sis:make-command {Context} {Name}
# Example: php artisan sis:make-command Enrollment EnrollStudent

# Read use case
php artisan sis:make-query {Context} {Name}
# Example: php artisan sis:make-query Student GetStudentProfile
```

Then implement Domain logic and Infrastructure bindings.

## Step 3 — Layer checklist

```
[ ] Domain has zero Illuminate imports
[ ] Controller only authorizes + delegates to handler
[ ] academic_year_id + school_id on academic operations
[ ] Transaction via UnitOfWork or single handler transaction
[ ] Heavy ops dispatched to queue — not HTTP
[ ] Policy registered for new resource
[ ] Repository interface in Domain, implementation in Infrastructure
[ ] architecture:validate passes
```

## Step 4 — Wire DI

Register bindings in `app/Providers/ArchitectureServiceProvider.php`:

```php
$this->app->bind(StudentRepositoryInterface::class, EloquentStudentRepository::class);
```

## Step 5 — Test

- Unit test: Domain specifications / value objects
- Feature test: HTTP endpoint (if applicable)
- Run: `php artisan architecture:validate`

## Anti-Patterns (reject)

- Controller with `DB::` or `Model::create`
- Domain importing Eloquent
- God service with 500+ lines
- Repository wrapping every model without need
- CQRS on trivial CRUD with no performance need

## Bounded Contexts (folder names)

`Student`, `Enrollment`, `Attendance`, `Exams`, `Grades`, `School`, `Teacher`, `Curriculum`, `Document`, `Report`, `Security`, `Shared`
