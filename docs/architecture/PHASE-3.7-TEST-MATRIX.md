# Phase 3.7 — Test Matrix

**Date:** 2026-09-07  
**Primary suite:** `phpunit.phase3.xml`  
**Integration:** `tests/Feature/Integration/Phase3ApplicationIntegrationTest.php`

---

## Cross-Phase Integration Matrix

| ID | Phase | Scenario | Expected | Test | Result |
|----|-------|----------|----------|------|--------|
| I1 | 3.1 | Health endpoint exposes profile + optimization block | 200 + JSON structure | `phase3_full_pipeline...` | PASS |
| I2 | 3.1 | Correlation ID propagated | Header echoed | `phase3_full_pipeline...` | PASS |
| I3 | 3.2–3.3 | Seed + create + search student | 201 + 200 | `phase3_full_pipeline...` | PASS |
| I4 | 3.4–3.6 | Workload profile meets budget | `report.passed = true` | `phase3_full_pipeline...` | PASS |
| I5 | 3.5 | Healthy traffic → zero detections | `detections = 0` | `phase3_full_pipeline...` | PASS |
| I6 | 3.4 | TelemetryCollector prefers HTTP | `p95_latency_source = http` | `phase3_full_pipeline...` | PASS |
| I7 | 3.5 | Budget breach → detection + recommendation | Detection + APP-001 | `phase3_budget_breach_pipeline...` | PASS |
| I8 | 3.6 | Validate workload command E2E | Exit 0 | `phase3_validate_workload_command...` | PASS |

---

## Architecture Matrix

| ID | Area | Scenario | Expected | Test | Result |
|----|------|----------|----------|------|--------|
| A1 | Fitness | All 9 categories pass | PASS | `ArchitectureFitnessTest` | PASS |
| A2 | Domain | No Laravel imports in Domain | PASS | `CleanArchitectureTest` | PASS |
| A3 | Student | Feature contract complete | No violations | `Phase3FeatureContractTest` | PASS |
| A4 | Controllers | No Intelligence models / DB in API controllers | PASS | `Phase3FeatureContractTest` | PASS |
| A5 | CLI | `architecture:validate --fitness` | 9/9 | Manual / CI | PASS |

---

## Phase 3 Component Regression

| Phase | Area | Test file(s) | Tests |
|-------|------|--------------|-------|
| 3.1 | Environment + policy | `SisEnvironmentProfileTest`, `AutonomousExecutionPolicyTest` | 8+ |
| 3.1 | Health API | `HealthEndpointTest` | 2 |
| 3.2 | Database foundation | `FoundationSeederTest`, `DatabaseFoundationVerifierTest` | 4+ |
| 3.3 | Students API | `StudentApiTest`, `CreateStudentHandlerTest` | 7+ |
| 3.4 | HTTP telemetry | `HttpRequestTelemetryTest` | 2 |
| 3.5 | Intelligence integration | `HttpWorkloadDetectionTest`, `ThresholdEngineTest` | 7 |
| 3.6 | Workload validation | `StudentSearchWorkloadValidationTest`, `WorkloadBudgetValidatorTest` | 6 |
| 3.7 | Cross-phase integration | `Phase3ApplicationIntegrationTest` | 3 |

---

## Commands

```bash
# Phase 3 regression (recommended pre-gate)
php artisan test -c phpunit.phase3.xml

# Integration only
php artisan test --filter=Phase3ApplicationIntegration

# Architecture
php artisan test --testsuite=Architecture
php artisan architecture:validate --fitness
php artisan architecture:feature-check Student

# Full suite (includes Optimization skips on SQLite)
php artisan test
```

---

## Last Run (2026-09-07)

| Command | Passed | Failed | Skipped | Assertions |
|---------|--------|--------|---------|------------|
| `-c phpunit.phase3.xml` | 61 | 0 | 2 | 247 |
| `--filter=Phase3` | 6 | 0 | 0 | 46 |
| `php artisan test` (full) | 166 | 0 | 44 | 531 |
| `architecture:validate --fitness` | 9/9 | — | — | — |

Skipped tests = Fortify features disabled + PostgreSQL-only tests in default SQLite config.

---

## Evidence for Phase 3.8 Gate

| Evidence | Source |
|----------|--------|
| E2E pipeline PASS | `Phase3ApplicationIntegrationTest` |
| Zero false-positive detections | I5 in integration test |
| Budget breach detection works | I7 in integration test |
| Architecture 9/9 | `architecture:validate --fitness` |
| Production autonomous blocked | I1 + `AutonomousExecutionPolicyTest` |
| Workload within budget | I4 + `sis:validate-workload` |

---

*Phase 3.7 test matrix complete.*
