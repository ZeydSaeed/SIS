# Phase 2A Architecture

## Dual Self-Healing Domains

```text
┌─────────────────────────────────────────────────────────────┐
│                 OPTIMIZATION SELF-HEALING                    │
│  RunSelfHealingCycleJob → SelfHealingPerformanceEngine       │
└─────────────────────────────────────────────────────────────┘
                              │
Telemetry → Baseline (context) → Anomaly → RCA → Recommendation
                              │
              IncidentRecommendationResolver (rank + match)
                              │
              Checkpoint → IsolatedOptimizationRunner (ANALYZE)
                              │
              CrossMetricGuard → Stabilization → Learning

┌─────────────────────────────────────────────────────────────┐
│                    OPS SELF-HEALING (Tier 1)                 │
│  RunHealthMonitorJob → DatabaseGuardian → SelfHealingEngine  │
└─────────────────────────────────────────────────────────────┘
                              │
                    OpsSelfHealingSafetyGate
                              │
              Allowlist: replica_lag | connection_saturation
                              │
              Cache runtime flags (TTL-bound, no schema mutation)
```

## Unified Safety Principle

- **Optimization path:** all performance mutations flow through `SelfHealingPerformanceEngine` → checkpoint → `IsolatedOptimizationRunner`.
- **Ops path:** explicitly separate domain with independent allowlist, cooldown, and circuit breaker (`OpsSelfHealingSafetyGate`).
- **Growth cycle bypass closed:** `DatabaseGuardian::runGrowthAndOptimizationCycle()` respects `unified_safety_pipeline=true` (default).

## Key Classes

| Component | Path |
|-----------|------|
| Performance orchestrator | `app/Optimization/SelfHealing/SelfHealingPerformanceEngine.php` |
| Checkpoint lifecycle | `app/Optimization/SelfHealing/CheckpointService.php` |
| Crash recovery | `app/Optimization/SelfHealing/CheckpointRecoveryService.php` |
| Context baseline | `app/Optimization/SelfHealing/BaselineContextMatcher.php` |
| Rollback abstraction | `app/Optimization/Rollback/RollbackManager.php` |
| Ops safety gate | `app/Intelligence/SelfHealing/OpsSelfHealingSafetyGate.php` |
