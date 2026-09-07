# ADR-019: Self-Healing Adaptive Performance Engine

## Status

Accepted — 2026-09-07

## Context

ADR-018 introduced `OptimizationEngine` with observe/recommend/autonomous modes and manual artisan commands. The user requires:

1. **Continuous background monitoring** — not manual `optimization:observe` per cycle
2. **Adaptive context-aware baselines** — not hard-coded numbers
3. **Stabilization window + anti-thrashing + circuit breaker + environment profiles**
4. **Self-heal with rollback** — without changing application behavior
5. **Failure containment** — optimization failure must not crash the app

## Decision

Extend `app/Optimization/SelfHealing/` as an orchestration layer **on top of** `OptimizationEngine`:

```text
SelfHealingPerformanceEngine
  → TelemetryCollector (lightweight)
  → AdaptiveBaselineEngine (anti-poisoning)
  → HealthScoreEngine
  → AnomalyDetector (hysteresis)
  → RootCauseAnalyzer (confidence)
  → [gates] CircuitBreaker, Cooldown, TargetLock, Checkpoint
  → OptimizationEngine::runAutonomous() (Tier-1 only)
  → StabilizationMonitor + RollbackCoordinator
```

**Scheduler:** `RunSelfHealingCycleJob` every 5 minutes replaces observe-only job.

**Worker:** `optimization:worker` for dedicated loop with max execution time.

**Safe defaults preserved:** `OPTIMIZATION_MODE=observe`, circuit breaker → safe mode.

## Consequences

### Positive

- 24/7 monitoring path without developer intervention
- Baseline adapts to environment/hardware/data growth without poisoning
- Thrashing prevented via hysteresis + cooldown + target locks
- Post-accept stabilization with automatic rollback

### Limits

- CPU/memory/GC profiling awaits Phase 2 Observability (Prometheus)
- Autonomous code patches not implemented — DB ANALYZE Tier-1 only
- Worker requires Laravel scheduler + queue worker in production

## References

- ADR-018
- `.cursor/architecture/optimization/SELF-HEALING-PERFORMANCE-PROMPT.md`
- `config/optimization.php`
