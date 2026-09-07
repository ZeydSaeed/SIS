# Phase 2B.2 — Failure Injection Matrix

**Date:** 2026-09-07

| Failure | Expected | Test / Evidence | Result |
|---------|----------|-----------------|--------|
| Kill switch ON | BLOCK | `AutonomousExecutionPolicyTest::kill_switch_blocks...`, `OperationalSoakTest::kill_switch_on...` | VERIFIED |
| Production environment | BLOCK | `OperationalSoakTest::production_isolation...` | VERIFIED |
| Missing allowlist | BLOCK | `ControlledAutonomousAnalyzeTest::a2_...` | VERIFIED |
| Unauthorized target | BLOCK | `ControlledAutonomousAnalyzeTest::a3_...` | VERIFIED |
| Lock conflict | BLOCK | `OperationalSoakTest::concurrent_workers...` | VERIFIED |
| Cooldown active | BLOCK | `OperationalSoakTest::cooldown_blocks...` | VERIFIED |
| Circuit open | BLOCK | `OperationalSoakTest::circuit_breaker_opens...` | VERIFIED |
| Unknown telemetry | BLOCK | `OperationalSoakTest::critical_telemetry_unknown...` | VERIFIED |
| Stale baseline | BLOCK | `OperationalSoakTest::stale_baseline...` | VERIFIED |
| Data-scale mismatch | BLOCK | Phase 2A `s15_...` | VERIFIED |
| Ambiguous checkpoint | BLOCK | `ControlledAutonomousAnalyzeTest::a9/a13`, S21 tests | VERIFIED |
| Low confidence | BLOCK | `ControlledAutonomousAnalyzeTest::a10_...` | VERIFIED |
| Rate limit exceeded | BLOCK | `AutonomousExecutionPolicyTest::rate_limit...`, `OperationalSoakTest::rate_limit...` | VERIFIED |
| PostgreSQL unavailable | SAFE FAIL | `OperationalSoakTest::database_execution_failure...` | VERIFIED |
| PostgreSQL timeout | SAFE FAIL | `RealAnalyzeExecutionTest::f2_...` | VERIFIED |
| Crash before checkpoint | Safe retry | `OperationalSoakTest::worker_crash_before_checkpoint...` | VERIFIED |
| Crash after checkpoint started | NO BLIND RETRY | `OperationalSoakTest::worker_crash_after_checkpoint_started...` | VERIFIED |
| Crash after execution (S21) | NO BLIND RETRY | `OperationalSoakTest::worker_crash_after_real_analyze...` | VERIFIED |
| Guard failure | REJECT | `ControlledAutonomousAnalyzeTest::a12_...` | VERIFIED |
| Learning privilege escalation | BLOCK | `ControlledAutonomousAnalyzeTest::a14_...` | VERIFIED |
| SQL injection target | BLOCK | `AnalyzeTargetPolicyTest`, `ControlledAutonomousAnalyzeTest::a16_...` | VERIFIED |
| Wildcard target | BLOCK | `ControlledAutonomousAnalyzeTest::a15_...` | VERIFIED |
| Worker lock overlap | SKIP cycle | `OperationalSoakTest::worker_lock_prevents...` | VERIFIED |
| Registry extra actions | FAIL phase | `OperationalSoakTest::operation_registry_contains_analyze_only` | VERIFIED |

---

## Skipped Tests (SQLite Default Suite)

42 PostgreSQL tests skipped when running `php artisan test --filter=Optimization` without `-c phpunit.optimization-pgsql.xml`.

**Reason:** Real PostgreSQL ANALYZE validation requires live PG connection. Run dedicated config for full coverage.

---

## Not Verifiable

| Item | Status |
|------|--------|
| 24/7 continuous soak | NOT VERIFIABLE |
| 7-day soak | NOT VERIFIABLE |
| Full-path timeout with ACCESS EXCLUSIVE lock | Covered by F2 direct executor test (lock-hold PG test risks stale locks) |
