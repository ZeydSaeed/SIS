# Database Optimization Cost Model

> **Rule:** Fastest solution ≠ best solution. Optimize for **total cost**, not latency alone.  
> **Usage:** Expert Engine ranks options using performance + operational cost.

---

## Cost Dimensions

Every optimization option is scored on:

| Dimension | Measures |
|-----------|----------|
| **Performance** | P95/P99 delta, query count reduction |
| **Storage** | Index size, partition overhead, MV disk |
| **CPU** | Query CPU, refresh CPU, vacuum cost |
| **RAM** | shared_buffers pressure, cache memory |
| **WAL / I/O** | Write amplification, replication lag impact |
| **Replication** | Lag increase, replica catch-up time |
| **Maintenance** | VACUUM duration, REINDEX frequency, ops hours |
| **Operational complexity** | Runbook steps, failure modes, rollback difficulty |
| **Development cost** | Migration effort, app changes, test surface |

---

## Option Scoring Template

Before recommending, compare alternatives:

```yaml
optimization_comparison:
  query: "Directorate dashboard — 20 schools aggregate"
  workload_class: dashboard

  option_a:
    name: "B-Tree index on (school_id, date)"
    expected_p95_ms: 420
    write_overhead_pct: 4
    storage_mb: 850
    replication_impact: low
    maintenance: low
    complexity: low
    risk_tier: 2
    total_score: 72                    # weighted — see below

  option_b:
    name: "Sub-partition attendance by school"
    expected_p95_ms: 300
    write_overhead_pct: 2
    storage_mb: 0
    replication_impact: medium
    maintenance: high
    complexity: high
    risk_tier: 3
    total_score: 65

  option_c:
    name: "Materialized View — directorate_daily_summary"
    expected_p95_ms: 100
    refresh_cost_minutes: 8
    storage_mb: 120
    replication_impact: low
    maintenance: medium
    complexity: medium
    risk_tier: 2
    total_score: 88

  recommendation: option_c
  rationale: "Best P95 with acceptable refresh cost; lowest ops risk for dashboard workload"
```

---

## Weighting by Workload

| Workload | Performance weight | Storage weight | Maintenance weight |
|----------|-------------------|----------------|-------------------|
| OLTP | 40% | 15% | 15% |
| Dashboard | 35% | 20% | 20% |
| Reporting | 25% | 25% | 25% |
| Bulk Import | 30% (throughput) | 10% | 20% |

Adjust weights in ADR for major architecture shifts.

---

## Reject Criteria (Even If Faster)

Reject optimization if:

| Condition | Example |
|-----------|---------|
| Storage > 3× current with marginal P95 gain | 10GB index saves 50ms |
| Write overhead > 25% on write-heavy table | attendance peak |
| Replication lag increase > 60s sustained | heavy index on primary |
| Maintenance window > 4h/month | complex partition ops |
| Rollback complexity = Tier 4 risk | irreversible without DR |
| CRITICAL table + unproven integrity path | grades table experiment |

---

## Cost vs Performance Trade-off Example

```text
Query P95: 4000 ms

Option A — Index:
  P95 → 420 ms
  Storage +850 MB
  Write +4%
  Score: 72

Option B — MV:
  P95 → 100 ms
  Refresh 8 min/night
  Storage +120 MB
  Score: 88

Recommendation: Option B (dashboard workload)
```

Not: "Option B is faster" — but "Option B wins on total cost for this workload."

---

## Evidence Required

Record in optimization event:

```yaml
cost_analysis:
  options_compared: [A, B, C]
  recommended: C
  performance_delta_p95_pct: 97
  storage_delta_mb: 120
  write_overhead_pct: 0
  refresh_cost_minutes: 8
  reviewed_by: DBA
```

---

## Related

- [DATABASE-SIMULATION-POLICY.md](./DATABASE-SIMULATION-POLICY.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
