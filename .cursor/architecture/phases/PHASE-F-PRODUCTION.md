# Phase F — Production Launch

> **الحالة:** دليل مرجع — لا migration  
> **المتطلب:** Phases A–E مكتملة + load tests passed  
> **Checklist:** [production-readiness.md](../production-readiness.md)

## الهدف

إطلاق Production لـ 45,000 طالب / 20 مدرسة باستقرار 10 سنوات.

## Infrastructure — Phase 2 من اليوم الأول

```
Load Balancer
    ↓
App × 2 (4 vCPU, 8GB each)
    ↓
Redis (4GB — cache + queue)
    ↓
PgBouncer (port 6432)
    ↓
PostgreSQL Primary (16 vCPU, 32GB, 500GB NVMe)
    ↓ replication
Read Replica (8 vCPU, 16GB)
```

**لا Phase 1 single-node** لهذا الحجم — راجع `02-infrastructure-phases.md`.

## Pre-Launch Sequence

```
Week -4: Load test environment (copy of production schema + synthetic 45K data)
Week -3: Load tests 1–7 (capacity-planning.md)
Week -2: PITR restore test, DR drill
Week -1: production-readiness.md checklist 100%
Day 0:  Go-live with monitoring
Day 1–30: Daily slow query + backup review
```

## Load Tests — Mandatory

| # | Scenario | Target | سجّل في |
|---|----------|--------|---------|
| 1 | 500 concurrent login | P95 < 500ms | load-test-results.md |
| 2 | 900 attendance batches | All < 2s | load-test-results.md |
| 3 | 45K bulk attendance | < 60s | load-test-results.md |
| 4 | 20 school dashboards | P95 < 1s | load-test-results.md |
| 5 | Directorate report | P95 < 3s | load-test-results.md |
| 6 | 2000 stress test | Document limit | load-test-results.md |
| 7 | 10-year partition query | P95 < 200ms | load-test-results.md |

## Monitoring Stack

| Tool | Purpose |
|------|---------|
| pg_stat_statements | Slow queries |
| Prometheus + Grafana | CPU, RAM, disk, connections |
| Laravel Horizon | Queue depth |
| Alertmanager | Threshold alerts |

Alerts: راجع `production-readiness.md`.

## Backup & DR

```
RPO: 15 minutes (WAL archiving)
RTO: 1 hour (restore procedure documented)
Monthly: full restore test to staging
```

## Yearly Maintenance (10-Year Plan)

| When | Action |
|------|--------|
| Before each academic year | CREATE new attendance partition |
| End of year | REFRESH all MVs, run promotion job |
| Year 5+ | DETACH old partitions → archive storage |
| Quarterly | REINDEX if dead_pct > 30% |
| Monthly | PITR restore test |

## Post-Launch SLA

| Operation | P95 | Availability |
|-----------|-----|-------------|
| Core CRUD | < 500ms | 99.5% |
| Attendance batch | < 2s | 99.5% |
| Dashboards | < 3s | 99% |
| Bulk reports | Async < 5min | 99% |

## Go/No-Go Criteria

- [ ] All production-readiness checkboxes complete
- [ ] All 7 load tests passed
- [ ] PITR restore verified
- [ ] On-call runbook documented
- [ ] Rollback plan tested

## مراجع

- `production-readiness.md`
- `postgresql-tuning.md`
- `capacity-planning.md` (authoritative)
- `capacity-planning-45k.md` (baseline snapshot)
- `peak-hour-strategy.md`
- `load-test-results.md`
