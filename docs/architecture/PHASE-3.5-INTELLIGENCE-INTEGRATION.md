# Phase 3.5 — Intelligence Integration

**Phase:** 3.5  
**Date:** 2026-09-07  
**Previous:** Phase 3.4 Application Observability  
**Status:** Complete — HTTP workload telemetry drives detections and expert recommendations

---

## Executive Summary

Phase 3.5 closes the loop between **HTTP request telemetry** (Phase 3.4) and the **Intelligence layer**. When an HTTP workload snapshot reports `budget_exceeded = true`, `DatabaseGuardian::runPerformanceCycle()` now evaluates it through `ThresholdEngine`, persists `Detection` rows, and generates expert `Recommendation` rows via `RuleEngine`.

**No new database tables** — reuses `intelligence.detections` and `intelligence.recommendations`.

**Risk tier 0 (observe only)** — no auto-execution on HTTP detections.

---

## Architecture

```text
HttpRequestTelemetryMonitor::flush()
    → monitoring_snapshots (http_workload)
    ↓
DatabaseGuardian::runPerformanceCycle()
    ↓
FOR EACH http_workload snapshot:
    ThresholdEngine::evaluateHttpWorkload(metrics)
        → match http_budget_exceeded | http_query_heavy | http_high_error_rate
        → persistDetection()
        → RuleEngine::diagnose() → APP-001 | APP-002 | APP-003
        → ConfidenceEngine → createRecommendation()
    ↓
(existing query metric evaluation continues unchanged)
```

---

## Deliverables

| Item | Location | Purpose |
|------|----------|---------|
| HTTP evaluation | `ThresholdEngine::evaluateHttpWorkload()` | Map snapshot metrics → threshold signals |
| Threshold rules | `config/intelligence.php` | `http_budget_exceeded`, `http_query_heavy`, `http_high_error_rate` |
| Expert rules | `config/intelligence.php` | `APP-001`, `APP-002`, `APP-003` for application workloads |
| Guardian wiring | `DatabaseGuardian::runPerformanceCycle()` | Evaluate HTTP snapshots before query metrics |
| RuleEngine | Updated | `investigate`, `query_optimize` recommendation types |
| Unit tests | `ThresholdEngineTest.php` | Signal matching + evidence context |
| Feature tests | `HttpWorkloadDetectionTest.php` | End-to-end budget breach → detection → recommendation |

---

## HTTP Threshold Signals

| Signal | Source | Used By |
|--------|--------|---------|
| `http_p95_ms` | `metrics.p95_ms` | Evidence, degradation score |
| `budget_exceeded` | `metrics.budget_exceeded` | `http_budget_exceeded` rule |
| `error_rate_pct` | `metrics.error_rate_pct` | `http_high_error_rate` rule |
| `mean_db_queries_per_request` | `metrics.mean_db_queries_per_request` | `http_query_heavy` rule |
| `sample_count` | `metrics.sample_count` | Evidence |

### Context (stored in detection evidence)

- `workload` — e.g. `student_search`
- `routes` — e.g. `["api.students.search"]`
- `performance_budget_p95_ms` — from `performance_budgets` config
- `source` — `http_request_telemetry`

---

## Threshold Rules (HTTP)

| Rule ID | Condition | Risk Tier | Action |
|---------|-----------|-----------|--------|
| `http_budget_exceeded` | `budget_exceeded = true` | 0 | investigate |
| `http_high_error_rate` | `error_rate_pct > 5` | 0 | investigate |
| `http_query_heavy` | `mean_db_queries_per_request > 10` | 0 | investigate |

---

## Expert Rules (Application Workloads)

| Rule ID | Trigger | Recommendation |
|---------|---------|----------------|
| `APP-001` | Budget exceeded | `investigate` — API latency + query patterns |
| `APP-002` | >10 DB queries/request | `query_optimize` — N+1 / eager load |
| `APP-003` | >5% error rate | `investigate` — application failure |

`student_search` budget: **200 ms P95** (`performance_budgets.student_search`).

---

## Detection Evidence Example

```json
{
  "http_p95_ms": 285.0,
  "budget_exceeded": true,
  "error_rate_pct": 0.0,
  "mean_db_queries_per_request": 3.2,
  "sample_count": 20,
  "workload": "student_search",
  "routes": ["api.students.search"],
  "performance_budget_p95_ms": 200,
  "source": "http_request_telemetry"
}
```

`degradation_score` = `(p95 - budget) / budget × 100` when budget exceeded.

---

## Safety Constraints

| Constraint | Enforcement |
|------------|-------------|
| No auto-execute on HTTP detections | All HTTP rules = risk tier 0 |
| Production autonomous ANALYZE blocked | Unchanged from Phase 3.1 |
| UNKNOWN telemetry | Null preserved — no fake healthy values |
| SQLite tests | PgStat collector returns empty; HTTP path fully testable |

---

## Verification

```bash
# Unit
php artisan test --filter=ThresholdEngine

# Feature (end-to-end)
php artisan test --filter=HttpWorkloadDetection

# Regression
php artisan test --filter="HttpRequestTelemetry|HealthEndpoint|StudentApi"
php artisan architecture:validate --fitness
```

**Gate (2026-09-07):** ThresholdEngine + HttpWorkloadDetection tests pass · architecture 9/9 PASS

---

## What Is NOT in Phase 3.5

- Automatic remediation of HTTP budget violations
- Prometheus / external APM export
- Detection → Optimization `IncidentReport` bridge
- Dashboard UI for HTTP detections

---

## Next: Phase 3.7 — Integration + Architecture Tests
