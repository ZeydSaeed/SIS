# SIS Architecture Stack

> **Purpose:** Authoritative application architecture — enforced by Cursor rules, skills, PHPUnit architecture tests, and `php artisan architecture:validate`.  
> **Principle:** Not slogans — each layer has a job. Apply by priority (P0 → P4).

## Priority Rollout

| Priority | Stack | Status |
|----------|-------|--------|
| **P0** | Clean + Layered + SOLID + DI + Security + RLS | ✅ Foundation |
| **P1** | DDD + UseCase + Repository + Specification + Events + DTOs + Results | ✅ Foundation |
| **P2** | CQRS + Queue + Outbox + Idempotency + Observability | ⏳ Partial |
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
│       ├── DTOs/              # Read/write data carriers (not Eloquent)
│       ├── Results/           # Command outcomes (EnrollStudentResult)
│       └── Contracts/         # Ports for cross-context reads/writes
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
| **Domain** | Domain, PHP std | Laravel, Eloquent, HTTP, DB, Application DTOs |
| **Application** | Domain, Application contracts | Controllers, Http Request, Eloquent models |
| **Infrastructure** | Domain ports, Laravel, Eloquent | Controllers |
| **Http/Controllers** | Application handlers, Form Requests | DB::, Eloquent (direct), business rules |
| **Intelligence** | Infrastructure metrics, config | Domain entities (optional read-only) |

## CQRS (selective — not every module)

**Commands (writes):** `Application\{Context}\Commands\{Name}Command` + `{Name}Handler`  
**Queries (reads):** `Application\{Context}\Queries\{Name}Query` + `{Name}Handler`

Heavy reads → Materialized views / cache (see `normalization-and-cqrs.md`).

## Event-Driven (Transactional Outbox)

```text
Handler (inside UnitOfWork transaction)
    → OutboxRepository::stage(StudentEnrolled)
    → COMMIT
ProcessOutboxJob (scheduled)
    → Laravel bridge event
    → Listeners: Audit · Cache · Intelligence · Notification
```

See [ADR-013-outbox-pattern.md](./adr/ADR-013-outbox-pattern.md).

## DTOs vs Commands vs Results

| Type | Role | Example |
|------|------|---------|
| **Command** | Write intent + input data | `EnrollStudentCommand` |
| **Query** | Read intent | `GetStudentDashboardQuery` |
| **DTO** | Layer-safe data transfer | `EnrollmentDTO`, `StudentSummaryDTO` |
| **Result** | Structured command outcome | `EnrollStudentResult` |
| **Entity** | Business state + behavior | `Student::canEnroll()` |

Commands/Queries are DTO-like carriers; explicit DTOs used for reads and API responses.

## Idempotency

Sensitive commands accept `?string $idempotencyKey`. Stored in `audit.idempotency_keys` with TTL.  
See [ADR-014-idempotency-commands.md](./adr/ADR-014-idempotency-commands.md).

## OOP Requirements

- **Encapsulation:** state changes via entity methods, not public property mutation.
- **Value Objects:** `StudentCode`, `SchoolId`, `AcademicYearId` enforce invariants.
- **Polymorphism:** Specifications compose with `and()` / `or()`.
- **Composition:** Handlers orchestrate — no God classes.
- **Interfaces:** Repository ports in Domain; adapters in Infrastructure.

See [ADR-015-rich-domain-dtos.md](./adr/ADR-015-rich-domain-dtos.md).

## Event-Driven (legacy note)

Dispatch from **Application handler** inside transaction via Outbox — not direct `event()` after commit.

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

- **Idempotency keys** on enrollment, payments, imports, certificates
- **Transactional outbox** for domain events
- Queue for heavy ops — never in HTTP
- Retry on jobs with backoff

## Enforcement (automatic)

| Mechanism | When |
|-----------|------|
| `.cursor/rules/architecture-governance.mdc` | AI creates any feature — **no Laravel-style Model+Controller** |
| `.cursor/rules/clean-architecture.mdc` | AI edits `app/**` |
| `.cursor/skills/application-feature/SKILL.md` | AI creates feature |
| `php artisan sis:make-feature` | Bounded context scaffold |
| `php artisan architecture:validate --fitness` | Manual + CI |
| `php artisan architecture:feature-check {Context}` | Per-feature contract |
| `ARCHITECTURE-BASELINE.json` | Versioned rules (layers, complexity, security) |
| `tests/Architecture/*` | PHPUnit on every test run |

**Source of truth:** Validator + Tests + CI — Cursor is assistant only.

See [FEATURE-DONE.md](./FEATURE-DONE.md) for per-feature checklist.

## Related

- [laravel-architecture.md](./laravel-architecture.md)
- [normalization-and-cqrs.md](./normalization-and-cqrs.md)
- [api-conventions.md](./api-conventions.md)
- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
