# Database Intelligence Safety

> **Purpose:** Gates that prevent intelligence layer from harming correctness, integrity, or security.  
> **Principle:** Correctness > Security > Integrity > Availability > Performance > Storage

---

## Non-Negotiable Boundaries

```text
PostgreSQL + FK + RLS + Audit = Source of Truth

Intelligence Layer NEVER:
  ├── DROP TABLE / COLUMN
  ├── DROP INDEX (without Tier 3 + human)
  ├── ALTER FK / disable RLS
  ├── Modify grades / enrollment / attendance data
  └── Override transactional correctness
```

LLM (if used later) sits **above** Policy Engine — never executes directly on DB.

```text
LLM → Explanation / NL Diagnosis
   ↓
Structured Recommendation (JSON)
   ↓
Policy Engine → Risk Engine → Approval → Execution
```

---

## Automation Risk Tiers (Authoritative)

| Tier | Examples | Automation |
|------|----------|------------|
| **0 — Observe** | Slow query, growth threshold, drift detected | Alert only |
| **1 — Safe Auto** | ANALYZE, pool resize, cache TTL, route replica traffic | Auto + audit log |
| **2 — Human Review** | Add index, add MV, new cache layer | Staging + approval |
| **3 — ADR + DBA** | Drop index, partition change, type change | Tech lead + DBA |
| **4 — Forbidden Auto** | DROP TABLE, remove FK, disable RLS, modify CRITICAL data | Never automated |

**Same technical action, different tier by data criticality:**

| Action | LOW table | CRITICAL (grades) |
|--------|-----------|-------------------|
| Add index | Tier 2 | Tier 3 |
| Partition change | Tier 3 | Tier 3 + extended integrity gate |

See [SIS-DOMAIN-KNOWLEDGE-BASE.md](./SIS-DOMAIN-KNOWLEDGE-BASE.md).

---

## Data Integrity Gate (Tier 2+)

Every schema optimization MUST pass before production:

```text
Pre-deploy (staging)
  ├── FK violations = 0
  ├── Orphan records = 0
  ├── Row count delta within expected bounds
  ├── Sample checksum / hash on CRITICAL tables
  └── Grade / enrollment / attendance record counts unchanged

Post-deploy (production, 24–72h)
  ├── Same checks repeated
  ├── Audit trail intact
  ├── No unexpected NULL spikes on NOT NULL columns
  └── Application smoke tests on CRITICAL flows
```

**Fail any check → rollback immediately** — performance gain is worthless.

---

## Rollback Verification (Not Execution Alone)

```text
Regression detected
     ↓
Execute rollback migration
     ↓
Schema verification     ← structure matches pre-change
     ↓
Performance verification ← P95 restored to baseline
     ↓
Data integrity verification ← integrity gate re-run
     ↓
Rollback CONFIRMED (log outcome: rolled_back)
     ↓
Optimization event updated → feeds anti-pattern learning
```

Rollback is **not complete** until all four verification steps pass.

---

## Explainability Package (Mandatory Output)

Every Expert recommendation MUST include:

| Field | Question answered |
|-------|-------------------|
| **WHY** | Root cause diagnosis |
| **WHAT** | Proposed action |
| **EVIDENCE** | Metrics, EXPLAIN, pg_stat |
| **ALTERNATIVES** | Other options considered |
| **RISK** | Tier + data criticality |
| **EXPECTED RESULT** | P95, write overhead, storage |
| **ROLLBACK** | Exact rollback steps |
| **CONFIDENCE** | Score + context similarity |
| **VERSION** | rule_id, rule_version, knowledge_version |

Example template:

```yaml
recommendation:
  what: "Add partial index on students WHERE status = 1"
  why: "Student search filters active students 18K/day; Seq Scan on 2.1M rows"
  evidence:
    p95_ms: 820
    query_frequency_per_day: 18000
    explain: "Seq Scan cost=45000"
  alternatives:
    - option: "Composite index (status, school_id)"
      rejected_because: "Partial index smaller; status filter highly selective"
  risk_tier: 2
  data_criticality: HIGH
  expected:
    p95_ms: 350
    write_overhead_pct: 3
  rollback: "DROP INDEX CONCURRENTLY ix_students_active ON students.students"
  confidence: 0.81
  context_similarity: 0.88
  rule_id: IDX-007
  rule_version: 3
  knowledge_version: KB-2026.09
```

---

## Rule & Knowledge Versioning

Nothing is permanent. Every rule and pattern is versioned:

```text
IDX-007 v1 → v2 → v3
LP-001 v1 → v2
KB-2026.08 → KB-2026.09
PB-2026.09 → PB-2026.12
```

Audit trail must answer: **Which rule version produced this recommendation?**

```yaml
audit:
  recommendation_id: REC-2026-0912-004
  rule_id: IDX-007
  rule_version: 3
  knowledge_version: KB-2026.09
  pattern_id: LP-001
  pattern_version: 2
  pattern_status: active          # candidate | validated | active | deprecated
  approved_by: "DBA name"
  executed_at: "2026-09-12T14:00:00Z"
```

---

## Pattern Lifecycle (Never Direct KB Update)

```text
Candidate
   ↓  DBA review + benchmark + historical validation
Validated
   ↓  30+ days production success OR explicit promotion
Active
   ↓  success rate < 40% OR drift detected
Deprecated
```

```yaml
pattern:
  id: LP-001
  version: 2
  status: validated               # not active until promoted
  promoted_by:
  promoted_at:
```

---

## Forbidden Intelligence Actions

| Action | Always |
|--------|--------|
| AI/LLM → DROP INDEX | Tier 3 + human + 90-day idx_scan=0 proof |
| Auto schema change from confidence alone | Forbidden for Tier 2+ |
| Skip staging benchmark | Forbidden |
| Skip integrity gate on CRITICAL tables | Forbidden |
| Skip rollback plan | Forbidden |
| Redis as source of truth | ADR-004 violation |

---

## Related

- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
- [DATABASE-OPTIMIZATION-CONTEXT.md](./DATABASE-OPTIMIZATION-CONTEXT.md)
- [DATABASE-SIMULATION-POLICY.md](./DATABASE-SIMULATION-POLICY.md)
- [SIS-DOMAIN-KNOWLEDGE-BASE.md](./SIS-DOMAIN-KNOWLEDGE-BASE.md)
