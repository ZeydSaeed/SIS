# Adaptive Database Governance

> **Principle:** DO NOT OPTIMIZE FOR A NUMBER. OPTIMIZE FOR A MEASURED WORKLOAD.  
> **Layer:** Sits above DATABASE-GOVERNANCE.md — governs *how engineering responds* when the database evolves.

## Why This Exists

Static docs say:

```text
attendance MUST be partitioned
these indexes MUST exist
8 materialized views
45,000 students
```

After 5 years the system may be:

```text
150 tables · 60 schools · 120,000 students · 30 years
```

**Wrong response:** keep the same indexes because the doc said so.  
**Correct response:** re-evaluate based on current data volume, query patterns, and measurements.

This document defines **Optimization by Rules + Measurements**, not Optimization by Configuration.

---

## Architecture Feedback Loop

```text
              ┌──────────────────────┐
              │   Database Schema    │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Schema Change Audit  │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Data Growth Analysis   │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Query/Usage Analysis   │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Performance Metrics    │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Optimization Decision  │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Migration + Testing  │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Governance Approval  │
              └──────────┬───────────┘
                         ↓
              ┌──────────────────────┐
              │ Production           │
              └──────────┬───────────┘
                         ↓
                   Monitoring ──────────→ Feedback
```

---

## Priority Stack (Never Violate)

```text
Correctness        — grades, enrollment, audit must be accurate
      ↓
Security           — RLS, RBAC, no data leaks
      ↓
Integrity          — FK, constraints, no orphans
      ↓
Availability       — system up, backups work
      ↓
Performance        — measured, not assumed
      ↓
Storage cost       — right-size when evidence supports
```

**Optimization must never change business correctness.**

---

## 1. Schema Evolution

Every schema change triggers impact analysis — see [schema-change-impact.md](./schema-change-impact.md).

| Change Type | Required Analysis |
|-------------|-------------------|
| Add table | FK, indexes, RLS, cache, reports, tests |
| Add column | Nullability, backfill, index need, app impact |
| Remove column | Deprecation phase — zero-downtime-migrations.md |
| Rename column | Expand → migrate → contract |
| Change type | Compatibility, backfill, downtime assessment |
| Add FK | Index on FK column if used in JOIN/WHERE |
| Remove FK | Orphan risk — dependency search |
| Split / merge table | Data migration plan, dual-write period |

**Never:** `DROP TABLE` without full dependency discovery workflow.

---

## 2. Data Growth — Dynamic Capacity Model

Do not hard-code `45K × 200 × 5 = 45M`. Use variables:

```yaml
capacity_model:
  students:              variable    # baseline: 45000
  schools:               variable    # baseline: 20
  academic_days_per_year: variable   # baseline: 200
  records_per_student_per_day: variable  # baseline: 5
  retention_years:       variable    # baseline: 10
  annual_growth_rate:    variable    # measure annually
```

**Review thresholds** (triggers capacity review — not automatic redesign):

| Metric | Review Trigger |
|--------|----------------|
| Active students | +50% above baseline |
| Single table rows | > 10M (partition candidate) |
| Single table rows | > 100M (mandatory partition review) |
| DB size | > 80% disk capacity |
| P95 latency | > 2× performance budget |
| Replication lag | > 60s sustained |

See [capacity-planning.md](./capacity-planning.md) and [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md).

---

## 3. Index Adaptation

Indexes are **not mandatory forever**. Governed by:

| Factor | Source |
|--------|--------|
| Query frequency | pg_stat_statements, app logs |
| Query latency (P95) | APM, EXPLAIN ANALYZE |
| Selectivity | EXPLAIN, cardinality stats |
| Write overhead | INSERT/UPDATE rate on table |
| Table cardinality | pg_class.reltuples |
| Index usage | pg_stat_user_indexes.idx_scan |

### Decision Matrix

| Signal | Action |
|--------|--------|
| idx_scan = 0 for 90 days | Review for removal |
| Seq Scan on large table + frequent query | Candidate for new index |
| P95 improved > 30% after index | Keep, document in INDEX-GOVERNANCE |
| Write rate high + marginal read benefit | Reject index |

**New table with 10K rows:** do not auto-index every FK — analyze JOIN/WHERE patterns first.

Template: [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)

---

## 4. Partition Adaptation

**Not:** every large table = partitioned.  
**Not:** attendance always partitioned.

### Evaluate Partitioning WHEN

- Table size exceeds review threshold (~10M+ rows)
- Retention policy requires archival (detach old partitions)
- Queries consistently filter on partition key (pruning verified in EXPLAIN)
- Maintenance (VACUUM, backup) cost is high on monolithic table

### Evaluate De-Partitioning WHEN

- Table shrinks below threshold AND partitioning adds ops complexity with no measured benefit
- Partition pruning never occurs in production queries

### Partition Decision Tree

```text
Table Size + Growth Rate + Retention + Query Pattern + Maintenance Cost
                              ↓
                    Partition? YES / NO / DEFER
```

Document decision in ADR when choosing partition strategy for a new high-growth table.

---

## 5. Cache Adaptation (Redis)

Redis = performance layer only (ADR-004). Not everything gets cached.

```text
Cache Candidate?
      ↓
Read Frequency (high?)
      ↓
Change Frequency (low?)
      ↓
Cost of DB Query (expensive?)
      ↓
Consistency Requirement (eventual OK?)
      ↓
Cache? YES / NO / TTL only
```

| Signal | Action |
|--------|--------|
| Cache hit ratio < 50% for key pattern | Review TTL or remove |
| DB query cheap (< 5ms) | Remove cache |
| Stale data caused incident | Shorten TTL or remove cache |
| 10K+ reads/day on reference data | Keep cache |

See [cache-invalidation.md](./cache-invalidation.md)

---

## 6. Materialized View Adaptation

**Not:** exactly 8 MVs forever.

Evaluate MV candidacy:

| Factor | Threshold (guideline) |
|--------|----------------------|
| Query time without MV | > 2s |
| Query frequency | > 100/day |
| Refresh cost | Acceptable vs query savings |
| Freshness requirement | Eventual OK? |

| Signal | Action |
|--------|--------|
| MV refresh cost > query savings | Remove MV, optimize query or use summary table |
| Dashboard unused | Deprecate MV |
| New report needs aggregation | Add MV only after EXPLAIN proves need |

---

## 7. Relationship Impact Analysis

New relationship e.g. `students → student_transfers → schools`:

Checklist:

- [ ] Referential integrity: FK, ON DELETE, NULLABILITY
- [ ] Performance: JOIN patterns, indexes, cardinality
- [ ] Security: RLS, school scope
- [ ] Application: Models, DTOs, API, Queries
- [ ] Reports: MV impact
- [ ] Cache: invalidation keys
- [ ] Audit: who/when/why logged

Full workflow: [schema-change-impact.md](./schema-change-impact.md)

---

## 8. Table Removal — Hard Stop

```text
DROP TABLE request
        ↓
Dependency discovery (FK, views, MV, jobs, cache, API, reports)
        ↓
Deprecation period (rename → archive → verify zero usage)
        ↓
Backup snapshot
        ↓
Migration + rollback plan
        ↓
Governance approval
        ↓
DROP
```

**Academic official tables:** prefer archive over DROP unless legal policy requires deletion.

---

## 9. Logical vs Physical Model Separation

If DBMS or implementation changes after 10 years:

```text
Logical Model (stable)
  Student, School, Enrollment, Attendance, Grade, Certificate
        ↓
Physical Implementation (replaceable)
  PostgreSQL, BIGINT, RLS, Partitioning, Indexes, MV, JSONB
```

See [logical-data-architecture.md](./logical-data-architecture.md)

Logical model lives in: `database-blueprint.md`, `database-dictionary.md`, `erd-overview.md`  
Physical rules live in: `postgresql-tuning.md`, `INDEX-GOVERNANCE.md`, ADRs

---

## 10. Evidence-Based Optimization

Every optimization must be measurable:

| Claim | Required Evidence |
|-------|-------------------|
| "Index improves performance" | EXPLAIN before/after, P95 delta |
| "Partitioning helps" | EXPLAIN with partition pruning, query time |
| "Redis reduces load" | DB query count before/after |
| "MV worth refresh cost" | Dashboard P95 with/without MV |

Record in migration PR or [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md).

---

## Scenario Playbooks

### Add Table

```text
ADD → Impact Analysis → Index/FK/RLS/Cache/Report review → Tests → Migration
```

### Drop Table

```text
DROP REQUEST → Dependencies → Deprecation → Backup → Approval → DROP
```

### +100% Student Growth

```text
Threshold crossed → Capacity review → Query/storage analysis → Partition/index/cache review → Load test → Decision
```

### Student Count Decreases

```text
Lower utilization → Cost analysis → Unused index review → Right-size infra (do NOT remove indexes without evidence)
```

### Fundamental DB Change

```text
Logical model → Compatibility analysis → Physical redesign → Dual validation → Cutover → Rollback ready
```

---

## 11. Knowledge Drift

Rules and patterns may become wrong as workload evolves. Re-evaluate on drift — see DATABASE-KNOWLEDGE-DRIFT.md.

---

## Governance Document Stack

```text
DATABASE GOVERNANCE
├── DATABASE-GOVERNANCE.md           — static rules
├── DATABASE-ADAPTIVE-GOVERNANCE.md  — this file — dynamic response
├── DATABASE-INTELLIGENCE-LAYER.md   — expert + learning + self-healing design (v3.0)
├── DATABASE-KNOWLEDGE-BASE.md       — inference rules & heuristics
├── DATABASE-OPTIMIZATION-LEARNING.md — historical learning log
├── SELF-HEALING-RUNBOOK.md          — safe automated responses
├── schema-change-impact.md          — per-change analysis
├── DATABASE-CHANGE-CHECKLIST.md     — mandatory checklist
├── INDEX-GOVERNANCE.md              — index lifecycle
├── DATA-LIFECYCLE-MATRIX.md         — retention & archive
├── PERFORMANCE-BUDGET.md            — measurable targets
├── capacity-planning.md             — variable capacity model
├── logical-data-architecture.md     — logical vs physical
└── adr/                             — decision history (001–010)
```

**Maturity note:** Adaptive policies (this file) are **implemented in documentation**. Expert inference and Self-Learning require Phases 2–6 operational — see [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md).

---

## Related

- [DATABASE-GOVERNANCE.md](./DATABASE-GOVERNANCE.md)
- [capacity-planning.md](./capacity-planning.md)
- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
- [DATA-LIFECYCLE-MATRIX.md](./DATA-LIFECYCLE-MATRIX.md)
- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
- [adr/ADR-010-intelligence-layer.md](./adr/ADR-010-intelligence-layer.md)
