# PHASE OBS — PROMETHEUS METRICS BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر»
Status: LOCKED — recommended defaults adopted
Slice: OBS-PROM (Phase 2 Observability)
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-OBS-PROM-001 | Open Prometheus scrape now? | A yes · B hold | **A yes** |
| HD-OBS-PROM-002 | Implementation | A hand-rolled text · B Composer client | **A hand-rolled** |
| HD-OBS-PROM-003 | Route | A /api/v1/metrics · B /metrics root | **A /api/v1/metrics** |
| HD-OBS-PROM-004 | Auth | A public · B bearer token · C Sanctum+school | **B bearer (SIS_METRICS_TOKEN)** |
| HD-OBS-PROM-005 | Empty token | A allow always · B allow local/testing only | **B local/testing only** |
| HD-OBS-PROM-006 | Labels | A include school_id · B no tenant labels | **B no tenant / PII labels** |
| HD-OBS-PROM-007 | Schema | A migrate · B none | **B none** |

## Implications

```text
IN: GetPrometheusMetrics + MetricsTokenMiddleware + gauges from health/http_workload
OUT: Grafana dashboards, vendor APM, school-scoped metrics, new packages
```
