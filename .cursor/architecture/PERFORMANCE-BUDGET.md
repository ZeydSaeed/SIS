# Performance Budget

> **Version:** PB-2026.09 (baseline — bump on capacity review or drift)  
> **Rule:** Targets are workload-aware, measured in production, and revised when workload changes.  
> **See:** [DATABASE-WORKLOAD-CLASSIFICATION.md](./DATABASE-WORKLOAD-CLASSIFICATION.md)

---

## Versioning

```yaml
performance_budget:
  version: PB-2026.09
  baseline:
    active_students: 45000
    schools: 20
  review_triggers:
    - student_count_change_pct: 50
    - p95_breach_days: 30
    - knowledge_drift_detected: true
```

On review → new version (e.g. `PB-2026.12`) with documented reason.

---

## Measurement Method

- **Tool:** Laravel Pulse, pg_stat_statements, APM, load-test-results.md
- **Window:** Rolling 7-day P95/P99 in production (or staging with production-like data)
- **Review:** Quarterly, or when capacity threshold crossed (see capacity-planning.md)
- **Tag:** Every measurement with `workload_class`

---

## Operation Budgets — Normal Workload

| Operation | Workload | P95 Target | P99 Target | Max DB Queries |
|-----------|----------|-----------|-----------|----------------|
| Login | OLTP | 300 ms | 800 ms | 5 |
| Student search | OLTP | 200 ms | 500 ms | 3 |
| Student profile | OLTP | 400 ms | 1 s | 8 |
| Attendance batch (50) | Bulk Import | 2 s | 5 s | 2 (batch) |
| Grade entry | OLTP | 500 ms | 1.2 s | 4 |
| School dashboard | Dashboard | 1 s | 2.5 s | 0–2 (summary/MV) |
| Directorate dashboard | Dashboard | 3 s | 6 s | 0–1 (MV) |
| Bulk report | Reporting | N/A sync | < 5 min job | Queue |
| 10-year attendance history | Reporting | 200 ms | 500 ms | 1–2 (partition prune) |
| Certificate generation (45K) | Bulk Export | N/A sync | < 30 min job | Queue |

---

## Workload-Aware Budget Overrides

| Profile | When | Example adjustment |
|---------|------|-------------------|
| **peak_enrollment** | Admission window | Student search P95 → 500 ms |
| **peak_attendance** | 8:00–8:30 daily | Write throughput > latency — see peak-hour-strategy.md |
| **bulk_import** | CSV / mass enrollment | Async only — no sync SLO |
| **maintenance_window** | DDL / REINDEX | Administrative — notify only |
| **degraded_replica** | Replica lag > 30s | Read routes to primary — relaxed read SLO |

Document active profile in ops runbook during known windows.

---

## Infrastructure Budgets

| Metric | Warning | Critical | Action |
|--------|---------|----------|--------|
| PostgreSQL CPU | 70% | 85% | Capacity review |
| Disk usage | 70% | 85% | Archive / expand |
| DB connections | 70% pool | 90% pool | PgBouncer tuning |
| Replication lag | 30 s | 120 s | Pause replica reads |
| Redis memory | 70% | 85% | Review cache keys |
| Cache hit ratio (reference) | < 90% | < 80% | TTL / key review |
| Error rate (5xx) | 0.5% | 1% | Incident |

---

## Query Budget Rules

| Rule | Limit |
|------|-------|
| API endpoint DB queries | ≤ 10 (target ≤ 5) |
| Dashboard page DB queries | ≤ 3 (prefer 0 via MV/summary) |
| N+1 pattern | 0 tolerance in production |
| Sequential scan on table > 1M rows | Must justify in EXPLAIN |

---

## Optimization Evidence Template

```yaml
optimization:
  type: index | partition | cache | mv | config
  target: table_or_query
  workload_class: OLTP | dashboard | ...
  performance_budget_version: PB-2026.09
  before:
    p95_ms:
    explain:
  after:
    p95_ms:
    explain:
  cost_analysis_ref: DATABASE-COST-MODEL.md
  simulation_id: SIM-2026-0912-001
  decision: keep | revert | monitor
```

---

## Budget Breach Workflow

```text
P95 exceeds budget for 7 days (same workload_class)
        ↓
Identify slow queries (pg_stat_statements)
        ↓
EXPLAIN ANALYZE
        ↓
What-if simulation (≥ 2 options) — DATABASE-SIMULATION-POLICY.md
        ↓
Cost model ranking — DATABASE-COST-MODEL.md
        ↓
Apply smallest winning fix
        ↓
Measure again
        ↓
If still breached → capacity review + budget version bump
```

---

## Related

- [DATABASE-WORKLOAD-CLASSIFICATION.md](./DATABASE-WORKLOAD-CLASSIFICATION.md)
- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [DATABASE-COST-MODEL.md](./DATABASE-COST-MODEL.md)
- [capacity-planning.md](./capacity-planning.md)
