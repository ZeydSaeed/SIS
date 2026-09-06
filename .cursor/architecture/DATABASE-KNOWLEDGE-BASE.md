# Database Knowledge Base

> **Version:** KB-2026.09 — bump on drift or quarterly review  
> **Purpose:** Expert inference rules — not executable code.  
> **Companion:** [SIS-DOMAIN-KNOWLEDGE-BASE.md](./SIS-DOMAIN-KNOWLEDGE-BASE.md) for business criticality.

---

## Rule Versioning

Every rule is versioned and auditable:

```yaml
rule:
  id: IDX-007
  version: 3
  knowledge_version: KB-2026.09
  status: active                    # candidate | active | deprecated
  superseded_by: null               # IDX-007 v4 if replaced
```

Recommendations MUST cite: `rule_id`, `rule_version`, `knowledge_version`.

---

## Structure

```text
Knowledge Base
│
├── Indexing Knowledge
├── Partitioning Knowledge
├── Query Optimization Knowledge
├── Caching Knowledge
├── PostgreSQL Knowledge
├── Capacity Knowledge
├── Security Knowledge
├── Data Lifecycle Knowledge
├── Failure / Recovery Knowledge
└── SIS Domain Knowledge  →  SIS-DOMAIN-KNOWLEDGE-BASE.md
```

Each domain contains: **signals**, **inference rules**, **recommended actions**, **risk tier**, **evidence required**.

---

## 1. Indexing Knowledge

### Signals

| Signal | Threshold (review trigger) |
|--------|---------------------------|
| Seq Scan on table > 1M rows | Any frequent query |
| idx_scan = 0 for 90+ days | Index size > 10 MB |
| INSERT/UPDATE latency ↑ after index add | Write rate > 10K/day |
| FK column in JOIN/WHERE | Table > 100K rows |

### Inference Rules

```yaml
rule: index_candidate_fk
  when:
    - column is foreign_key
    - used_in: [JOIN, WHERE]
    - table_rows: "> 100000"
    - no_existing_index_on_column: true
  then:
    recommendation: "Add B-Tree index on FK column"
    risk_tier: 2
    evidence: [EXPLAIN before, query frequency per day]

rule: index_removal_candidate
  when:
    - idx_scan: 0
    - days_since_last_scan: "> 90"
    - index_size_mb: "> 10"
    - not_primary_or_unique: true
  then:
    recommendation: "Review for removal — confirm no periodic/report query"
    risk_tier: 3
    evidence: [pg_stat_user_indexes, application grep, report inventory]
```

### Anti-Patterns (Never Infer)

- Auto-index every FK on tables < 10K rows
- Drop index because "AI thinks it's unused" without 90-day stat + app search

---

## 2. Partitioning Knowledge

### Signals

| Signal | Review trigger |
|--------|----------------|
| Table rows | > 10M (consider) · > 100M (strong candidate) |
| Partition pruning rate | < 50% on dominant queries |
| Retention policy | Requires drop/archive by year |
| VACUUM duration | > 30 min per partition |

### Inference Rules

```yaml
rule: partition_strategy_mismatch
  when:
    - table_rows: "> 100000000"
    - partition_pruning_rate: "< 50%"
    - dominant_filter: "not equal to partition_key"
  then:
    diagnosis: >
      Partition key may not match query pattern.
      Evaluate composite key or sub-partitioning.
    recommendation: "Capacity review + ADR + staging benchmark"
    risk_tier: 3
    evidence: [EXPLAIN with partition pruning, query log analysis]

rule: partition_not_needed
  when:
    - table_rows: "< 1000000"
    - retention_years: "< 3"
    - maintenance_cost: "high relative to benefit"
  then:
    recommendation: "Defer partitioning — reassess at next capacity review"
    risk_tier: 0
```

### SIS-Specific Heuristics

| Table | Baseline partition key | Re-evaluate when |
|-------|------------------------|------------------|
| attendance.records | academic_year_id | Pruning < 80% OR rows > 500M |
| audit.audit_logs | created_at (monthly) | Archive policy changes |
| exams.student_grades | academic_year_id | Query pattern shifts to student-centric |

---

## 3. Query Optimization Knowledge

```yaml
rule: n_plus_one_detected
  when:
    - query_count_per_request: "> 20"
    - same_table_repeated: true
  then:
    recommendation: "Eager load relationship in Laravel — not DB index"
    risk_tier: 0

rule: slow_dashboard
  when:
    - operation: dashboard
    - p95_ms: "> performance_budget.target"
    - scans_raw_fact_table: true
  then:
    recommendation: "Use daily_section_summary or MV — not raw OLTP scan"
    risk_tier: 2
    evidence: [EXPLAIN, dashboard query log]
```

---

## 4. Caching Knowledge

```yaml
rule: cache_candidate
  when:
    - read_frequency: "high"
    - change_frequency: "low"
    - db_query_cost_ms: "> 50"
    - consistency: "eventual acceptable"
  then:
    recommendation: "Redis cache with TTL + invalidation key"
    risk_tier: 1

rule: cache_removal
  when:
    - cache_hit_rate: "< 20%"
    - data_size: "small (< 1MB)"
    - db_query_cost_ms: "< 10"
  then:
    recommendation: "Remove cache — DB is faster/cheaper"
    risk_tier: 1
```

**Never cache:** grades, enrollment status, payment state as source of truth.

---

## 5. PostgreSQL Knowledge

| Symptom | Likely cause | Safe action (Tier) |
|---------|--------------|-------------------|
| dead_pct > 30% | Index bloat | REINDEX CONCURRENTLY (2) |
| autovacuum not running | long transactions | Alert + kill long queries (1) |
| replication lag > 30s | write spike | Route reads to primary (1) |
| connection count > 80% max | pool misconfig | PgBouncer pool resize (1) |
| seq_scan on large table | missing index | Recommend index (2) |

---

## 6. Capacity Knowledge

```yaml
rule: capacity_review_trigger
  when_any:
    - student_count_change_pct: "> 50"
    - largest_table_rows: "> 100000000"
    - storage_growth_monthly_pct: "> 20"
    - p95_breach_days_consecutive: "> 30"
  then:
    recommendation: "Full capacity review — see capacity-planning.md"
    checklist:
      - db_size, table_size, index_size
      - tps, qps, cpu, ram, iops
      - cache_hit_ratio, replication_lag
      - partition_count, vacuum_performance
```

Growth thresholds are **review points**, not hard limits. See [capacity-planning.md](./capacity-planning.md).

---

## 7. Security Knowledge

```yaml
rule: new_table_rls
  when:
    - table_has: school_id
    - multi_tenant: true
  then:
    recommendation: "RLS policy required before production"
    risk_tier: 3
    forbidden_auto: true

rule: pii_column_added
  when:
    - column_class: PII
  then:
    recommendation: "Update dictionary, audit policy, encryption review"
    risk_tier: 2
```

---

## 8. Data Lifecycle Knowledge

Cross-reference [DATA-LIFECYCLE-MATRIX.md](./DATA-LIFECYCLE-MATRIX.md).

```yaml
rule: archive_candidate
  when:
    - data_age_years: "> retention_policy.active_years"
    - query_frequency: "low"
  then:
    recommendation: "Archive to cold storage — do not hard-delete academic records"
    risk_tier: 3
```

---

## 9. Failure / Recovery Knowledge

Cross-reference [dr-runbook.md](./dr-runbook.md) and [SELF-HEALING-RUNBOOK.md](./SELF-HEALING-RUNBOOK.md).

| Failure | Diagnosis path | First response |
|---------|----------------|----------------|
| Primary down | Health check fail | Failover per DR runbook |
| Replica lag | replication_lag metric | Stop replica reads (Tier 1) |
| Connection exhaustion | pool saturation | Scale pool / alert (Tier 1) |
| Disk > 85% | storage metric | Alert + archive review (Tier 0) |

---

## Knowledge Base Maintenance

| Activity | Frequency | Owner |
|----------|-----------|-------|
| Review inference rules vs production metrics | Quarterly | DBA + Tech Lead |
| Drift detection on active patterns | Weekly (when operational) | See DATABASE-KNOWLEDGE-DRIFT.md |
| Add rule from successful optimization | After each Tier 3 change | Developer |
| Deprecate rule if consistently wrong | After 3 failed inferences or drift | DBA |
| Sync with ADRs | On new ADR | Tech Lead |
| Bump knowledge_version | On material rule change | DBA + PR |

---

## Related

- [SIS-DOMAIN-KNOWLEDGE-BASE.md](./SIS-DOMAIN-KNOWLEDGE-BASE.md)
- [DATABASE-KNOWLEDGE-DRIFT.md](./DATABASE-KNOWLEDGE-DRIFT.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
