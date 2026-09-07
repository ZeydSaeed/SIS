# System Inventory — SIS

Generated for the Autonomous Optimization Engine. **Do not modify production code during discovery.**

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13, PHP 8.x |
| Frontend | Inertia.js + React (RTL/Arabic) |
| Database | PostgreSQL (source of truth) |
| Cache | Redis (cache only) |
| Queue | Laravel Queue (database driver) |

## Application Structure

```text
app/
├── Architecture/          # Static analysis, fitness gates, dependency graph
├── Domain/                # Entities, Value Objects, Specifications, Events
├── Application/           # Commands, Queries, Handlers, DTOs
├── Infrastructure/        # Repositories, Outbox, Idempotency
├── Intelligence/          # Database Guardian, Rule Engine, Self-Healing
├── Optimization/          # Observe → Recommend → Autonomous engine
├── Http/Controllers/      # Thin controllers → Application handlers
└── Models/                # Legacy Eloquent (new features use Infrastructure repos)
```

## Major Modules (implementation order)

organization → academic → security → students → enrollment → curriculum → teachers → attendance → exams → lifecycle

## Intelligence Pipeline

```text
DatabaseGuardian
  → DatabaseMonitor, QueryMonitor, PgStatStatementsCollector
  → ThresholdEngine → RuleEngine → Recommendations
  → SafeAutoExecutor (Tier 1: ANALYZE only)
  → SelfHealingEngine (cache flags)
  → ApprovalGate + /intelligence/recommendations UI
```

## Optimization Domains (monitored)

CPU, Memory, Cache, Database, Disk, Network, Concurrency, I/O, Algorithms, API, Serialization, UI (Inertia/React), Startup, Logging, Background jobs, Scalability.

## Database

- **89 tables** — see `database-blueprint.md`
- Intelligence schema: `intelligence.*` (detections, recommendations, optimization_events, baseline_snapshots)
- RLS + `SchoolContextMiddleware` for multi-tenant security

## Background Jobs

| Job | Schedule |
|-----|----------|
| RunHealthMonitorJob | every minute |
| RunPerformanceAnalysisJob | every 5 minutes |
| RunGrowthOptimizationJob | hourly |
| RunSchemaGuardianJob | daily |
| RunOptimizationObserveJob | every 5 minutes (observe mode) |
| CaptureBaselineSnapshotJob | weekly |
| ProcessOutboxJob | every minute |

## Tests

- `tests/Architecture/` — architecture fitness gates
- `tests/Unit/Optimization/` — optimization scoring and gates
- `composer test` runs `architecture:validate --fitness`

## External Integrations

None in baseline — future: SMS, payment gateways (idempotency required).

## Governance Locks (must not bypass)

- `architecture:validate --fitness`
- `config/intelligence.auto_execute_max_tier` = 1
- `ARCHITECTURE-BASELINE.json` intelligence_governance
- `OptimizationMode::Observe` default — no auto code changes
