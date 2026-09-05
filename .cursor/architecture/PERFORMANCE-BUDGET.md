# Performance Budget

> **Rule:** Targets are defined by requirements, measured in production, and revised when workload changes.  
> **Not:** fixed numbers that never update.

## Measurement Method

- **Tool:** Laravel Pulse, pg_stat_statements, APM, load-test-results.md
- **Window:** Rolling 7-day P95/P99 in production (or staging with production-like data)
- **Review:** Quarterly, or when capacity threshold crossed (see capacity-planning.md)

---

## Operation Budgets

| Operation | P95 Target | P99 Target | Max DB Queries | Measurement |
|-----------|-----------|-----------|----------------|-------------|
| Login | 300 ms | 800 ms | 5 | APM |
| Student search | 200 ms | 500 ms | 3 | APM + EXPLAIN |
| Student profile (single) | 400 ms | 1 s | 8 | APM |
| Attendance batch (50 students) | 2 s | 5 s | 2 (batch) | Feature test + APM |
| Grade entry (single) | 500 ms | 1.2 s | 4 | APM |
| School dashboard | 1 s | 2.5 s | 0–2 (summary/MV) | APM |
| Directorate dashboard (20 schools) | 3 s | 6 s | 0–1 (MV) | APM |
| Bulk report (async) | N/A sync | < 5 min job | Queue | Job duration |
| 10-year student attendance history | 200 ms | 500 ms | 1–2 | EXPLAIN + partition pruning |

**Baseline context:** 45,000 active students, 20 schools — see capacity-planning.md  
**If workload changes:** revise targets after capacity review, not before.

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

When claiming an optimization, record:

```yaml
optimization:
  type: index | partition | cache | mv | config
  target: table_or_query
  before:
    p95_ms: 
    explain: 
    db_queries_per_request:
  after:
    p95_ms:
    explain:
    db_queries_per_request:
  decision: keep | revert | monitor
  reviewed_by:
  date:
```

Store in PR description or load-test-results.md.

---

## Budget Breach Workflow

```text
P95 exceeds budget for 7 days
        ↓
Identify slow queries (pg_stat_statements)
        ↓
EXPLAIN ANALYZE
        ↓
Apply smallest fix (index, query, cache, MV)
        ↓
Measure again
        ↓
If still breached → capacity review (capacity-planning.md)
```

---

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [capacity-planning.md](./capacity-planning.md)
- [load-test-results.md](./load-test-results.md)
- [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)
