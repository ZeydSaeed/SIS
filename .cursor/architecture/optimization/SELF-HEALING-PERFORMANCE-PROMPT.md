# SIS Autonomous Self-Healing Performance Engine

Master prompt — built **on top of** `OptimizationEngine` + Intelligence Layer.

## Mission

Transform SIS into a continuously running, self-monitoring, self-diagnosing, and self-healing performance system that:

- Detects degradation against **adaptive, context-aware baselines**
- Localizes root cause with **confidence scores**
- Applies **ONE smallest safe change** at a time
- Validates with tests + cross-metric guards
- **Rollbacks** on regression
- **Never changes application behavior**

## Governing Flow

```text
OBSERVE → MEASURE → BASELINE → DETECT → LOCALIZE → DIAGNOSE
→ ASSESS IMPACT → CHECKPOINT → APPLY ONE CHANGE → TEST → BENCHMARK
→ CROSS-METRIC VALIDATION → ACCEPT OR ROLLBACK → STABILIZE → LEARN
```

## Absolute Rule

**Functional behavior is the protected invariant.**

Performance improvement is NEVER more important than correctness, data integrity, security, or concurrency semantics.

## Background Operation (24/7)

```text
Scheduler (every 5 min)
    → RunSelfHealingCycleJob
        → SelfHealingPerformanceEngine::runCycle()
            → Telemetry (lightweight)
            → Adaptive Baseline update
            → Health Score
            → Anomaly Detection (hysteresis)
            → [if degraded] Root Cause Analysis
            → [if autonomous + safe] IsolatedOptimizationRunner
            → Stabilization Monitor
```

Optional dedicated worker:

```bash
php artisan optimization:worker --once
php artisan optimization:worker   # loop with safe max execution time
```

## Key Components

| Component | Purpose |
|-----------|---------|
| `SelfHealingPerformanceEngine` | Main orchestrator |
| `AdaptiveBaselineEngine` | Context-aware baselines + anti-poisoning |
| `EnvironmentProfileService` | CPU/RAM/OS fingerprint |
| `TelemetryCollector` | Lightweight metrics |
| `HealthScoreEngine` | Multi-dimensional health |
| `AnomalyDetector` | Hysteresis (N consecutive observations) |
| `RootCauseAnalyzer` | Causal correlation + confidence |
| `CircuitBreaker` | Safe mode after N failures |
| `OptimizationCooldownManager` | Anti-thrashing per target |
| `OptimizationTargetLock` | One optimization per target |
| `CheckpointService` | Pre-change checkpoint |
| `StabilizationMonitor` | Post-accept observation window |
| `RollbackCoordinator` | Automatic + manual rollback |

## Commands

```bash
php artisan optimization:status
php artisan optimization:health
php artisan optimization:baseline [--capture]
php artisan optimization:history
php artisan optimization:rollback {historyId} [--reset-safe-mode]
php artisan optimization:worker [--once]
php artisan optimization:observe      # manual observe (legacy)
php artisan optimization:recommend
php artisan optimization:run
```

## Safety Mechanisms

1. **Default mode:** `observe` — no auto modification
2. **Hysteresis:** 3 consecutive degraded observations (configurable)
3. **Baseline anti-poisoning:** degraded state never becomes new normal immediately
4. **Circuit breaker:** 3 failures → SAFE MODE (observe only)
5. **Cooldown:** 60 min per target after optimization
6. **Target lock:** prevents concurrent optimization on same component
7. **Stabilization window:** 30 min post-accept monitoring
8. **Failure containment:** try/catch — optimizer crash ≠ app crash

## Config (.env)

```env
OPTIMIZATION_MODE=observe
OPTIMIZATION_HYSTERESIS_OBSERVATIONS=3
OPTIMIZATION_COOLDOWN_MINUTES=60
OPTIMIZATION_MAX_FAILED_ATTEMPTS=3
OPTIMIZATION_STABILIZATION_MINUTES=30
OPTIMIZATION_MIN_ROOT_CAUSE_CONFIDENCE=0.75
OPTIMIZATION_ROLLBACK_ENABLED=true
```

## Reports

`.cursor/architecture/optimization/`:

- `HEALTH-STATUS.md`
- `PERFORMANCE-BASELINE.md`
- `ROOT-CAUSE-REPORT.md`
- `SELF-HEALING-STATUS.md`
- `BOTTLENECK-REPORT.md`

## What Autonomous Mode Can Do Today

Tier-1 DB actions via `SafeAutoExecutor` (ANALYZE only).

Application code changes remain **recommend + human approval** until explicit code-patch workflow is added.

## Final Rule

> SELF-MONITOR → SELF-DIAGNOSE → SELF-OPTIMIZE → SELF-VALIDATE → SELF-ROLLBACK → SELF-LEARN

while preserving original application behavior as an **immutable functional contract**.

See also: [ADR-019](../adr/ADR-019-self-healing-performance-engine.md)
