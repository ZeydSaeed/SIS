# Database Workload Classification

> **Purpose:** Expert decisions differ by workload type — OLTP ≠ Reporting ≠ Bulk Import.  
> **Usage:** Intelligence Layer tags every query/event with workload class before optimization.

---

## Workload Classes

| Class | Examples | Default SLO profile |
|-------|----------|---------------------|
| **OLTP** | Student search, grade entry, enrollment | Strict P95 — see PERFORMANCE-BUDGET |
| **Dashboard** | School/directorate dashboards | Summary/MV preferred |
| **Reporting** | Term reports, transcripts | Async or replica; relaxed sync SLO |
| **Analytics** | Directorate aggregates, trends | MV or reporting DB |
| **Bulk Import** | 45K attendance batch, CSV import | Queue; no HTTP |
| **Bulk Export** | Certificate batch, export jobs | Queue; idempotent |
| **Background Jobs** | MV refresh, archive, notifications | Job duration SLO |
| **Administrative** | Schema migration, reindex | Maintenance window |

---

## Per-Class Policy Matrix

| Class | Priority | Cache | Replica | Optimization strategy |
|-------|----------|-------|---------|----------------------|
| OLTP | Highest | Hot reference only | Primary for writes | Index, query tune |
| Dashboard | High | MV/summary | Replica OK | MV, daily_summary |
| Reporting | Medium | Short TTL or none | Replica preferred | MV, partition prune |
| Analytics | Medium | None | Replica / reporting DB | MV, pre-aggregate |
| Bulk Import | High (throughput) | Invalidate after | Primary only | Batch write, COPY |
| Bulk Export | Medium | None | Replica OK | Cursor, queue |
| Background Jobs | Low (latency) | N/A | Either | Off-peak schedule |
| Administrative | Controlled | Flush if needed | Primary | Zero-downtime path |

---

## Workload-Aware Performance Budget

Budgets are **not one-size-fits-all**. See [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md) versions:

```yaml
budget_profile:
  normal_operations:
    student_search_p95_ms: 200
  peak_enrollment:
    student_search_p95_ms: 500      # relaxed during intake window
  bulk_import:
    attendance_batch_p95_ms: 2000   # 50 students batch
  reporting:
    async_job_max_minutes: 5
  certificate_generation:
    async_job_max_minutes: 30       # 45K certificates — different SLO
```

Tag optimization events with `workload_class` in context fingerprint.

---

## Expert Inference by Workload

```yaml
rule: slow_dashboard_not_oltp_fix
  when:
    workload_class: dashboard
    scans_raw_fact_table: true
    table_rows: "> 1000000"
  then:
    recommendation: "Use daily_section_summary or MV — not OLTP index on fact table"
    risk_tier: 2

rule: bulk_import_never_row_by_row
  when:
    workload_class: bulk_import
    operation: attendance
  then:
    recommendation: "Batch write pattern — COPY or multi-row INSERT"
    risk_tier: 0
    reference: batch-write-patterns.md
```

---

## Detection Signals

| Signal | Likely class |
|--------|--------------|
| Single-row INSERT/UPDATE, < 10 queries/request | OLTP |
| Aggregates GROUP BY school/date | Dashboard / Analytics |
| SELECT * large range + export format | Bulk Export |
| COPY / INSERT 1000+ rows | Bulk Import |
| REFRESH MATERIALIZED VIEW | Background Job |
| DDL in migration | Administrative |

Use: route name, job class, query pattern, time of day (peak-hour-strategy.md).

---

## Related

- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
- [DATABASE-OPTIMIZATION-CONTEXT.md](./DATABASE-OPTIMIZATION-CONTEXT.md)
- [peak-hour-strategy.md](./peak-hour-strategy.md)
- [batch-write-patterns.md](./batch-write-patterns.md)
