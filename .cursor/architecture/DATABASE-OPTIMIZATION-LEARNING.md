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
  human_decision: approved              # approved | rejected | modified
  human_decision_reason: "Best cost score on staging"
  recency_weight: 1.0                   # see Recency Weighting below
```

Store in: team wiki, `optimization_events/` table (future), or migration PR descriptions.

**Prerequisite:** Do not start learning until Observability (Phase 2) produces reliable events. Garbage in → garbage knowledge.

---

## Human Feedback Learning

Learning from outcomes **and** expert decisions:

| human_decision | Learning action |
|----------------|-----------------|
| **approved** | Reinforce pattern if production success |
| **rejected** | Log anti-pattern with `human_decision_reason` |
| **modified** | Log what DBA chose instead — often more valuable than auto success |

```yaml
human_feedback_event:
  recommendation_id: REC-2026-0912-004
  expert_suggested: "Sub-partition by school_id"
  human_decision: rejected
  human_decision_reason: "Operational complexity too high for current team size"
  human_chose_instead: "Materialized View directorate_daily_summary"
  outcome_of_human_choice: success
```

Feed rejected/modified events into Knowledge Base anti-patterns and cost model weights.

---

## Recency Weighting

Recent events matter more than old ones:

| Event age | Default weight |
|-----------|----------------|
| 0–6 months | 1.0 |
| 6–12 months | 0.8 |
| 12–24 months | 0.6 |
| > 24 months | 0.4 (review for deprecation) |

Apply to success rate aggregation:

```text
weighted_success = Σ(outcome_success × recency_weight) / Σ(recency_weight)
```

Tune weights from calibration data — not fixed forever.

---

## Confidence Scoring (Multi-Factor)

`success_rate × context_similarity` is a **starting heuristic only**. Prefer:

```text
confidence = f(
  weighted_success_rate,     # with recency
  sample_size,               # 20/20 ≠ 900/1000
  context_similarity,
  bayesian_prior,            # domain baseline success for this change type
  risk_tier,
  data_criticality
)
```

### Sample size dampening

| Success rate | Sample | Interpretation |
|--------------|--------|----------------|
| 20/20 (100%) | 20 | Low confidence — small sample |
| 900/1000 (90%) | 1000 | High confidence |

Use Wilson score or Beta distribution for small samples (implementation Phase 8).

### Adjusted confidence (heuristic fallback)

```text
adjusted = weighted_success_rate × context_similarity × sample_factor

sample_factor:
  n < 10  → 0.5
  n < 30  → 0.7
  n ≥ 30  → min(1.0, n / 50)
```

Example:

```text
LP-001: 82% success, n=100, similarity 0.55 → adjusted ~0.45 → research only
LP-002: 78% success, n=22, similarity 0.94 → adjusted ~0.73 → standard workflow
```

---

## Confidence Calibration

Track whether stated confidence matches reality:

```yaml
calibration_report:
  period: 2026-Q3
  buckets:
    - predicted_confidence: 0.70-0.85
      recommendations: 24
      actual_success_rate: 0.71
      status: well_calibrated
    - predicted_confidence: 0.85-1.00
      recommendations: 12
      actual_success_rate: 0.58
      status: overconfident    # adjust formula or thresholds
```

Run quarterly with DBA review. Overconfident buckets → tighten promotion to Active patterns.

---

## Confidence ≠ Authorization

```text
Confidence = how likely the recommendation succeeds IF approved
Authorization = Risk Tier + Human Gate + Blast Radius
```

| Scenario | Result |
|----------|--------|
| Confidence 99% + Tier 4 (DROP FK) | **Forbidden** — never auto |
| Confidence 40% + Tier 1 (ANALYZE) | May auto with audit |
| Confidence 85% + Tier 3 | Human + ADR required |

See [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md).

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

**Do not start Phase 8 (Self-Learning jobs) before:**
- Phase 2 Observability live
- Phase 7 Optimization events table with validated data
- Baseline snapshot recorded
- Minimum 20 Tier 2+ events with integrity verification

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
| **Success** | **P95 reduction ≥ 30%** (lower is better) · write regression ≤ 10% · no correctness issues · stable 72h |
| **Partial** | P95 reduced but write regression 10–25% · monitor longer |
| **Failure** | P95 unchanged or worse · correctness issue · rolled back |
| **Rolled back** | Production regression within monitoring window |

Failed events are **equally valuable** — update Knowledge Base to avoid repeat mistakes.

---

## Confidence Scoring (Statistical + Context)

See **Multi-Factor Confidence**, **Recency Weighting**, **Calibration**, and **Confidence ≠ Authorization** sections above.

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

## Future Implementation (Phases 7–8)

When operational:

```text
Phase 2 Observability (Prometheus + pg_stat + APM)
     ↓
Phase 7 optimization_events table (append-only, validated)
     ↓
Phase 8 weekly learning job (Laravel Queue)
     ↓
Pattern report + calibration report → DBA review
     ↓
Knowledge Base version bump (PR + approval)
```

No ML required initially. Wilson/Beta confidence optional in Phase 8+. LLM optional for NL explanation only — never direct DB execution.

---

## Related

- [DATABASE-OPTIMIZATION-CONTEXT.md](./DATABASE-OPTIMIZATION-CONTEXT.md)
- [DATABASE-KNOWLEDGE-DRIFT.md](./DATABASE-KNOWLEDGE-DRIFT.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
- [DATABASE-KNOWLEDGE-BASE.md](./DATABASE-KNOWLEDGE-BASE.md)
- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
- [INDEX-GOVERNANCE.md](./INDEX-GOVERNANCE.md)
