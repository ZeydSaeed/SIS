# Phase 2B.1 — Test Matrix

**Date:** 2026-09-07  
**Primary suite:** `tests/Feature/Optimization/PostgreSql/ControlledAutonomousAnalyzeTest.php`

---

## Matrix

| ID | Scenario | Expected | Test | Result |
|----|----------|----------|------|--------|
| A1 | observe mode | BLOCK | `a1_observe_mode_blocks_autonomous_analyze` | PASS |
| A2 | missing allowlist | BLOCK | `a2_missing_allowlist_blocks_autonomous_analyze` | PASS |
| A3 | unauthorized target | BLOCK | `a3_unauthorized_target_blocks_autonomous_analyze` | PASS |
| A4 | unauthorized environment | BLOCK | `a4_unauthorized_environment_blocks_autonomous_analyze` | PASS |
| A5 | circuit open | BLOCK | `a5_circuit_breaker_open_blocks_autonomous_analyze` | PASS |
| A6 | target lock conflict | BLOCK | `a6_target_lock_conflict_blocks_autonomous_analyze` | PASS |
| A7 | incompatible baseline | BLOCK | `a7_incompatible_baseline_blocks_autonomous_analyze` | PASS |
| A8 | critical telemetry UNKNOWN | BLOCK | `a8_critical_telemetry_unknown_blocks_autonomous_analyze` | PASS |
| A9 | ambiguous checkpoint | BLOCK | `a9_ambiguous_checkpoint_blocks_autonomous_analyze` | PASS |
| A10 | insufficient confidence | BLOCK | `a10_insufficient_confidence_blocks_autonomous_analyze` | PASS |
| A11 | controlled autonomous ANALYZE | REAL EXECUTION | `a11_controlled_autonomous_analyze_e2e_executes_real_postgresql_analyze` | PASS |
| A12 | guard failure after real execution | SAFE REJECT | `a12_guard_failure_after_real_analyze_is_not_considered_successful` | PASS |
| A13 | crash after execution | NO BLIND RETRY | `a13_crash_after_real_analyze_blocks_blind_retry` | PASS |
| A14 | learning escalation attempt | BLOCK | `a14_learning_escalation_does_not_expand_autonomous_privilege` | PASS |
| A15 | wildcard target | BLOCK | `a15_wildcard_allowlist_blocks_autonomous_analyze` | PASS |
| A16 | SQL injection target | BLOCK | `a16_sql_injection_target_is_blocked` | PASS |

Additional production safety: `production_observe_mode_blocks_even_with_high_confidence_and_allowlisted_target` — PASS

---

## Real Execution Evidence (A11)

| Evidence | Source |
|----------|--------|
| `OptimizationEvent.action_taken` contains `ANALYZE` | `intelligence.optimization_events` |
| `pg_stat_user_tables.last_analyze` updated | PostgreSQL catalog |
| Checkpoint → `StabilizationStarted` | `CheckpointService` |
| Executor path | `AnalyzeTableOperation` → `SafeAutoExecutor` (not `TestAnalyzeOperation`) |

---

## Supporting Tests (Phase 2A / 2B.0 regression)

| Area | Test file |
|------|-----------|
| Allowlist policy | `AnalyzeTargetPolicyTest` |
| Policy unit | `AutonomousExecutionPolicyTest` |
| Real PG E2E (S11/S21) | `RealAnalyzeExecutionTest` |
| CrossMetricGuard | `CrossMetricGuardTest`, `IsolatedOptimizationRunnerTest` |
| Learning safety | `RecommendationRankerTest` |

---

## Commands

```bash
php artisan test --filter=Optimization
php artisan test -c phpunit.optimization-pgsql.xml
php artisan architecture:validate --fitness
```

---

## Last Run (2026-09-07)

| Command | Passed | Failed | Skipped | Assertions |
|---------|--------|--------|---------|------------|
| `--filter=Optimization` | 59 | 0 | 25 | 130 |
| `-c phpunit.optimization-pgsql.xml` | 25 | 0 | 0 | 75 |
| `architecture:validate --fitness` | 9/9 | — | — | — |

Skipped tests = SQLite suite without PostgreSQL (PG tests run via dedicated config).
