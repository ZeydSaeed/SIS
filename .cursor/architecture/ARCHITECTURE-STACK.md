# SIS Architecture Stack

> **Purpose:** Authoritative application architecture — enforced by Cursor rules, skills, PHPUnit architecture tests, and `php artisan architecture:validate`.  
> **Principle:** Not slogans — each layer has a job. Apply by priority (P0 → P4).

## Priority Rollout

| Priority | Stack | Status |
|----------|-------|--------|
| **P0** | Clean + Layered + SOLID + DI + Security + RLS | ✅ Foundation |
| **P1** | DDD + UseCase + Repository + Specification + Events | ✅ Foundation |
| **P2** | CQRS + Queue + Redis + Observability | ⏳ Partial |
| **P3** | Intelligence + Simulation + Self-Healing | ✅ Runtime |
| **P4** | Self-Learning adaptive optimization | ⏳ Partial |

## Layer Diagram

```text
┌──────────────── Presentation ────────────────┐
│  Http/Controllers · Form Requests · Inertia  │
├──────────────── Application ─────────────────┤
│  Commands · Queries · UseCases · DTOs        │
├──────────────── Domain ──────────────────────┤
│  Entities · ValueObjects · Specifications  │
│  Domain Events · Policies (pure PHP)         │
├──────────────── Infrastructure ──────────────┤
│  Persistence · Cache · Queue · External      │
├──────────────── Intelligence (cross-cut) ──┤
│  Monitoring · Expert · Self-Healing          │
├──────────────── Security (cross-cut) ────────┤
│  RBAC · Policies · RLS session context       │
└──────────────────────────────────────────────┘
         PostgreSQL · Redis · Queue
```

## Directory Map

```text
app/
├── Domain/                    # NO Laravel · NO Eloquent · NO DB::
│   └── {Context}/             # Student, Enrollment, Attendance, ...
│       ├── Entities/
│       ├── ValueObjects/
│       ├── Specifications/
│       ├── Events/
│       ├── Repositories/      # Interfaces (ports) only
│       └── Services/          # Pure domain services
│
├── Application/               # Use cases · orchestration
│   └── {Context}/
│       ├── Commands/          # Write side (CQRS)
│       ├── Queries/           # Read side (CQRS)
│       ├── DTOs/
│       └── Handlers/
│
├── Infrastructure/            # Adapters (hexagonal)
│   ├── Persistence/
│   ├── Cache/
│   ├── Queue/
│   └── External/
│
├── Intelligence/              # Database Guardian (existing)
├── Security/                  # RLS · tenant context
├── Http/                      # Presentation (Laravel)
├── Models/                    # Legacy Eloquent — migrate to Infrastructure/Persistence
└── Services/                  # Legacy — migrate to Application/
```

## Dependency Rules (Clean + Hexagonal)

| From | May depend on | Must NOT depend on |
|------|---------------|-------------------|
| **Domain** | Domain, PHP std | Laravel, Eloquent, HTTP, DB, Redis |
| **Application** | Domain, Application contracts | Controllers, Blade, Inertia |
| **Infrastructure** | Domain ports, Laravel, Eloquent | Controllers |
| **Http/Controllers** | Application handlers, Form Requests | DB::, Eloquent (direct), business rules |
| **Intelligence** | Infrastructure metrics, config | Domain entities (optional read-only) |

## CQRS (selective — not every module)

**Commands (writes):** `Application\{Context}\Commands\{Name}Command` + `{Name}Handler`  
**Queries (reads):** `Application\{Context}\Queries\{Name}Query` + `{Name}Handler`

Heavy reads → Materialized views / cache (see `normalization-and-cqrs.md`).

## Event-Driven

```text
Domain Event (e.g. StudentEnrolled)
    → Listener: Audit
    → Listener: Cache invalidation
    → Listener: Intelligence metric
    → Job: Notification (async)
```

Dispatch from **Application handler** after successful UnitOfWork commit.

## Patterns in Use

| Pattern | Where |
|---------|-------|
| Repository | Infrastructure implements Domain port |
| Specification | Domain rules (eligibility, validation) |
| Strategy | Grade calculation, Optimization strategies |
| Factory | Reports, complex aggregates |
| Command | Application write handlers |
| Observer | Laravel Events for domain events |
| Unit of Work | `Infrastructure\Persistence\EloquentUnitOfWork` |

## Multi-Tenancy + Security

Every academic operation scoped:

```text
school_id + academic_year_id
```

Two layers:

1. **Laravel:** Policy + `SchoolContextMiddleware` → sets PostgreSQL `app.current_school_id`
2. **PostgreSQL:** RLS on `enrollment.enrollments`, `attendance.records`

## Resilience (P2)

- Idempotency keys on payments, imports, certificates
- Queue for heavy ops — never in HTTP
- Retry on jobs with backoff

## Enforcement (automatic)

| Mechanism | When |
|-----------|------|
| `.cursor/rules/clean-architecture.mdc` | AI edits `app/**` |
| `.cursor/skills/application-feature/SKILL.md` | AI creates feature |
| `php artisan architecture:validate` | Manual + CI |
| `tests/Architecture/*` | PHPUnit on every test run |
| `php artisan sis:make-command` / `sis:make-query` | Scaffolding |

## Related

- [laravel-architecture.md](./laravel-architecture.md)
- [normalization-and-cqrs.md](./normalization-and-cqrs.md)
- [api-conventions.md](./api-conventions.md)
- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
