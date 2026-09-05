# Capacity Planning — Dynamic Model

> **45K is baseline, not architectural ceiling.**  
> **Rule:** architecture_decisions must be measurement_based, workload_based, evidence_based.

---

## Variable Capacity Model

```yaml
capacity_model:
  # Current baseline (2026) — update when measured reality changes
  baseline:
    students: 45000
    schools: 20
    sections: 900
    teachers: 1500

  # Variables — never hard-code projections
  variables:
    students: variable
    schools: variable
    academic_days_per_year: variable      # baseline: 200
    attendance_records_per_student_per_day: variable  # baseline: 5
    grade_records_per_student_per_year: variable      # baseline: 60
    retention_years: variable             # baseline: 10
    annual_growth_rate: variable          # measure each year

  # Formula (recalculate when variables change)
  formulas:
    attendance_rows_per_year: >
      students × academic_days_per_year × attendance_records_per_student_per_day
    attendance_rows_total: >
      sum per year accounting for retention_years and growth_rate
```

### Example Calculation (Baseline)

```
45,000 × 200 × 5 = 45,000,000 attendance rows/year
10 years retention ≈ 450M rows (if stable enrollment)
```

**If variables change** (e.g. 3 records/day not 5), recalculate — do not keep stale numbers in docs.

---

## Growth Scenarios (Review Triggers — Not Prescriptive Architecture)

| Scenario | Students | Schools | Trigger |
|----------|----------|---------|---------|
| Shrink | 8,000 | 10 | Right-size review |
| Small | 25,000 | 12 | Baseline− |
| **Baseline** | **45,000** | **20** | Current design reference |
| Growth A | 75,000 | 25 | Capacity review |
| Growth B | 100,000 | 30 | Partition + replica review |
| Growth C | 250,000 | 50 | Architecture review (ADR) |
| Growth D | 500,000+ | 100+ | Major architecture review |

Crossing a threshold **starts review** — does not automatically mandate specific technology.

---

## Review Checklist (When Threshold Crossed)

- [ ] DB size and largest tables (pg_table_size)
- [ ] Index size and unused indexes
- [ ] TPS / QPS (pg_stat_database)
- [ ] CPU, RAM, IOPS, disk
- [ ] Cache hit ratio
- [ ] Query latency P95/P99 vs PERFORMANCE-BUDGET.md
- [ ] Replication lag
- [ ] Connection pool utilization
- [ ] Autovacuum / bloat
- [ ] Partition count and prune effectiveness
- [ ] Backup duration and WAL growth
- [ ] Load test at new scale

Document outcome in ADR if architecture decision changes.

---

## Storage Projection Template

```yaml
table: attendance.records
current_rows: MEASURE
growth_rate_per_year: MEASURE
avg_row_bytes: ~100
index_multiplier: ~3x
projected_5yr_gb: CALCULATE
partition_strategy: REVIEW
```

Run `ANALYZE` and query `pg_class` — do not estimate from docs alone.

---

## Infrastructure Right-Sizing

| Workload | Primary PG | Replica | Redis | App Nodes |
|----------|-----------|---------|-------|-----------|
| Baseline 45K | 16 vCPU / 32GB | 8 vCPU / 16GB | 4 GB | 2 |
| 75K | Review CPU/IO | Keep | Review | Review |
| 100K+ | Scale up or partition tune | Required | Required | 3+ |

Decisions based on **measured** CPU/IO/latency — not student count alone.

---

## What Does NOT Change With Scale

These remain true at 8K or 250K students:

- Normalization (3NF) in OLTP
- PostgreSQL as source of truth
- Audit trail append-only
- Governance workflow for schema changes
- Evidence-based optimization
- Logical data model (entities and relationships)

## What MAY Change With Scale

- Partition strategy (add sub-partitions, archive cadence)
- Index set (add/remove based on pg_stat)
- MV count and refresh frequency
- Replica count
- Cache scope and TTL
- Batch sizes and worker count
- Hardware tier

---

## Related

- [capacity-planning-45k.md](./capacity-planning-45k.md) — baseline snapshot (legacy alias)
- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
- [DATA-LIFECYCLE-MATRIX.md](./DATA-LIFECYCLE-MATRIX.md)
