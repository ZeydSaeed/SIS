# Self-Healing E2E Test Matrix

## Test Locations

| Suite | Path |
|-------|------|
| Feature E2E | `tests/Feature/Optimization/SelfHealingPipelineTest.php` |
| Incident resolver | `tests/Unit/Optimization/IncidentRecommendationResolverTest.php` |
| Runner + guard | `tests/Unit/Optimization/IsolatedOptimizationRunnerTest.php` |
| Stabilization | `tests/Unit/Optimization/SelfHealing/StabilizationMonitorTest.php` |
| Engine gates | `tests/Unit/Optimization/OptimizationEngineTest.php` |
| Cross-metric | `tests/Unit/Optimization/CrossMetricGuardTest.php` |
| Baseline / hysteresis / CB | `tests/Unit/Optimization/SelfHealing/*` |

Run: `php artisan test --filter=Optimization`

---

## Scenario Matrix

| ID | Scenario | Expected | Test |
|----|----------|----------|------|
| S1 | Healthy system | `NO_ACTION` / outcome `monitor` | `s1_healthy_system_returns_monitor_without_optimization` |
| S2 | Temporary CPU spike | `NO_OPTIMIZATION` (hysteresis) | `s2_temporary_spike_does_not_trigger_anomaly_due_to_hysteresis` |
| S3 | Database degradation | RCA target = recommendation target | `s3_it_matches_database_target_recommendation_for_incident` |
| S4 | Wrong recommendation | `REJECT` / no match | `s4_it_rejects_when_no_recommendation_matches_incident_target` |
| S4b | Assert mismatch | RuntimeException | `s4_assert_matches_throws_on_incident_mismatch` |
| S5 | Cross-metric regression | `REJECTED` | `s5_it_rejects_when_cross_metric_guard_detects_regression` |
| S6 | Optimization failure | Circuit breaker | `CircuitBreakerTest::it_enters_safe_mode_after_max_failures` |
| S7 | Stabilization regression | Rollback | `s7_stabilization_regression_triggers_rollback` |
| S8 | Unknown critical metric | Block autonomous | `s8_it_blocks_when_critical_metrics_are_unavailable` |
| S9 | Concurrent cycle | Skip — lock held | `s9_concurrent_cycle_is_skipped_when_worker_lock_held` |
| S10 | Observe mode | Never heal | `s10_observe_mode_never_attempts_self_heal` |

---

## Commands Tested

| Command | Test |
|---------|------|
| `optimization:status` | `optimization_status_command_runs` |
| `optimization:run` | `optimization_run_autonomous_command_is_disabled` |

---

## Not Yet Automated

| Scenario | Reason |
|----------|--------|
| S10 Worker crash mid-cycle | Requires process kill simulation |
| Full autonomous ANALYZE on PostgreSQL | Integration test — deferred to Phase 2 |
| 24/7 scheduler uptime | Operational — see runtime runbook |

---

## Gate Commands

```bash
php artisan test --filter=Optimization          # 26 tests
php artisan architecture:validate --fitness     # must PASS
php artisan optimization:status                 # runtime verification
php artisan optimization:health
```
