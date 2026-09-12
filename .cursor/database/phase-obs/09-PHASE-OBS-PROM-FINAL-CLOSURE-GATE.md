# PHASE OBS — PROMETHEUS METRICS
# FINAL CLOSURE GATE (slice)

---

```text
Subphase: Phase OBS — Prometheus text scrape endpoint
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-13
```

## Observability Phase 2 matrix

| Slice | Status |
|-------|--------|
| HTTP request telemetry (3.4) | CLOSED |
| pg_stat_statements collector | CLOSED |
| HTTP workload coverage | CLOSED |
| Provisional module budgets | CLOSED |
| Prometheus text `/api/v1/metrics` | CLOSED |
| Vendor APM / Grafana | HOLD |

## Conditions

```text
- Bearer token required when SIS_METRICS_TOKEN set
- Empty token allowed only in local/testing
- No school_id / PII labels
- No new Composer packages
```

## Scrape example

```text
Authorization: Bearer $SIS_METRICS_TOKEN
GET /api/v1/metrics
```

## Recommended next

```text
1) Measured budget recalibration (evidence-driven), OR
2) COM-PROVIDER / FIN refund / WF role-step with explicit ballot, OR
3) Ranking/PDF only after reopening 7.5/7.8
```

```text
PHASE OBS PROM FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Hand-rolled Prometheus exposition live; vendor APM HOLD.
```
