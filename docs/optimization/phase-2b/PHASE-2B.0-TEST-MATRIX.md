# Phase 2B.0 — Test Matrix

| ID | Scenario | Real DB | Expected | Test File | Result |
|----|----------|---------|----------|-----------|--------|
| S11 | Real ANALYZE E2E | YES | heal_applied + pg_stat evidence | PostgreSql/RealAnalyzeExecutionTest | **PASS** |
| S10 | Worker crash (pre-exec) | SQLite | INCOMPLETE_NO_AUTO_RETRY | Phase2ASafetyClosureTest | **PASS** |
| S21 | Crash after execution | YES | EXECUTED_BUT_NOT_FINALIZED | PostgreSql/RealAnalyzeExecutionTest | **PASS** |
| S12 | Checkpoint lifecycle | SQLite | Complete | CheckpointLifecycleTest | **PASS** |
| S14 | Context baseline | SQLite | Compatible/stale | BaselineContextMatcherTest | **PASS** |
| S15 | Data scale | SQLite | Tier context | DataScaleContextTest | **PASS** |
| S16 | Learning ranking | SQLite | No tier escalation | RecommendationRankerTest | **PASS** |
| S17 | Ops safety gate | SQLite | Allowlist | OpsSelfHealingSafetyGateTest | **PASS** |
| S18 | Rollback semantics | SQLite | Non-reversible | Phase2ASafetyClosureTest | **PASS** |
| S20 | Target normalization | SQLite | Same lock key | TargetNormalizerTest | **PASS** |
| F1 | Disallowed target | YES | SafeAutoExecutor null | RealAnalyzeExecutionTest | **PASS** |
| F4 | Circuit open | YES | No heal | RealAnalyzeExecutionTest | **PASS** |
| F5 | Lock conflict | YES | Second acquire fails | RealAnalyzeExecutionTest | **PASS** |
| F3 | Guard unknown metric | SQLite | REJECTED | IsolatedOptimizationRunnerTest | **PASS** |
| — | Sustained degradation | SQLite | Blocks until minutes elapsed | AnomalyDetectorTest | **PASS** |

## Commands

```bash
php artisan test --filter=Optimization          # 42 passed, 6 skipped (PG)
php artisan test -c phpunit.optimization-pgsql.xml  # 6 passed
php artisan architecture:validate --fitness   # PASS
```
