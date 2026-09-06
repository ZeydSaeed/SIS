# Database Simulation & What-If Policy

> **Purpose:** Expert Engine must compare options before recommending — not jump to first fix.  
> **Rule:** No Tier 2+ production change without staging simulation results.

---

## When Simulation Is Required

| Tier | Simulation |
|------|--------------|
| 0–1 | Optional |
| 2 | Required on staging |
| 3 | Required on staging + production-like data volume |

---

## What-If Comparison Workflow

```text
Problem detected (metrics + EXPLAIN)
     ↓
Generate candidate options (≥ 2)
     ↓
Simulate each on staging
     ↓
Measure: P95, write overhead, storage, refresh cost
     ↓
Apply cost model (DATABASE-COST-MODEL.md)
     ↓
Rank options → recommend best total score
     ↓
Explainability package (DATABASE-INTELLIGENCE-SAFETY.md)
     ↓
Human approval → production
```

---

## Simulation Methods

| Option type | Staging method |
|-------------|----------------|
| New index | `CREATE INDEX CONCURRENTLY` on staging copy · EXPLAIN ANALYZE before/after |
| Partial index | Same + verify selectivity on production stats export |
| Partition change | Subset partition on copy table · EXPLAIN with pruning |
| Materialized View | Create MV · measure refresh time + query P95 |
| Cache layer | Enable cache · measure DB query count + hit ratio |
| Query rewrite | Deploy query variant · APM comparison |
| Config change | PgBouncer pool / PG param on staging load test |

Use anonymized production snapshot or seed-data-45k.md scaled subset.

---

## Comparison Output Template

```yaml
what_if_simulation:
  id: SIM-2026-0912-001
  problem: "Dashboard P95 4.2s on attendance aggregate"
  staging_data_profile: "45K students, 2 academic years, 90M attendance rows"

  options:
    - id: A
      type: index
      ddl: "CREATE INDEX CONCURRENTLY ix_att_school_date ON ..."
      p95_before_ms: 4200
      p95_after_ms: 420
      write_overhead_pct: 4
      storage_mb: 850

    - id: B
      type: partition_sub
      description: "Sub-partition top 5 schools"
      p95_after_ms: 300
      migration_risk: high
      ops_complexity: high

    - id: C
      type: materialized_view
      name: directorate_daily_summary
      p95_after_ms: 100
      refresh_seconds: 480
      storage_mb: 120

  ranking: [C, A, B]
  recommended: C
  cost_model_reference: DATABASE-COST-MODEL.md
  simulated_by: DBA
  date: 2026-09-12
```

---

## Simulation Fail Criteria

Do not recommend option if staging shows:

- P95 improvement < 20% (not worth Tier 2+ risk)
- Write overhead > 25% on write-heavy path
- Integrity gate failure
- Refresh time exceeds maintenance window
- EXPLAIN plan regression on unrelated critical queries

---

## Production Validation (Post-Deploy)

Simulation predicts — production confirms:

```text
Staging prediction: P95 4200 → 100 ms
Production (72h):     P95 4200 → 95 ms  → success
Production (72h):     P95 4200 → 800 ms → partial / rollback review
```

Log actual vs predicted in optimization event for learning loop.

---

## Related

- [DATABASE-COST-MODEL.md](./DATABASE-COST-MODEL.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [load-test-results.md](./load-test-results.md)
- [testing-strategy.md](./testing-strategy.md)
