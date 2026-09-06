# Knowledge Drift Management

> **Purpose:** Rules and patterns that were correct may become wrong as workload evolves.  
> **Trigger:** Re-evaluate when environment drifts — not only when code changes.

---

## What Is Knowledge Drift?

```text
Historical baseline (when pattern LP-001 was validated)
        ↓
Current workload (12 months later)
        ↓
Drift detected
        ↓
Re-evaluate rules / patterns / budgets
```

**Example:**

```text
LP-001 success rate at validation: 82%
After 12 months, similar events: 41%
→ Pattern deprecated — drift likely cause
```

---

## Drift Signals

| Domain | Signal | Review action |
|--------|--------|---------------|
| Scale | Students ±50%, schools ±30% | Re-score all patterns |
| Data volume | Largest table 2× since baseline | Partition/index review |
| Query mix | Dashboard queries +200% | MV/cache review |
| PostgreSQL | Major version upgrade | All patterns → `under_review` |
| Hardware | Tier change (e.g. 16→32 vCPU) | Cost model + budget review |
| Pattern performance | Success rate < 40% over 10+ samples | Deprecate pattern |
| Index usage | Former hot index idx_scan → 0 | Index drift review |
| Budget | P95 breach 30+ consecutive days | Budget version bump |

---

## Drift Detection Workflow

```text
1. Weekly job: compare current fingerprint vs pattern baseline fingerprints
2. Compute drift_score per active pattern (0.0 = identical, 1.0 = unrelated)
3. If drift_score > 0.50 → mark pattern under_review
4. If success_rate drops below 40% → deprecate candidate
5. Alert DBA + document in quarterly learning review
6. Propose KB version bump (KB-2026.09 → KB-2026.12)
```

---

## Pattern Status Transitions

```text
active
  ↓ drift_score > 0.50 OR success_rate declining
under_review
  ↓ re-validated on staging + 5 new successful events
validated
  ↓ explicit promotion
active

under_review
  ↓ success_rate < 40% OR 3 consecutive failures
deprecated
```

Never skip `under_review` → direct to `active` after drift.

---

## Baseline Snapshots

Store quarterly baseline for comparison:

```yaml
baseline_snapshot:
  id: BASE-2026-Q3
  date: 2026-09-01
  context_fingerprint: { ... }      # see DATABASE-OPTIMIZATION-CONTEXT.md
  active_patterns: [LP-001 v2, LP-003 v1]
  knowledge_version: KB-2026.09
  performance_budget_version: PB-2026.09
  top_10_queries_by_total_time: [...]
  table_size_snapshot: [...]
```

Compare current state to latest baseline every quarter.

---

## Rule Versioning on Drift

When drift forces strategy change:

```text
IDX-007 v3 (active) → under_review
     ↓
Staging validation with new workload
     ↓
IDX-007 v4 (candidate) OR new rule IDX-012 v1
     ↓
ADR if structural change
```

Old version retained in audit — never silent overwrite.

---

## Integration Points

| Document | Drift role |
|----------|------------|
| DATABASE-OPTIMIZATION-LEARNING.md | Pattern success tracking |
| DATABASE-OPTIMIZATION-CONTEXT.md | Fingerprint comparison |
| PERFORMANCE-BUDGET.md | Budget version bumps |
| DATABASE-KNOWLEDGE-BASE.md | Rule deprecation |
| capacity-planning.md | Scale threshold triggers |

---

## Related

- [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md)
- [DATABASE-OPTIMIZATION-CONTEXT.md](./DATABASE-OPTIMIZATION-CONTEXT.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
