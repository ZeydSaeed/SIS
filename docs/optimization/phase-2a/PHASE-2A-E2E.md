# Phase 2A End-to-End Autonomous Test

## Test: S11

**File:** `tests/Feature/Optimization/Phase2ASafetyClosureTest.php`  
**Method:** `s11_full_autonomous_e2e_executes_analyze_with_checkpoint_lifecycle`

## Pipeline Covered

```text
autonomous mode
  → telemetry (degraded)
  → baseline (warmed, context-compatible)
  → anomaly (hysteresis=1)
  → RCA (QueryMetric bottleneck → students)
  → recommendation resolve + assertMatches
  → safety gates (scorer, architecture gate off in test)
  → checkpoint CREATED
  → ANALYZE via TestAnalyzeOperation
  → fresh after metrics (SequentialMetricSnapshotCapturer)
  → CrossMetricGuard pass
  → stabilization started
  → learning record
  → events emitted
```

## Evidence

- `before_state.p95_latency_ms` (100) ≠ `after_metrics.p95_latency_ms` (85)
- Checkpoint reaches `STABILIZATION_STARTED`
- Outcome: `heal_applied`

## Limitation (Condition)

Apply step uses `TestAnalyzeOperation` — not a live PostgreSQL `ANALYZE` via `SafeAutoExecutor`.  
Real DB ANALYZE E2E deferred to Phase 2B with dedicated PostgreSQL test harness.
