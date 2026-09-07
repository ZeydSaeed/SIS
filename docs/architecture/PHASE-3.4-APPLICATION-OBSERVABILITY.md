# Phase 3.4 — Application Observability

**Phase:** 3.4  
**Date:** 2026-09-07  
**Previous:** Phase 3.3 Students Vertical Slice  
**Status:** Complete — real HTTP telemetry wired to Intelligence

---

## Executive Summary

Phase 3.4 connects **real HTTP request telemetry** from the Students API to the existing Intelligence/Optimization stack. Request duration, DB query count per request, and workload classification are captured via middleware, aggregated into `intelligence.monitoring_snapshots`, and consumed by `TelemetryCollector` for optimization decisions.

**No new database tables** — uses existing `monitoring_snapshots.metrics` JSON with `snapshot_type = http_workload`.

---

## Architecture

```text
API Request
    → CorrelationIdMiddleware
    → RequestTelemetryMiddleware (begin)
    → Controller / Handlers
    → QueryExecuted → CountDatabaseQueriesForRequest
    → QueryExecuted → QueryPerformanceListener (route-labeled, 10ms threshold)
    → Response
    → RequestTelemetryMiddleware::terminate (record sample)
    
RunPerformanceAnalysisJob (every 5 min)
    → DatabaseGuardian::runPerformanceCycle()
    → HttpRequestTelemetryMonitor::flush() → monitoring_snapshots
    → QueryMonitor::flushRuntimeSamples() → query_metrics
    → ThresholdEngine / RuleEngine

RunSelfHealingCycleJob
    → TelemetryCollector::collect()
    → prefers HTTP p95 when http_workload snapshots exist
```

---

## Deliverables

| Item | Location | Purpose |
|------|----------|---------|
| Request context | `app/Observability/RequestTelemetryContext.php` | Per-request duration + query count |
| Workload mapping | `app/Observability/WorkloadResolver.php` | Route → workload (`student_search`) |
| HTTP monitor | `HttpRequestTelemetryMonitor.php` | Buffer + flush aggregates |
| Middleware | `RequestTelemetryMiddleware.php` | API-only capture |
| Query counter | `CountDatabaseQueriesForRequest.php` | DB queries per request |
| Provider | `ObservabilityServiceProvider.php` | Register listeners |
| Config | `config/sis.php` → `observability` | Routes, thresholds, enable flag |
| TelemetryCollector | Updated | HTTP p95 priority over query p95 |
| DatabaseGuardian | Updated | Flush HTTP samples in performance cycle |
| Health API | `GetHealthStatusHandler` + `HealthController` | `observability.latest_http_workload` via read port |
| Health ports | `DatabaseHealthPort`, `HttpWorkloadReadRepositoryInterface` | Clean Architecture compliance |
| Tests | `HttpRequestTelemetryTest.php`, `HealthEndpointTest.php` | End-to-end verification |

---

## Workload Mapping

| Route | Workload | Performance Budget (p95) |
|-------|----------|--------------------------|
| `api.students.search` | `student_search` | 200 ms |
| `api.students.index` | `student_search` | 200 ms |
| `api.students.show` | `student_search` | 200 ms |
| `api.students.store` | `student_search` | 200 ms |
| `api.students.update` | `student_search` | 200 ms |
| `api.health` | `health` | — |

Budgets from `config/intelligence.php` → `performance_budgets`.

---

## Configuration

```env
SIS_HTTP_TELEMETRY_ENABLED=true
SIS_SLOW_QUERY_THRESHOLD_MS=10
```

Disable in tests via `phpunit.xml` if needed (enabled by default).

---

## Snapshot Format (`http_workload`)

```json
{
  "workload": "student_search",
  "routes": ["api.students.search"],
  "sample_count": 12,
  "p50_ms": 35.2,
  "p95_ms": 88.5,
  "p99_ms": 120.0,
  "mean_db_queries_per_request": 2.1,
  "performance_budget_p95_ms": 200,
  "budget_exceeded": false,
  "source": "http_request_telemetry"
}
```

---

## TelemetryCollector Changes

| Field | Source (priority) |
|-------|-------------------|
| `p95_latency_ms` | HTTP workload snapshots → fallback query_metrics |
| `p95_latency_source` | `http` \| `query` \| `none` |
| `db_queries_per_request` | HTTP mean → fallback query call_count sum |
| `http_request_samples` | Sum of sample_count from recent snapshots |
| `http_workloads` | Raw workload metrics array |

**Rule preserved:** null when unavailable — never fake zero.

---

## Verification

```bash
# Generate workload
curl -X POST http://sis.test/api/v1/students -H "Content-Type: application/json" \
  -d '{"first_name":"Ali","last_name":"Test","gender":1,"birth_date":"2010-01-01"}'
curl "http://sis.test/api/v1/students/search?q=Ali"

# Flush via performance cycle (or wait for scheduler)
php artisan intelligence:guardian performance

# Check health endpoint
curl http://sis.test/api/v1/health

# Run tests
php artisan test --filter="HttpRequestTelemetry|HealthEndpoint|StudentApi"
php artisan architecture:validate --fitness
```

**Gate (2026-09-07):** 9/9 tests pass · architecture 9/9 PASS

---

## What Is NOT in Phase 3.4

- Prometheus / external APM export (Phase 3.5+)
- Automatic Intelligence detections from HTTP budget violations (Phase 3.5)
- Dashboard UI for telemetry
- Production canary authorization

---

## Next: Phase 3.5 — Intelligence Integration ✅

See `PHASE-3.5-INTELLIGENCE-INTEGRATION.md` — completed 2026-09-07.

## Next: Phase 3.6 — Workload Validation
