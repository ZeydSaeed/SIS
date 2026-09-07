# Autonomous Optimization Engine — SIS

Evidence-driven performance optimization integrated with **Architecture Guard + Intelligence Layer + Regression Guard**.

## Principle

```text
DETECT → MEASURE → BASELINE → ANALYZE → ISOLATE → OPTIMIZE → VALIDATE → ACCEPT OR ROLLBACK
```

**ONE PROBLEM → ONE OPTIMIZATION → ONE VALIDATION**

## Self-Healing Layer (v2)

Continuous background monitoring via `SelfHealingPerformanceEngine`:

```text
Scheduler (5 min) → RunSelfHealingCycleJob → runCycle()
  → Adaptive Baseline + Health Score + Hysteresis
  → Root Cause (confidence) → [autonomous] Heal + Stabilize + Rollback
```

See [SELF-HEALING-PERFORMANCE-PROMPT.md](./SELF-HEALING-PERFORMANCE-PROMPT.md) and [ADR-019](../adr/ADR-019-self-healing-performance-engine.md).

## Levels

| Level | Mode | Env | Behavior |
|-------|------|-----|----------|
| 0 | `observe` | `OPTIMIZATION_MODE=observe` (default) | Metrics + baseline only — **no code/DB changes** |
| 1 | `recommend` | `OPTIMIZATION_MODE=recommend` | RCA + reports + Intelligence recommendations — **human approval** |
| 2 | `autonomous` | `OPTIMIZATION_MODE=autonomous` | Tier-1 low-risk only via `IsolatedOptimizationRunner` + gates |

## Commands

```bash
php artisan optimization:status       # Engine status + safe mode
php artisan optimization:health       # Health score + anomalies
php artisan optimization:baseline     # Adaptive baseline [--capture]
php artisan optimization:history      # Recent optimization records
php artisan optimization:rollback     # Rollback by history ID
php artisan optimization:worker       # Background worker [--once]
php artisan optimization:observe      # Level 0 (manual)
php artisan optimization:recommend    # Level 1
php artisan optimization:run          # Level 2 (gated)
```

## Integration

```text
SelfHealingPerformanceEngine
    └── OptimizationEngine
            ├── DatabaseGuardian (Intelligence)
            ├── BaselineSnapshotService → intelligence.baseline_snapshots
            ├── AdaptiveBaselineEngine → storage/adaptive-baseline.json
            ├── BottleneckAnalyzer + OptimizationScorer
            ├── IsolatedOptimizationRunner → SafeAutoExecutor (ANALYZE only)
            ├── ArchitectureOptimizationGate → architecture:validate
            ├── CrossMetricGuard + CircuitBreaker + Cooldown + TargetLock
            └── OptimizationHistoryRecorder → storage/app/optimization/history/
```

## Safety Boundaries (Approval Required)

- Database schema / migrations
- Authentication / authorization
- Financial / grade calculations
- Transaction / concurrency semantics
- Public API breaking changes
- Audit log removal

## Related Docs

| File | Purpose |
|------|---------|
| [AUTONOMOUS-OPTIMIZATION-PROMPT.md](./AUTONOMOUS-OPTIMIZATION-PROMPT.md) | Master prompt for Cursor agents |
| [SYSTEM-INVENTORY.md](./SYSTEM-INVENTORY.md) | Project component inventory |
| [../DATABASE-INTELLIGENCE-LAYER.md](../DATABASE-INTELLIGENCE-LAYER.md) | Intelligence pipeline |
| [../PERFORMANCE-BUDGET.md](../PERFORMANCE-BUDGET.md) | Latency targets |
| [../DATABASE-ADAPTIVE-GOVERNANCE.md](../DATABASE-ADAPTIVE-GOVERNANCE.md) | Measure before optimize |
| [../adr/ADR-018-autonomous-optimization-engine.md](../adr/ADR-018-autonomous-optimization-engine.md) | Architecture decision |

## Config

`config/optimization.php` — mode, domains, scoring, safety boundaries, gates.
