# Phase 3 — Application Architecture Foundation

**Phase:** 3.1  
**Date:** 2026-09-07  
**Previous:** Phase 3.0 Discovery complete  
**Status:** Foundation implemented — ready for Phase 3.2 (Database)

---

## Executive Summary

Phase 3.1 establishes the **runtime foundation** for SIS business features: environment profiles, PostgreSQL + Redis as the documented dev/staging/production stack, API routing with correlation ID propagation, scheduler profile gates, and a **hard production block** on autonomous ANALYZE that cannot be bypassed via config misconfiguration.

No Student CRUD or new migrations in this phase — that is Phase 3.2–3.3.

---

## Deliverables

| Item | Location | Purpose |
|------|----------|---------|
| Environment profiles | `config/sis.php` | local/testing/staging/production expectations |
| API foundation | `routes/api.php`, `HealthController` | `/api/v1/health` + correlation ID |
| Bootstrap wiring | `bootstrap/app.php` | API routes + middleware |
| Scheduler gates | `routes/console.php` | Profile-aware intelligence/optimization jobs |
| Production autonomous block | `AutonomousExecutionPolicy` | `production_forbidden` — fail-closed |
| Dev template | `.env.example` | PG + Redis + OPTIMIZATION_* vars |
| Test isolation | `phpunit.xml` | `SIS_ENV_PROFILE=testing`, schedulers off |
| Tests | `HealthEndpointTest`, `SisEnvironmentProfileTest`, policy tests | Foundation verification |

---

## Environment Profiles

```text
SIS_ENV_PROFILE (defaults to APP_ENV)
        │
        ├── local     → pgsql, redis cache/queue, schedulers ON, mode=observe
        ├── testing   → sqlite, array/sync, schedulers OFF
        ├── staging   → pgsql, redis, schedulers ON, mode=observe
        └── production → pgsql, redis, schedulers ON, mode=observe
```

Profile values in `config/sis.php` are **documentation + scheduler defaults**. Database/cache/queue drivers remain env-driven (`DB_CONNECTION`, `CACHE_STORE`, `QUEUE_CONNECTION`) — `.env.example` now recommends PostgreSQL + Redis for local Herd/Valet development.

Override scheduler behavior explicitly:

```env
SIS_SCHEDULER_INTELLIGENCE=false
SIS_SCHEDULER_OPTIMIZATION=false
```

---

## API Foundation

| Route | Method | Response |
|-------|--------|----------|
| `/api/v1/health` | GET | JSON status, version, profile, services, optimization mode |

Middleware: `CorrelationIdMiddleware` on all API routes (prepended). Accepts inbound `X-Correlation-ID` or generates UUID; echoed on response.

Future Student endpoints (Phase 3.3) will follow `api-conventions.md` under `/api/v1/students`.

---

## Production Autonomous Safety

**Hard block** added in Phase 3.1:

```text
OPTIMIZATION_AUTONOMOUS_BLOCK_PRODUCTION=true (default, do not disable in production)
        │
        └── app.env === 'production' → production_forbidden
            (even if production ∈ controlled_environments AND mode=autonomous)
```

Policy evaluation order: kill switch → mode → **production block** → controlled environment → rate limit → allowlist → …

Existing Phase 2B tests remain valid; production isolation now returns `production_forbidden` instead of `environment_unauthorized` when `APP_ENV=production`.

---

## Scheduler Behavior

| Profile | Intelligence jobs | Optimization jobs |
|---------|-------------------|-------------------|
| local | ✅ (if `INTELLIGENCE_ENABLED`) | ✅ (if `OPTIMIZATION_ENABLED`) |
| testing | ❌ (default via profile) | ❌ (default via profile) |
| staging | ✅ | ✅ |
| production | ✅ | ✅ (observe mode only) |

Console schedule registration:

```php
config('intelligence.enabled') && config('sis.scheduler.intelligence_enabled')
config('optimization.enabled') && config('sis.scheduler.optimization_enabled')
```

---

## Recommended Local Setup (Herd/Valet)

1. Copy `.env.example` → `.env`
2. Ensure PostgreSQL database `sis` exists (Herd default)
3. Ensure Redis is running (Herd services)
4. `php artisan migrate`
5. Verify: `GET /api/v1/health` → `status: ok`, `database.connection: pgsql`

CI/fast tests continue using SQLite via `phpunit.xml`. PostgreSQL optimization tests use `phpunit.optimization-pgsql.xml` (unchanged).

---

## What Is NOT in Phase 3.1

- Student CRUD / search (Phase 3.3)
- New migrations or seeds (Phase 3.2)
- Real HTTP workload telemetry (Phase 3.4)
- Intelligence integration with app metrics (Phase 3.5)
- Production canary authorization (Phase 3.8 gate — human approval required)

---

## Next Steps

| Phase | Focus |
|-------|-------|
| **3.2** | PostgreSQL migration verification, minimal org/academic seeds |
| **3.3** | Students vertical slice — Commands/Queries, API, tests |
| **3.4** | Application observability — real request metrics |
| **3.5** | Wire telemetry into Intelligence layer |
| **3.6** | Workload validation against performance budgets |
| **3.7** | Integration + architecture tests |
| **3.8** | Gate report — STOP for human approval ✅ |

See **`PHASE-3.8-GATE-REPORT.md`** for gate decision: **PASS WITH CONDITIONS (82/100)**.

---

## Verification Commands

```bash
php artisan test --filter=HealthEndpoint
php artisan test --filter=SisEnvironmentProfile
php artisan test --filter=AutonomousExecutionPolicy
php artisan architecture:validate --fitness
curl -s http://sis.test/api/v1/health | jq .
```

---

*Phase 3.1 complete. Proceed to Phase 3.2 only after confirming local PG + Redis health check passes.*
