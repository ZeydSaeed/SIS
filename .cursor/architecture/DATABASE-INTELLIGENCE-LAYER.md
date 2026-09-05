# Database Intelligence Layer

> **Version:** SIS v3.0 — Adaptive Expert Database Architecture  
> **Goal:** Adaptive Expert Database Architecture with Controlled Self-Learning and Self-Healing  
> **Non-negotiable:** PostgreSQL = source of truth. Intelligence layer = analysis + recommendation only.

---

## What This Is — And What It Is Not

| Term | What the system does | SIS status |
|------|----------------------|------------|
| **Traditional** | Executes fixed rules | v2.0 baseline |
| **Smart / Adaptive** | Monitors, analyzes, proposes based on measurements | **v2.2 — achieved** |
| **Expert System** | Knowledge Base + inference rules → diagnosis + recommendation | **v3.0 — documented** |
| **Self-Learning** | Learns from historical optimization outcomes | **v3.0 — controlled design** |
| **Self-Healing** | Detects known failures → applies safe automated fixes | **v3.0 — limited scope** |
| **Autonomous** | Detect → analyze → decide → execute → verify → rollback | **Not target for SIS** |

**Honest assessment:** Current docs provide an **Adaptive Foundation**. This layer adds the path to Expert-Assisted and Controlled Self-Learning — not full autonomy.

```text
DO NOT OPTIMIZE FOR A NUMBER.     OPTIMIZE FOR A MEASURED WORKLOAD.
AI DOES NOT OWN THE DATABASE.     PostgreSQL + FK + RLS + Audit DO.
```

---

## Intelligence Architecture

```text
                    SIS Application
                           │
              ┌────────────┴────────────┐
              │                         │
         Application               PostgreSQL
         (Laravel)              (Source of Truth)
              │                         │
              └────────────┬────────────┘
                           │
                    Observability
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
           Metrics       Logs      Query Plans
         (Prometheus)  (correlation) (pg_stat, EXPLAIN)
              │            │            │
              └────────────┼────────────┘
                           ▼
                 Intelligence Layer
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
         Rule Engine   Knowledge Base   Learning Engine
         (policies)   (heuristics)     (historical patterns)
              │            │            │
              └────────────┼────────────┘
                           ▼
                   Expert Diagnosis
                           │
                           ▼
                  Recommendation
                           │
                           ▼
                   Risk Assessment
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
         Auto-Safe Actions          Human Gate
    (pool adjust, route traffic)   (schema changes)
              │                         │
              └────────────┬────────────┘
                           ▼
                      Execution
                           │
                           ▼
                      Validation
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
           Success                   Failure
              │                         │
              ▼                         ▼
        Learn / Record              Rollback
              │
              └──────────→ Knowledge Base update
```

---

## Adaptive vs Expert vs Self-Learning

### Adaptive (v2.2 — Current)

```text
SIS Database → Monitoring → Data Growth + Query Behavior
     → Performance Analysis → Optimization Rules → Recommendation → Human Approval
```

Rules are predefined. System responds to measurements but does not infer beyond documented policies.

### Expert System (v3.0 — This Layer)

```text
Database Metrics → Knowledge Base → Inference Engine → Diagnosis
     → Recommendation → Risk Assessment → Approval / Safe Auto-Action
```

**Example inference:**

```text
Input:
  attendance.records = 800M rows
  time-range queries P95 = 4.2s (target 500ms)
  partition pruning confirmed in 12% of queries (target > 80%)

Diagnosis (not just "table is big"):
  "Current partition key (academic_year_id) may not match dominant
   query pattern (school_id + date range). Evaluate sub-partitioning
   or composite partition key. Historical success rate for similar
   change: 78% (see optimization-learning log #47)."

Recommendation:
  ADR required · simulate on staging · benchmark before production
```

### Self-Learning (v3.0 — Controlled)

```text
Historical Data → Past Decisions → Results → Performance Changes
     → Pattern Extraction → Improved Recommendation → Validation → Knowledge Update
```

**Example learned pattern:**

```text
IF attendance volume ↑ AND academic_year queries ↑ AND P95 > target AND pruning < 50%
THEN partition strategy review (confidence: high — 82/100 similar events succeeded)
```

Learning improves **recommendation quality** — it does not bypass approval gates.

---

## Safety Gate — Mandatory for All Schema Changes

**Never allow:**

```text
AI → "This index is unnecessary" → DROP INDEX
```

**Always require:**

```text
Expert Engine
     ↓
Recommendation + evidence
     ↓
Risk Assessment (see risk tiers below)
     ↓
EXPLAIN / Simulation on staging
     ↓
Benchmark (before/after P95)
     ↓
Human Approval (Tier 2+)
     ↓
Production migration
     ↓
Monitoring window (24–72h)
     ↓
Rollback if regression detected
```

---

## Automation Risk Tiers

| Tier | Examples | Automation |
|------|----------|------------|
| **0 — Observe** | Slow query detected, table growth threshold | Alert only |
| **1 — Safe Auto** | ANALYZE after bulk load, connection pool resize, cache TTL adjust | Auto with audit log |
| **2 — Review Required** | Add index, add partial index, new MV | Human approval + staging test |
| **3 — ADR Required** | Drop index, partition change, column type change | Tech lead + DBA |
| **4 — Forbidden Auto** | DROP TABLE, DROP COLUMN, remove FK, disable RLS | Never automated |

See [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md) for event logging.

---

## Phased Implementation Roadmap

Do **not** start with ML. Build on existing governance (ADRs, capacity planning, indexing, zero-downtime, DR).

| Phase | Name | Status | Deliverable |
|-------|------|--------|-------------|
| **1** | Governance | ✅ Done | DATABASE-GOVERNANCE, ADRs, blueprint |
| **2** | Monitoring + Metrics | ⏳ Design | Prometheus, pg_stat, query logging |
| **3** | Rule Engine | ⏳ Design | Threshold alerts → recommendations |
| **4** | Expert Knowledge Base | ✅ Documented | DATABASE-KNOWLEDGE-BASE.md |
| **5** | Recommendation Engine | ⏳ Design | Diagnosis templates + risk scoring |
| **6** | Historical Learning | ⏳ Design | Optimization event log + patterns |
| **7** | Controlled Automation | ⏳ Design | Tier 0–1 auto-actions only |
| **8** | Self-Healing | ⏳ Design | SELF-HEALING-RUNBOOK.md |

**Production Proven** requires Phases 2–3 operational with measured results.

---

## Document Stack (v3.0)

```text
DATABASE GOVERNANCE
├── DATABASE-GOVERNANCE.md              — change control
├── DATABASE-ADAPTIVE-GOVERNANCE.md     — adaptive policies
├── DATABASE-INTELLIGENCE-LAYER.md      — this file — expert + learning design
├── DATABASE-KNOWLEDGE-BASE.md          — inference rules & heuristics
├── DATABASE-OPTIMIZATION-LEARNING.md   — historical learning log
├── SELF-HEALING-RUNBOOK.md             — safe automated responses
├── schema-change-impact.md
├── PERFORMANCE-BUDGET.md
├── capacity-planning.md
└── adr/ (001–010)
```

---

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [DATABASE-KNOWLEDGE-BASE.md](./DATABASE-KNOWLEDGE-BASE.md)
- [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md)
- [SELF-HEALING-RUNBOOK.md](./SELF-HEALING-RUNBOOK.md)
- [adr/ADR-010-intelligence-layer.md](./adr/ADR-010-intelligence-layer.md)
