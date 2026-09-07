# Phase 2A Telemetry

## Semantic States

Metrics use explicit status — **never UNKNOWN → 0**.

| Field | Status key | Values |
|-------|------------|--------|
| error_rate_pct | error_rate_status | MEASURED, UNKNOWN, UNAVAILABLE |
| queue_depth/latency | queue_status | MEASURED, UNAVAILABLE |
| cpu_pct | cpu_status | MEASURED, UNKNOWN |

## Telemetry Matrix

| Metric | Source | Measured? | Unknown Possible? | Blocks Autonomous? | Tested? |
| ------ | ------ | --------: | ----------------: | -----------------: | ------: |
| CPU | sys_getloadavg / env cores | Partial | Yes | No | S1 |
| Memory | memory_get_usage | Yes | No | No | S1 |
| DB query p95/p99 | QueryMetric | When data exists | Yes | Indirect (guard) | S11 |
| Cache hit ratio | MonitoringSnapshot | When snapshot exists | Yes | Yes (guard) | S5 |
| Error rate | failed_jobs + QueryMetric proxy | When both exist | Yes | No (not in critical guard set) | S8 |
| Queue depth | Queue::size() | When not sync driver | N/A on sync | No | — |
| Queue latency | jobs.created_at delta | database driver only | Yes | No | — |
| HTTP request latency | **Not collected** | No | — | — | — |

Note: `p95_latency_ms` in telemetry is **query latency**, labeled explicitly via `db_query_latency_note`.

## Providers

- `app/Optimization/Telemetry/ErrorRateTelemetryProvider.php`
- `app/Optimization/Telemetry/QueueTelemetryProvider.php`
