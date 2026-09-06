# Database Intelligence Layer

> **Version:** SIS v3.2 — Adaptive Expert Database Architecture (Design Complete)  
> **Design Score:** 93–94/100 · **Operational Score:** ~60/100  
> **Label:** *Designed Controlled Self-Learning Architecture* — not *Operational Self-Learning* until Phases 2–7 live.

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

**Honest assessment:** Design covers Smart + Expert + Controlled Self-Learning. **Production Smart/Expert/Self-Learning requires Phases 2–6 operational** (Prometheus, optimization_events, staging simulation).

```text
DO NOT OPTIMIZE FOR A NUMBER.     OPTIMIZE FOR A MEASURED WORKLOAD.
AI/LLM DOES NOT EXECUTE ON DB.    Policy → Risk → Approval → Execution.
```

---

## v3.1 Full Pipeline

```text
                         SIS Application
                                │
                                ▼
                      ┌──────────────────┐
                      │ Observability    │
                      │ Metrics/Logs/EXPLAIN│
                      └────────┬─────────┘
                               │
                               ▼
                      ┌──────────────────┐
                      │ Smart / Adaptive │
                      │ Detection·Drift  │
                      └────────┬─────────┘
                               │
                               ▼
                      ┌──────────────────┐
                      │ Expert Engine    │
                      │ KB + SIS Domain  │
                      │ Inference        │
                      └────────┬─────────┘
                               │
                               ▼
                      ┌──────────────────┐
                      │ Self-Learning    │
                      │ Context·Patterns │
                      │ Confidence       │
                      └────────┬─────────┘
                               │
                               ▼
                      ┌──────────────────┐
                      │ What-If Simulation│
                      │ Cost Model       │
                      └────────┬─────────┘
                               │
                               ▼
                      ┌──────────────────┐
                      │ Risk Engine      │
                      │ Tier 0–4 + Safety│
                      └────────┬─────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
        Tier 0–1 Auto                    Tier 2–4 Human Gate
              │                                 │
              └────────────────┬────────────────┘
                               ▼
                          Staging Sim
                               │
                               ▼
                    Benchmark + Integrity Gate
                               │
                               ▼
                         Production
                               │
                               ▼
                    Monitor + Rollback Verify
                               │
                               └──────→ Optimization Log → Learning
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

## Phased Implementation Roadmap (v3.2)

Do **not** start Self-Learning before reliable optimization events. Do **not** start ML/LLM before rules + statistics work.

| Phase | Name | Status | Deliverable |
|-------|------|--------|-------------|
| **1** | Governance | ✅ Done | DATABASE-GOVERNANCE, ADRs, blueprint |
| **2** | Observability | ⏳ | Prometheus, pg_stat_statements, APM, query logging |
| **3** | Workload Classification | ⏳ | Tag queries/events by workload class |
| **4** | Rule Engine | ⏳ | Evidence thresholds + alerts → recommendations |
| **5** | Expert Engine | ✅ Documented | KB + inference + explainability |
| **6** | Simulation + Cost Model | ✅ Documented | Staging what-if + cost ranking |
| **7** | Optimization Events | ⏳ | Append-only event log + integrity validation |
| **8** | Self-Learning | ⏳ | Patterns, recency, calibration, human feedback |
| **9** | Controlled Automation | ⏳ | Tier 0–1 only + audit |
| **10** | Self-Healing | ⏳ | SELF-HEALING-RUNBOOK operational |

**Production Proven** = Phases 2–4 operational + first 20 validated optimization events + load test + DR drill.

**Next step:** Phase 2 (Prometheus + pg_stat_statements) — not more documentation.

Glossary: [INTELLIGENCE-GLOSSARY.md](./INTELLIGENCE-GLOSSARY.md)

---

## Document Stack (v3.2)

```text
DATABASE GOVERNANCE
├── DATABASE-INTELLIGENCE-LAYER.md      — this file
├── INTELLIGENCE-GLOSSARY.md            — terminology reference
├── DATABASE-INTELLIGENCE-SAFETY.md
├── DATABASE-KNOWLEDGE-BASE.md
├── SIS-DOMAIN-KNOWLEDGE-BASE.md
├── DATABASE-DEPENDENCY-GRAPH.md        — blast radius + dependency discovery
├── DATABASE-OPTIMIZATION-LEARNING.md
├── DATABASE-OPTIMIZATION-CONTEXT.md
├── DATABASE-KNOWLEDGE-DRIFT.md
├── DATABASE-EVIDENCE-THRESHOLDS.md     — adaptive thresholds from measurements
├── DATABASE-WORKLOAD-CLASSIFICATION.md
├── DATABASE-COST-MODEL.md
├── DATABASE-SIMULATION-POLICY.md
├── DATABASE-ADAPTIVE-GOVERNANCE.md
├── SELF-HEALING-RUNBOOK.md
├── PERFORMANCE-BUDGET.md
└── adr/ (001–012)
```

---

## Explainability (Mandatory)

Every recommendation outputs: **WHY · WHAT · EVIDENCE · ALTERNATIVES · RISK · EXPECTED RESULT · ROLLBACK · CONFIDENCE · VERSION**

Template: [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)

---

## LLM Policy (Optional Future Layer)

**Not required for Smart/Expert/Self-Learning.** Phase 1–6 uses metrics + rules + statistics.

If LLM added later:

```text
LLM → NL explanation / pattern discovery assist
   ↓
Structured recommendation (JSON)
   ↓
Policy Engine → Risk → Approval → Execution
```

Never: `LLM → DROP INDEX` or `LLM → ALTER TABLE` directly.

---

## Related

- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [DATABASE-OPTIMIZATION-CONTEXT.md](./DATABASE-OPTIMIZATION-CONTEXT.md)
- [DATABASE-SIMULATION-POLICY.md](./DATABASE-SIMULATION-POLICY.md)
- [DATABASE-COST-MODEL.md](./DATABASE-COST-MODEL.md)
- [SIS-DOMAIN-KNOWLEDGE-BASE.md](./SIS-DOMAIN-KNOWLEDGE-BASE.md)
- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [DATABASE-KNOWLEDGE-BASE.md](./DATABASE-KNOWLEDGE-BASE.md)
- [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md)
- [SELF-HEALING-RUNBOOK.md](./SELF-HEALING-RUNBOOK.md)
- [adr/ADR-010-intelligence-layer.md](./adr/ADR-010-intelligence-layer.md)
