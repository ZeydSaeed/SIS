# Phase 3.0 — Discovery & Architecture Baseline

**Date:** 2026-09-07  
**Git Commit:** `301887d` (Phase 2A–2B.2)  
**Previous Gate:** Phase 2B.2 — PASS WITH CONDITIONS (88/100)  
**Phase 3 Status:** Discovery complete — **no implementation started**

---

## Executive Summary

SIS is a **Laravel 13 + Inertia/React** application with a **mature Intelligence/Optimization safety stack** (Phase 2A–2B.2) but **minimal business application surface**. PostgreSQL schema design exists (~61 migrated tables across 24 schemas), Clean Architecture scaffolding is in place (Domain/Application/Infrastructure), and a partial **Enrollment** vertical exists — but there is **no Student CRUD API**, **no `routes/api.php`**, and **default runtime uses SQLite + database cache/queue** rather than the production-target PostgreSQL + Redis stack.

Phase 3 should build the **first real SIS vertical slice (Students)** and connect **real application telemetry** to the existing Intelligence layer — without enabling production autonomous optimization.

---

## A. Application Stack (Verified)

| Component | Implemented | Evidence |
|-----------|-------------|----------|
| Laravel | **13.30.1** | `php artisan --version`, `composer.lock` |
| PHP | **8.4.16** (requires ^8.3) | `php -v`, `composer.json` |
| PostgreSQL | Configured, **not default** | `config/database.php`, `.env.example` (commented) |
| SQLite | **Default dev/CI** | `DB_CONNECTION=sqlite` in `.env.example`, `phpunit.xml` |
| Redis | Configured, **not default** | `config/database.php` — cache/queue use `database` |
| Queue | **database** (default) | `config/queue.php`, `.env.example` |
| Cache | **database** (default) | `config/cache.php` |
| Session | database | `.env.example` |
| Frontend | **Inertia 3 + React 19 + Vite 8 + Tailwind 4** | `package.json`, `config/inertia.php` |
| Auth | **Laravel Fortify** + 2FA + Passkeys | `config/fortify.php`, migrations |
| Testing | PHPUnit 12, Larastan, Pint | `phpunit.xml`, `composer.json` scripts |
| Architecture validation | **9/9 PASS** | `php artisan architecture:validate --fitness` |
| Docker / Sail | Sail in devDeps, **no compose file** | `composer.json` — no `docker-compose.yml` |
| Scheduler | **Active when flags enabled** | `routes/console.php` |
| API layer | **Not implemented** | No `routes/api.php`; web + Inertia only |

### Environment Files

| File | Notes |
|------|-------|
| `.env.example` | SQLite default; PG commented; Intelligence vars present; **no OPTIMIZATION_* vars** |
| `.env` | Present (not inspected — secrets) |

---

## B. Existing Intelligence Inventory

### Optimization Layer (`app/Optimization/` — 60 files)

| Component | Location | Status |
|-----------|----------|--------|
| SelfHealingPerformanceEngine | `SelfHealing/SelfHealingPerformanceEngine.php` | Implemented |
| AutonomousExecutionPolicy | `SelfHealing/AutonomousExecutionPolicy.php` | 14+ gates, fail-closed |
| AutonomousKillSwitch | `SelfHealing/AutonomousKillSwitch.php` | Phase 2B.2 |
| AutonomousRateLimiter | `SelfHealing/AutonomousRateLimiter.php` | Phase 2B.2 |
| AnalyzeTargetPolicy | `Execution/AnalyzeTargetPolicy.php` | Allowlist + sanitization |
| SafeAutoExecutor | `Intelligence/Optimization/SafeAutoExecutor.php` | Real PG ANALYZE |
| AnalyzeTableOperation | `Execution/AnalyzeTableOperation.php` | Only registered op |
| TelemetryCollector | `SelfHealing/TelemetryCollector.php` | Reads intelligence tables + providers |
| AdaptiveBaselineEngine | `SelfHealing/AdaptiveBaselineEngine.php` | Context-aware |
| AnomalyDetector | `SelfHealing/AnomalyDetector.php` | Hysteresis + thresholds |
| RootCauseAnalyzer | `SelfHealing/RootCauseAnalyzer.php` | Incident generation |
| IncidentRecommendationResolver | `SelfHealing/IncidentRecommendationResolver.php` | Match rec ↔ incident |
| RecommendationRanker | `SelfHealing/RecommendationRanker.php` | Learning-safe ranking |
| CheckpointService / Recovery | `SelfHealing/Checkpoint*.php` | S21 semantics |
| CrossMetricGuard | `Gates/CrossMetricGuard.php` | Post-execution validation |
| StabilizationMonitor | `SelfHealing/StabilizationMonitor.php` | Deferred success |
| CircuitBreaker | `SelfHealing/CircuitBreaker.php` | Safe mode |
| OptimizationTargetLock | `SelfHealing/OptimizationTargetLock.php` | Cache-based |
| SelfHealingLearningRecorder | `SelfHealing/SelfHealingLearningRecorder.php` | Ranking/confidence only |

**Config:** `config/optimization.php` — default `mode=observe`, `autonomous_actions=[analyze]`, fail-closed allowlist.

### Intelligence Layer (`app/Intelligence/` — 39 files)

| Component | Location | Role |
|-----------|----------|------|
| DatabaseGuardian | `Guardian/DatabaseGuardian.php` | Health + performance cycles |
| SelfHealingEngine | `SelfHealing/SelfHealingEngine.php` | **Ops Tier-1 only** (replica lag, connections) |
| OpsSelfHealingSafetyGate | `SelfHealing/OpsSelfHealingSafetyGate.php` | Separate from optimization path |
| RuleEngine | `Expert/RuleEngine.php` | Recommendation rules |
| ConfidenceEngine | `Learning/ConfidenceEngine.php` | Scoring |
| DatabaseMonitor / QueryMonitor | `Monitoring/` | PG stats collection |
| Models | `Models/` | 10 Eloquent models for intelligence schema |

**Config:** `config/intelligence.php` — enabled by default, max tier 1, forbidden auto-actions list.

---

## C. Execution Paths (Verified — Single Optimization Path)

### Primary Autonomous Path (Optimization)

```text
Schedule: RunSelfHealingCycleJob (every 5 min)
  → SelfHealingPerformanceEngine::runCycle()
  → CheckpointRecoveryService::recoverIncomplete()
  → TelemetryCollector::collect()
  → AdaptiveBaselineEngine::loadCompatible()
  → AnomalyDetector::detect()
  → RootCauseAnalyzer::analyze()
  → AutonomousExecutionPolicy::evaluate()  [kill switch, env, rate limit, allowlist, ...]
  → CheckpointService::create()
  → OptimizationEngine::runForIncident()
  → IncidentRecommendationResolver::resolve()
  → IsolatedOptimizationRunner::run()
  → AnalyzeTableOperation::apply()
  → SafeAutoExecutor::attempt()
  → PostgreSQL ANALYZE
  → CrossMetricGuard::evaluate()
  → StabilizationMonitor::start()
  → SelfHealingLearningRecorder::record()
```

**No bypass found** in code review. Controllers do not invoke optimization directly.

### Separate Ops Path (Infrastructure)

```text
Schedule: RunHealthMonitorJob (every minute)
  → DatabaseGuardian::runHealthCycle()
  → SelfHealingEngine::evaluate()  [OpsSelfHealingSafetyGate — NOT ANALYZE]
```

These paths are **independently governed** and do not execute schema/ANALYZE optimization.

### Intelligence Analysis Path (Observe/Recommend)

```text
RunPerformanceAnalysisJob → DatabaseGuardian::runPerformanceCycle()
  → RuleEngine → Recommendations (pending)
```

Human approval via `RecommendationController` (Inertia UI).

### Telemetry Source Today

`TelemetryCollector` reads:
- `intelligence.monitoring_snapshots` (from health cycles)
- `intelligence.query_metrics` (from query monitoring)
- `ErrorRateTelemetryProvider` / `QueueTelemetryProvider`
- Environment profile fingerprint

**Gap:** No HTTP request latency telemetry from real Student API workload yet — query metrics depend on `QueryPerformanceListener` firing on actual queries.

---

## D. Database Inventory

### Migrations: 20 files

| Category | Count | Notes |
|----------|-------|-------|
| Laravel infra | 4 | users, cache, jobs, passkeys |
| PG schemas | 1 | 24 schemas |
| Core SIS | 8 | organization → attendance |
| Intelligence | 2 | tables + pg_stat_statements |
| Architecture | 1 | outbox + idempotency |
| RLS + MVs | 2 | enrollment, attendance RLS; reports MVs |
| Validation | 1 | `intelligence.optimization_validation_target` |

### Schemas (24)

`organization`, `academic`, `vocational`, `students`, `guardians`, `enrollment`, `teachers`, `curriculum`, `timetable`, `attendance`, `exams`, `results`, `promotion`, `transfers`, `graduation`, `certificates`, `documents`, `finance`, `communication`, `workflow`, `security`, `audit`, `reports`, `intelligence`

### Tables Migrated (~61)

Core domains through **attendance** are migrated. Schemas without tables yet: `exams`, `results`, `promotion`, `transfers`, `graduation`, `certificates`, `documents`, `finance`, `communication`, `workflow`.

### Blueprint Drift

| Source | Table Count |
|--------|-------------|
| `database-blueprint.md` | 86–89 (authoritative per AGENTS.md) |
| Migrations executed | ~61 |
| Gap | ~25–28 tables not yet migrated |

### Student Domain (DB Ready)

Migration `2026_09_05_100400_create_students_and_guardians_tables.php`:
- `students.students` — full column set with indexes
- `students.student_contacts`, `student_addresses`
- `guardians.*` — related tables

**Missing vs blueprint:** `students.student_documents` not migrated.

### PostgreSQL Features in Use

- Multi-schema (`SchemaHelper`)
- RLS on `enrollment.enrollments`, `attendance.records`
- Partitioned `attendance.records` (LIST by academic_year_id)
- Materialized views in `reports` schema
- `pg_stat_statements` extension
- Identity columns pattern in blueprint (migrations use `$table->id()`)

---

## E. Application Layer (Business Features)

### What Exists

| Feature | Layer | Status |
|---------|-------|--------|
| Student domain entity | `Domain/Student/` | Entity, VO, specs |
| Enrollment command | `Application/Enrollment/` | EnrollStudent with idempotency + outbox |
| Student read repo | `Infrastructure/Persistence/Student/` | Eloquent adapter |
| Intelligence UI | `Http/Controllers/Intelligence/` | Recommendations CRUD + approve/reject |
| Auth / Settings | Fortify + settings controllers | Working |
| Dashboard | Inertia page | Placeholder |

### What Does NOT Exist

| Gap | Impact on Phase 3 |
|-----|-------------------|
| Student CRUD API/Controller | **Primary Phase 3.3 deliverable** |
| Student create/update commands | Need Application handlers |
| `routes/api.php` | Need API foundation |
| Real HTTP workload for telemetry | Blocks Phase 3.4–3.5 |
| Redis as default cache/queue | Blocks Phase 3.1 foundation target |
| Environment-specific `.env` profiles | staging/production separation |
| Phase 3 documentation | This discovery doc is first |

---

## F. Testing Inventory

| Suite | Location | Count | Default Driver |
|-------|----------|-------|----------------|
| Unit | `tests/Unit/` | ~25 | SQLite in-memory |
| Feature | `tests/Feature/` | ~18 | SQLite in-memory |
| Architecture | `tests/Architecture/` | 3 | N/A |
| PG Optimization | `tests/Feature/Optimization/PostgreSql/` | 42 tests | Requires PostgreSQL |

**Commands verified:**
```bash
composer test                              # lint + phpstan + architecture + phpunit
php artisan test --filter=Optimization     # 62 pass, 42 skip (PG)
php artisan test -c phpunit.optimization-pgsql.xml  # 42 pass
php artisan architecture:validate --fitness  # 9/9 PASS
```

---

## G. Architecture Risks

### P0 — Must Address Before / During Phase 3 Foundation

| ID | Risk | Evidence | Phase Impact |
|----|------|----------|--------------|
| P0-1 | **SQLite default vs PostgreSQL production** | `.env.example`, `phpunit.xml`, `config/database.php` | RLS, partitions, ANALYZE, pg_stat untested in default CI |
| P0-2 | **Redis not default for cache/queue** | `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | Phase 3.1 requires Redis; ADR/capacity plan assumes Redis |
| P0-3 | **No real application workload** | No Student API; telemetry from intelligence snapshots only | Phase 3.4–3.5 blocked without vertical slice |
| P0-4 | **Scheduler jobs enabled by default** | `INTELLIGENCE_ENABLED=true`, `OPTIMIZATION_ENABLED=true` | Background load in local without explicit opt-out |
| P0-5 | **Blueprint/migration gap (~28 tables)** | 61 vs 89 tables | Not blocking first slice, but blocks full SIS |

### P1 — High (Address in Phase 3, Non-Blocking for Slice Start)

| ID | Risk | Evidence |
|----|------|----------|
| P1-1 | No Docker/reproducible staging | Sail dep only, no compose |
| P1-2 | `.env.example` missing OPTIMIZATION_* vars | Safe defaults in config, but deployers uninformed |
| P1-3 | `WORK-PLAN.md` stale ("no migrations") | Governance doc drift |
| P1-4 | `students.student_documents` in blueprint, not migrated | Schema drift |
| P1-5 | Attendance partition DEFAULT only | Year partitions not seeded |
| P1-6 | HTTP request latency not in TelemetryCollector | Only query p95 from `query_metrics` |

### P2 — Medium

| ID | Risk |
|----|------|
| P2-1 | No API versioning convention |
| P2-2 | Dashboard shows no health/telemetry yet |
| P2-3 | Production readiness checklist all unchecked |

### P3 — Low

| ID | Risk |
|----|------|
| P3-1 | Frontend RTL/Arabic not verified in discovery |
| P3-2 | No OpenAPI/Swagger for API |

---

## H. Production Autonomous Policy (Verified Unchanged)

| Control | Status | Evidence |
|---------|--------|----------|
| Default mode | `observe` | `config/optimization.php:16` |
| Autonomous actions | `analyze` only | `OptimizationOperationRegistry`, config |
| Production env gate | **BLOCK** unless in `controlled_environments` | `AutonomousExecutionPolicy::isControlledEnvironment()` |
| Allowlist | Fail-closed empty default | `optimization.analyze.allowed_targets = []` |
| Kill switch | Available, default OFF | `OPTIMIZATION_AUTONOMOUS_KILL_SWITCH=false` |
| Accidental `mode=autonomous` in production | **BLOCK** (env gate) | Phase 2B.2 tests |

**Production Autonomous = NOT AUTHORIZED** — unchanged from Phase 2B.2.

---

## I. Proposed Phase 3 Vertical Slice

### Recommendation: **Students — CRUD + List/Search**

**Rationale:**
1. `students.students` table **already migrated** on PostgreSQL
2. Domain entity + value objects **already exist** (`Domain/Student/`)
3. Read repository **already exists** (used by Enrollment)
4. Highest business value with lowest architectural risk
5. Generates real DB query workload for telemetry integration

### Proposed Scope (Phase 3.3)

```text
CreateStudent  → Application Command + Handler
UpdateStudent  → Application Command + Handler
ListStudents   → Application Query + Handler (pagination, filter by status/code)
GetStudent     → Application Query + Handler
HTTP API       → routes/api.php (or web API prefix) — thin controllers
Validation     → Form requests / command validation
Tests          → Feature (API) + Unit (domain/specs)
Authorization  → SchoolContext + existing security middleware if applicable
```

**Out of scope for Phase 3:** guardians UI, enrollment UI, documents, full 89-table build.

---

## J. Proposed Phase 3 File Plan (Preview — Not Implemented)

| Phase | Files / Areas |
|-------|---------------|
| 3.1 Foundation | `.env.example` profiles, Redis defaults for staging, env documentation |
| 3.2 Database | Verify PG migrations on dev; seed minimal student data |
| 3.3 Vertical Slice | `app/Application/Student/*`, `app/Http/Controllers/Student/*`, `routes/api.php`, tests |
| 3.4 Telemetry | Extend `TelemetryCollector` / listeners for HTTP + real query capture |
| 3.5 Integration | Wire real workload → baseline → anomaly pipeline |
| 3.6 Validation | PG workload tests + intelligence E2E with real telemetry |
| 3.7–3.8 | Tests, docs, gate report |

---

## K. Phase 3 Go / No-Go

| Gate | Decision |
|------|----------|
| P0 blockers prevent discovery? | **NO** — discovery complete |
| P0 blockers prevent 3.1 start? | **PARTIAL** — P0-1/P0-2 must be addressed in 3.1 foundation |
| Safe to start 3.1 Application Foundation? | **YES** with conditions |
| Safe to start 3.3 before 3.1–3.2? | **NO** |
| Production autonomous? | **BLOCKED** |

### Recommended Next Step

**Proceed to Phase 3.1 — Application Foundation** after human approval of:
1. Students as vertical slice
2. PostgreSQL + Redis as required dev/staging defaults
3. File plan above

---

## L. Evidence References

| Claim | Evidence |
|-------|----------|
| Laravel 13.30.1 | `php artisan --version` (2026-09-07) |
| Architecture 9/9 | `php artisan architecture:validate --fitness` |
| Phase 2B.2 gate | `docs/optimization/phase-2b/PHASE-2B.2-GATE-REPORT.md` |
| 20 migrations | `database/migrations/` glob |
| No api.php | `bootstrap/app.php`, routes directory |
| Telemetry sources | `app/Optimization/SelfHealing/TelemetryCollector.php` |
| Single optimization path | Code trace + `OptimizationServiceProvider` registry |

---

## M. Human Approval Required

Phase 3.0 Discovery is **complete**.

**STOP** — awaiting approval before Phase 3.1 implementation.

```text
PHASE 3.0 COMPLETE
STATUS: DISCOVERY COMPLETE
PRODUCTION AUTONOMOUS: DISABLED
PROPOSED SLICE: Students CRUD + List
P0 BLOCKERS FOR 3.1: SQLite default, Redis not default, no real workload
NEXT ACTION: WAITING FOR HUMAN APPROVAL
```
