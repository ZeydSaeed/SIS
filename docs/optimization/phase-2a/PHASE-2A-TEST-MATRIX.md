# Phase 2A Test Matrix (S1–S20)

| ID | Scenario | Expected | Test File | Method | Proves | Result |
|----|----------|----------|-----------|--------|--------|--------|
| S1 | Healthy system | monitor, no heal | SelfHealingPipelineTest | s1_healthy_system... | Observe path | PASS |
| S2 | Spike + hysteresis | no anomaly yet | SelfHealingPipelineTest | s2_temporary_spike... | Hysteresis | PASS |
| S3 | RCA target match | recommendation matched | IncidentRecommendationResolverTest | s3_it_matches... | Resolver | PASS |
| S4 | Mismatch rejected | matched=false | IncidentRecommendationResolverTest | s4_it_rejects... | assertMatches gate | PASS |
| S5 | Cross-metric regression | REJECTED | IsolatedOptimizationRunnerTest | s5_it_rejects... | CrossMetricGuard | PASS |
| S6 | Circuit breaker | safe_mode | CircuitBreakerTest | it_enters_safe_mode... | Failures open CB | PASS |
| S7 | Stabilization regression | rollback | StabilizationMonitorTest | s7_stabilization... | Post-accept monitor | PASS |
| S8 | Unknown critical metric | blocked | IsolatedOptimizationRunnerTest | s8_it_blocks... | Null ≠ zero | PASS |
| S9 | Concurrent cycle | skipped | SelfHealingPipelineTest | s9_concurrent... | Worker lock | PASS |
| S10 | Worker crash/recovery | RECOVERY_RECORDED | Phase2ASafetyClosureTest | s10_worker_crash... | No auto-retry | PASS |
| S11 | Full autonomous E2E | heal_applied | Phase2ASafetyClosureTest | s11_full_autonomous... | Full pipeline | PASS |
| S12 | Checkpoint lifecycle | states persisted | CheckpointLifecycleTest | s12_checkpoint... | Lifecycle | PASS |
| S13 | Environment change | baseline_warmup | Phase2ASafetyClosureTest | s13_environment... | Stale baseline | PASS |
| S14 | Context mismatch | no heal | BaselineContextMatcherTest + Phase2ASafetyClosureTest | s14_* | Fingerprint gate | PASS |
| S15 | Data scale context | tier + block | DataScaleContextTest + Phase2ASafetyClosureTest | s15_* | Scale awareness | PASS |
| S16 | Learning no tier escalation | rank only | RecommendationRankerTest | s16_learning... | max tier capped | PASS |
| S17 | Ops path safety | allowlist gate | OpsSelfHealingSafetyGateTest | s17_* | Independent gate | PASS |
| S18 | ANALYZE non-reversible | rollback false | Phase2ASafetyClosureTest | s18_analyze... | RollbackManager | PASS |
| S19 | Missing telemetry blocks | S8 covers critical | IsolatedOptimizationRunnerTest | s8_it_blocks... | Safety block | PASS |
| S20 | Target normalization | same lock key | TargetNormalizerTest | s20_same_logical... | Lock normalize | PASS |

**Execution:** `php artisan test --filter=Optimization` — 41 passed (2026-09-07).
