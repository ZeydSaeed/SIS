# Optimization Context Fingerprint

> **Purpose:** Self-Learning must compare **similar contexts**, not just similar table names.  
> **Rule:** 100 events in one environment ≠ 20 events in a comparable environment.

---

## Why Context Matters

```text
20 schools  ≠  2,000 schools
PostgreSQL 15  ≠  PostgreSQL 17
45K students  ≠  500K students
```

Pattern confidence without context similarity produces **false confidence**.

---

## Context Fingerprint — Required Fields

Every optimization event (Tier 2+) MUST include:

```yaml
context_fingerprint:
  # Database environment
  postgres_version: "16.2"
  database_size_gb: 420
  replication_mode: "async_replica"    # none | sync | async_replica

  # Workload scale
  active_students: 45000
  schools: 20
  academic_years_retained: 10

  # Target object
  schema_table: "attendance.records"
  table_rows: 820000000
  table_growth_rate_monthly_pct: 8.2
  index_count_on_table: 6
  partition_strategy: "list_by_academic_year_id"

  # Query / workload
  query_pattern: "school_id + date_range filter"
  workload_class: "dashboard"            # see DATABASE-WORKLOAD-CLASSIFICATION.md
  dominant_access: "read_heavy"          # read_heavy | write_heavy | mixed

  # Infrastructure
  hardware_profile: "16vcpu_32gb_nvme"
  cache_strategy: "redis_reference_data"
  read_replica_in_use: true

  # Data distribution (v3.2 — affects selectivity, indexes, partial indexes)
  data_distribution:
    status_active_pct: 0.12          # e.g. 12% active students
    top_filter_selectivity: 0.08     # estimated rows matching dominant WHERE
    null_pct_on_filter_column: 0.01
    skew_notes: "Top 5 schools = 40% of rows"

  # Governance snapshot
  knowledge_version: "KB-2026.09"
  performance_budget_version: "PB-2026.09"
```

**10M rows with 99% one status value ≠ 10M rows with uniform distribution** — always include distribution in fingerprint for index/partition decisions.

---

## Context Similarity Score

Before applying a learned pattern, compute similarity:

```text
context_similarity =
  weighted_match(fingerprint_current, fingerprint_historical)

Weights (guideline):
  table_rows ratio:           20%
  growth_rate:                10%
  query_pattern match:        25%
  partition_strategy match:   15%
  schools/students scale:     15%
  postgres_major_version:     10%
  hardware tier:               5%
```

| Similarity | Use pattern? |
|------------|--------------|
| < 0.50 | Do not apply — research only |
| 0.50 – 0.70 | Apply with extra staging validation |
| 0.70 – 0.85 | Standard confidence adjustment |
| > 0.85 | High context match — boost confidence |

**Example:**

```text
LP-001 success rate: 82% (100 events)
Current context similarity to LP-001 events: 0.91
Adjusted confidence: 0.82 × 0.91 ≈ 0.75

LP-002 success rate: 78% (22 events)
Current context similarity: 0.94
Adjusted confidence: 0.78 × 0.94 ≈ 0.73  ← may beat LP-001 despite smaller sample
```

---

## Statistical Confidence (Not Event Count Alone)

```text
Confidence = f(
  historical_success_rate,
  sample_size,
  context_similarity,      ← required
  risk_tier,
  data_criticality         ← see SIS-DOMAIN-KNOWLEDGE-BASE.md
)
```

Minimum sample for pattern promotion:

| Condition | Minimum |
|-----------|---------|
| Raw event count | ≥ 20 similar events |
| Context-matched events | ≥ 10 with similarity > 0.70 |
| Time span | ≥ 90 days (avoid single-incident bias) |

---

## Context Drift Trigger

Re-evaluate patterns when fingerprint drifts:

| Signal | Action |
|--------|--------|
| Student count ±50% from pattern baseline | Re-score all active patterns |
| PostgreSQL major upgrade | Mark patterns `under_review` |
| Partition strategy changed | Invalidate partition-related patterns |
| Hardware tier changed | Re-weight cost model |

See [DATABASE-KNOWLEDGE-DRIFT.md](./DATABASE-KNOWLEDGE-DRIFT.md).

---

## Related

- [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md)
- [DATABASE-WORKLOAD-CLASSIFICATION.md](./DATABASE-WORKLOAD-CLASSIFICATION.md)
- [DATABASE-KNOWLEDGE-DRIFT.md](./DATABASE-KNOWLEDGE-DRIFT.md)
