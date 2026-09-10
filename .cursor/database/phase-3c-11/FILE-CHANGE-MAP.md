# PHASE 3C.11 — FILE CHANGE MAP (FUTURE ONLY)

**No files created/modified in application or migrations this phase.**

Classification: CREATE | MODIFY | READ ONLY | DO NOT TOUCH

---

## Database (future implementation phase)

| Path | Type | Purpose | Phase | Class | Risk |
|------|------|---------|-------|-------|------|
| `database/migrations/*_phase3c_graduation_*.php` | migration | M01–M18 | DB | CREATE | med |
| `app/Database/SchemaHelper.php` | php | already has graduation | — | **DO NOT TOUCH** (unless bug) | high if removed |
| `database/migrations/2026_09_10_160000_phase3b_*` | migration | grades LIVE | — | **DO NOT TOUCH** | critical |
| `database/migrations/2026_09_06_110000_*` | migration | outbox/idempotency | — | **DO NOT TOUCH** | critical |

## Application (future)

| Path | Type | Purpose | Class | Risk |
|------|------|---------|-------|------|
| `app/Domain/Graduation/**` | php | domain | CREATE | med |
| `app/Application/Graduation/**` | php | CQRS | CREATE | med |
| `app/Infrastructure/Persistence/Graduation/**` | php | Eloquent | CREATE | med |
| `app/Providers/ArchitectureServiceProvider.php` | php | bind repos | MODIFY | med |
| `app/Http/Controllers/**/Graduation*` | php | optional API | CREATE | med |
| `routes/**` | php | optional | MODIFY | med |
| `app/Domain/Student/ValueObjects/StudentStatus.php` | php | enum exists | **READ ONLY** until sync rules decided | high |
| `app/Domain/Exams/**` | php | grades SSOT | **DO NOT TOUCH** for graduation SSOT | critical |
| `app/Infrastructure/Persistence/Outbox/**` | php | reuse | READ ONLY / minimal MODIFY if needed | high |
| `app/Infrastructure/Persistence/Idempotency/**` | php | reuse | READ ONLY | high |

## Tests (future)

| Path | Class |
|------|-------|
| `tests/Feature/Graduation/**` | CREATE |
| `tests/Unit/Graduation/**` | CREATE |
| `tests/Database/Graduation/**` RLS/trigger | CREATE |

## Documentation

| Path | Class | Notes |
|------|-------|-------|
| `.cursor/database/phase-3c-10/**` | READ ONLY | Authority |
| `.cursor/database/phase-3c-11/**` | CREATE (this phase — docs only) | Planning |
| `.cursor/architecture/database-blueprint.md` | MODIFY later | Mark stale graduation; **after** auth + human — not this phase |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.9*` | READ ONLY | Logical authority |

## Allowed modification (this phase)

| Path | Allowed |
|------|---------|
| `.cursor/database/phase-3c-11/*` | YES — documentation only |
| Everything else listed above | **NO** |
