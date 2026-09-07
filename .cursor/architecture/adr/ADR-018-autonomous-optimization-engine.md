# ADR-018: Autonomous Optimization Engine

## Status

Accepted — 2026-09-07

## Context

SIS already has Architecture Guard (static analysis), Intelligence Layer (database monitoring + Tier-1 auto ANALYZE), and Performance Budget docs. The user requested an **Autonomous Optimization System** that:

1. Starts in **Observe** mode — no blind auto-modification
2. Follows **ONE PROBLEM → ONE OPTIMIZATION → ONE VALIDATION**
3. Integrates with existing gates — not a separate uncontrolled AI modifier

## Decision

Introduce `app/Optimization/` as an orchestration layer **on top of** Intelligence + Architecture:

| Component | Role |
|-----------|------|
| `OptimizationEngine` | Mode orchestration (observe / recommend / autonomous) |
| `BaselineSnapshotService` | Writes `intelligence.baseline_snapshots` |
| `BottleneckAnalyzer` | Anomaly detection vs baseline |
| `OptimizationScorer` | Impact × Confidence / Risk × Complexity |
| `IsolatedOptimizationRunner` | ONE change + checkpoint + history |
| `ArchitectureOptimizationGate` | Blocks code path if architecture fails |
| `CrossMetricGuard` | Rejects if protected metrics regress |

Default mode: **`observe`** (`OPTIMIZATION_MODE=observe`).

Level 2 autonomous execution:
- Only Tier-1 recommendations
- Only actions in `config/optimization.low_risk_auto_actions`
- Delegates DB execution to existing `SafeAutoExecutor` (ANALYZE only today)
- **Does not auto-modify application source code**

## Consequences

### Positive

- Unified workflow for Cursor agents and runtime scheduler
- Baseline snapshots finally persisted
- CostEstimator wired into RuleEngine recommendations
- VerificationService scopes P95 by table context

### Negative / Limits

- Application-level optimizations (N+1 fixes, React re-renders) remain **recommend-only** until explicit approval workflow extends to code patches
- Full cross-metric telemetry (CPU, UI render) requires Phase 2 Observability (Prometheus)

## Alternatives Considered

1. **Separate docs-only prompt** — rejected; no runtime integration
2. **Full autonomous code editor** — rejected; violates safety and architecture gates
3. **Extend Intelligence only** — insufficient; needed explicit optimization modes and history

## References

- `.cursor/architecture/optimization/README.md`
- `.cursor/architecture/DATABASE-INTELLIGENCE-LAYER.md`
- `.cursor/architecture/PERFORMANCE-BUDGET.md`
