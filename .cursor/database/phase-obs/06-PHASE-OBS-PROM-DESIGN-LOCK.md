# PHASE OBS — DESIGN LOCK (Prometheus metrics)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: OBS-PROM
Ballot: 05 LOCKED
```

## In

```text
- GET /api/v1/metrics
- Content-Type: text/plain; version=0.0.4; charset=utf-8
- Auth: Authorization Bearer = SIS_METRICS_TOKEN (required outside local/testing when empty)
- Gauges: sis_up, sis_info, sis_http_workload_p95_ms, sis_http_workload_samples, sis_http_workload_budget_exceeded, sis_process_memory_bytes
- Source: DatabaseHealthPort + HttpWorkloadReadRepository (latest per workload)
```

## Out

```text
- Composer prometheus packages
- Grafana provisioning
- Histograms/counters requiring in-process scrape state beyond snapshots
- Tenant labels
```

## Units

| Unit | Name | Status |
|------|------|--------|
| OBS-U03 | Prometheus HTTP | CLOSED |
| OBS-U04 | Closure | CLOSED (see 09) |
