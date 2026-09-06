# Database Optimization Learning

> **Purpose:** Controlled Self-Learning — improve recommendations from historical outcomes.  
> **Scope:** Learning informs recommendations. It does **not** auto-execute schema changes.

---

## Learning Loop

```text
Historical Metrics
       ↓
Past Optimization Events
       ↓
Measured Results (before/after)
       ↓
Success / Failure Classification
       ↓
Pattern Extraction
       ↓
Updated Recommendation Confidence
       ↓
Knowledge Base Update (human-reviewed)
```

---

## Optimization Event Log

Every Tier 2+ optimization MUST be logged:

```yaml
optimization_event:
  id: OPT-2026-047
  date: 2026-09-15
  type: partition_strategy_review          # index_add | index_drop | partition | mv | cache
  table: attendance.records
  trigger:
    table_rows: 820000000
    p95_before_ms: 4200
    partition_pruning_rate: 0.12
  recommendation: "Evaluate sub-partition by school_id"
  action_taken: "Added sub-partitions for top 5 schools by volume"
  evidence:
    explain_before: "Seq Scan 780M rows"
    explain_after: "Partition pruning 94%"
  results:
    p95_after_ms: 380
    write_overhead_pct: 2
  outcome: success
  rollback_required: false
  rollback_verified: null              # true after rollback confirmation
  reviewer: "DBA + Tech Lead"
  adr_reference: ADR-002-partitioning
  context_fingerprint:                 # REQUIRED — see DATABASE-OPTIMIZATION-CONTEXT.md
    postgres_version: "16.2"
    active_students: 45000
    schools: 20
    table_rows: 820000000
    query_pattern: "school_id + date_range"
    workload_class: dashboard
    hardware_profile: "16vcpu_32gb_nvme"
  simulation_id: SIM-2026-0912-001     # see DATABASE-SIMULATION-POLICY.md
  rule_id: PART-003
  rule_version: 2
  knowledge_version: KB-2026.09
```

Store in: team wiki, `optimization_events/` table (future), or migration PR descriptions.

---

## Pattern Lifecycle (Never Direct KB Update)

```text
Candidate → Validated → Active → Deprecated
```

| Status | Meaning |
|--------|---------|
| **candidate** | Extracted from events — not used in production recommendations |
| **validated** | DBA review + benchmark + historical validation passed |
| **active** | Promoted — used by Expert Engine with confidence |
| **deprecated** | Drift or success rate < 40% — do not use |

Promotion requires human review. See [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md).

---

## Pattern Extraction (Context-Aware)

After **N ≥ 20** similar events **with context similarity > 0.70**:

```yaml
learned_pattern:
  id: LP-001
  version: 2
  confidence: 0.82
  sample_size: 100
  context_matched_samples: 47          # similarity > 0.70
  conditions:
    - table: attendance.records
    - rows: "> 500000000"
    - pruning_rate: "< 50%"
    - dominant_filter: "school_id + date_range"
  successful_action: "sub-partition or composite partition key"
  failed_action: "add more indexes without partition change"
  last_validated: 2026-09-01
  status: validated                    # candidate | validated | active | deprecated
  knowledge_version: KB-2026.09
```

**20 events in 20-school env ≠ 20 events in 2000-school env** — use context similarity, not count alone.

---

## Success / Failure Criteria

| Outcome | Criteria |
|---------|----------|
| **Success** | P95 improved ≥ 30% · no write regression > 10% · no correctness issues · stable 72h |
| **Partial** | P95 improved but write regression 10–25% · monitor longer |
| **Failure** | P95 unchanged or worse · correctness issue · rolled back |
| **Rolled back** | Production regression within monitoring window |

Failed events are **equally valuable** — update Knowledge Base to avoid repeat mistakes.

---

## Confidence Scoring (Statistical + Context)

```text
Confidence = f(
  historical_success_rate,
  sample_size,
  context_similarity,        # REQUIRED — DATABASE-OPTIMIZATION-CONTEXT.md
  risk_tier,
  data_criticality           # SIS-DOMAIN-KNOWLEDGE-BASE.md
)

Adjusted confidence = raw_success_rate × context_similarity
```

Example:

```text
LP-001: 82% success, 100 events, context_similarity 0.55 → adjusted 0.45 → research only
LP-002: 78% success, 22 events, context_similarity 0.94 → adjusted 0.73 → standard workflow
```

| Confidence | UI / Process |
|------------|--------------|
| < 0.50 | Research only — do not recommend for production |
| 0.50 – 0.70 | Recommend with strong staging validation |
| 0.70 – 0.85 | Standard approval workflow |
| > 0.85 | High confidence — still requires human gate for Tier 2+ |

**Never auto-execute** based on confidence alone for Tier 2+.

---

## What the System Learns (Allowed)

| Learnable | Example |
|-----------|---------|
| Index effectiveness by table size + query pattern | Partial index on status=1 works until 50M rows |
| Partition key fit | academic_year_id works until school-scoped queries dominate |
| MV refresh cost vs benefit | Dashboard MV worth it at > 1000 queries/day |
| Cache TTL sweet spot | School list cache 1h optimal at current change rate |
| Failed optimizations to avoid | Dropping composite index X caused report regression |

## What the System Must NOT Learn (Forbidden)

| Forbidden | Why |
|-----------|-----|
| Auto-drop indexes without approval | Data integrity + report risk |
| Auto-change RLS policies | Security |
| Auto-modify FK constraints | Referential integrity |
| Override PERFORMANCE-BUDGET without human | Operational agreement |
| Treat Redis as source of truth | Architecture violation |

---

## Quarterly Learning Review

```text
1. Export optimization events (last 90 days)
2. Calculate success rate by type (index, partition, mv, cache)
3. Identify top 3 successful patterns → propose Knowledge Base update
4. Identify top 3 failures → add anti-patterns
5. Deprecate patterns with success rate < 40% OR drift_score > 0.50 — see DATABASE-KNOWLEDGE-DRIFT.md
6. Update confidence scores
7. Document in improvement-matrix or ADR if strategy shifts
```

---

## Future Implementation (Phase 6)

When operational:

```text
PostgreSQL pg_stat + application metrics
     ↓
optimization_events table (append-only audit)
     ↓
Batch analysis job (weekly — Laravel Queue)
     ↓
Pattern report → DBA review
     ↓
Knowledge Base YAML update (PR + approval)
```

No ML required for Phase 6. Statistical aggregation + human review is sufficient.

Phase 7+ may add ML for anomaly detection — still with human gate for schema changes.

---

## Related

- [DATABASE-OPTIMIZATION-CONTEXT.md](./DATABASE-OPTIMIZATION-CONTEXT.md)
- [DATABASE-KNOWLEDGE-DRIFT.md](./DATABASE-KNOWLEDGE-DRIFT.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
- [DATABASE-KNOWLEDGE-BASE.md](./DATABASE-KNOWLEDGE-BASE.md)
- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
- [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)
