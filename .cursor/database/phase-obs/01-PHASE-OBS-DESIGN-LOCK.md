# PHASE OBS — DESIGN LOCK (HTTP workload coverage)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: OBS-HTTP-WORKLOADS
Ballot: 00 LOCKED
```

## In

```text
- Exact route map (students, health) preserved
- Prefix fallback for api.finance.*, api.workflow.*, api.communication.*, …
- Provisional performance_budgets entries (PB provisional — revise on measured P95)
- Unit + feature tests for resolution + snapshot workload label
- PgStatStatementsCollector labels for finance/workflow/communication schemas
```

## Out

```text
- Prometheus /metrics exposition
- External APM
- New Composer packages
- Database migrations
```

## Units

| Unit | Name | Status |
|------|------|--------|
| OBS-U01 | Workload coverage | CLOSED |
| OBS-U02 | Closure | CLOSED (see 04) |
