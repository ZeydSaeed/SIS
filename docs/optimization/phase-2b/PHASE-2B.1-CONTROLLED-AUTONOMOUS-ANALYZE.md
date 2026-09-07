# Phase 2B.1 — Controlled Autonomous ANALYZE

**Date:** 2026-09-07  
**Prerequisite:** Phase 2B.0 Final Closure — **PASS**

---

## Objective

Validate **controlled autonomous ANALYZE** under a strictly bounded safety policy.

This phase does **not** authorize production autonomous optimization or new autonomous action types.

---

## Scope

| In scope | Out of scope |
|----------|--------------|
| ANALYZE only | CREATE/DROP INDEX, REINDEX, VACUUM, schema changes |
| Controlled test environment | Production autonomous activation |
| Explicit allowlist | Wildcards, arbitrary SQL |
| Real PostgreSQL execution via `SafeAutoExecutor` | `TestAnalyzeOperation` for autonomous path |
| Synthetic anomaly triggers (deterministic fixtures) | Production telemetry claims |

---

## Controlled Environment

Autonomous ANALYZE requires **both**:

```env
OPTIMIZATION_MODE=autonomous
```

and membership in:

```php
config('optimization.autonomous.controlled_environments')
```

PostgreSQL validation suite sets:

```php
'app.env' => 'testing'
'optimization.autonomous.controlled_environments' => ['testing']
'optimization.analyze.allowed_targets' => ['intelligence.optimization_validation_target']
```

Production default remains:

```env
OPTIMIZATION_MODE=observe
```

Production is **not** listed in `controlled_environments` by default.

---

## Execution Path

```text
RunSelfHealingCycleJob
  → SelfHealingPerformanceEngine
  → AutonomousExecutionPolicy.evaluate()     [20 gates, fail-closed]
  → CheckpointService.create()
  → OptimizationEngine.runForIncident()
  → IsolatedOptimizationRunner
  → AnalyzeTableOperation
  → SafeAutoExecutor
  → REAL PostgreSQL ANALYZE
  → CrossMetricGuard
  → StabilizationMonitor
  → Learning + audit events
```

---

## Key Implementation

| Component | Role |
|-----------|------|
| `AutonomousExecutionPolicy` | Unified autonomous gate evaluation + denial codes |
| `AnalyzeTargetPolicy` | Allowlist + identifier sanitization + quoted SQL |
| `SafeAutoExecutor` | Real ANALYZE execution with timeout + fail-closed |
| `CheckpointRecoveryService` | S21 ambiguous execution — no blind retry |
| `CrossMetricGuard` | Post-execution regression detection |
| `StabilizationMonitor` | Deferred success classification |

---

## Synthetic vs Real

| Layer | Type |
|-------|------|
| Telemetry degradation (`FakeTelemetryCollector`) | **Synthetic anomaly trigger** |
| RCA + recommendation matching | Deterministic test fixtures |
| `SafeAutoExecutor` → PostgreSQL | **Real database execution** |
| `pg_stat_user_tables.last_analyze` | **Real execution evidence** |

---

## Human Gate

Phase 2B.1 completion does **not** enable production autonomous mode. Await explicit human approval before Phase 2B.2 or production changes.
