# Autonomous Optimization Engine — SIS

Evidence-driven performance optimization integrated with **Architecture Guard + Intelligence Layer + Regression Guard**.

## Principle

```text
DETECT → MEASURE → BASELINE → ANALYZE → ISOLATE → OPTIMIZE → VALIDATE → ACCEPT OR ROLLBACK
```

**ONE PROBLEM → ONE OPTIMIZATION → ONE VALIDATION**

## Levels

| Level | Mode | Env | Behavior |
|-------|------|-----|----------|
| 0 | `observe` | `OPTIMIZATION_MODE=observe` (default) | Metrics + baseline only — **no code/DB changes** |
| 1 | `recommend` | `OPTIMIZATION_MODE=recommend` | RCA + reports + Intelligence recommendations — **human approval** |
| 2 | `autonomous` | `OPTIMIZATION_MODE=autonomous` | Tier-1 low-risk only via `IsolatedOptimizationRunner` + gates |

## Commands

```bash
php artisan optimization:observe      # Level 0
php artisan optimization:recommend    # Level 1
php artisan optimization:run          # Level 2 (gated)
```

## Integration

```text
OptimizationEngine
    ├── DatabaseGuardian (Intelligence)
    ├── BaselineSnapshotService → intelligence.baseline_snapshots
    ├── BottleneckAnalyzer + OptimizationScorer
    ├── IsolatedOptimizationRunner → SafeAutoExecutor (ANALYZE only)
    ├── ArchitectureOptimizationGate → architecture:validate
    ├── CrossMetricGuard
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
