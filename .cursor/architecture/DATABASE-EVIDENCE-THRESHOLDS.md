# Adaptive Evidence-Based Thresholds

> **Purpose:** Thresholds like `10M rows` or `90 days` are **review triggers**, not sacred laws.  
> **Goal:** Over time, derive thresholds from **when performance actually degraded** in this deployment.

---

## Static vs Evidence-Based

| Approach | Example | Problem |
|----------|---------|---------|
| **Static** | `table_rows > 100M → partition` | May partition too early or too late |
| **Evidence-based** | `P95 degraded at ~80M rows for this query pattern → review at 70M` | Tuned to measured workload |

Static thresholds in docs = **starting points**. Replace with measured thresholds when data exists.

---

## Threshold Learning Workflow

```text
Monitor table size + query P95 over time
     ↓
Record degradation point (when P95 crossed budget)
     ↓
Log as optimization_event or threshold_observation
     ↓
After N observations → propose evidence-based threshold
     ↓
Human review → update Knowledge Base rule version
     ↓
Deprecate static threshold if evidence contradicts
```

---

## Threshold Observation Log

```yaml
threshold_observation:
  id: THR-2026-012
  table: attendance.records
  metric: p95_dashboard_ms
  observations:
    - at_rows: 20000000
      p95_ms: 180
      status: stable
    - at_rows: 50000000
      p95_ms: 220
      status: stable
    - at_rows: 80000000
      p95_ms: 890
      status: degraded
    - at_rows: 100000000
      p95_ms: 2100
      status: breached
  proposed_review_trigger_rows: 70000000    # 10–15% before degradation
  rule_id: PART-003
  rule_version: 4
  status: candidate
  reviewed_by: null
```

---

## Default Static Triggers (Until Evidence Replaces)

| Metric | Static trigger | Replace when |
|--------|----------------|--------------|
| Table rows (partition review) | 10M consider · 100M strong | 3+ degradation observations |
| idx_scan unused | 90 days | Confirmed no periodic query in app + reports |
| Student count review | +50% baseline | Measured capacity review completed |
| P95 budget breach | 7 consecutive days | Workload misclassified — adjust budget profile |
| Pattern deprecation | success < 40% | Drift doc — DATABASE-KNOWLEDGE-DRIFT.md |

---

## False Positive Guards

Before firing threshold alert:

| Check | Why |
|-------|-----|
| Workload class | Bulk import spike ≠ OLTP regression |
| Time window | Peak attendance 8:00–8:30 ≠ all-day breach |
| One-off job | MV refresh ≠ user-facing SLO |
| Replica lag | Read path degraded ≠ write path |

See [DATABASE-WORKLOAD-CLASSIFICATION.md](./DATABASE-WORKLOAD-CLASSIFICATION.md).

---

## Integration with Self-Learning

Evidence-based thresholds become **learned patterns**:

```yaml
learned_pattern:
  id: LP-THR-001
  type: evidence_threshold
  condition: "attendance.records dashboard P95 degrades"
  evidence_trigger_rows: 70000000
  previous_static_trigger: 100000000
  sample_observations: 4
  status: validated
```

---

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md)
- [DATABASE-KNOWLEDGE-DRIFT.md](./DATABASE-KNOWLEDGE-DRIFT.md)
