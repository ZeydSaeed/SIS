# Database Intelligence Layer

> **Version:** SIS v3.1 — Adaptive Expert Database Architecture (Design Complete)  
> **Goal:** Adaptive Expert Database Architecture with Controlled Self-Learning and Self-Healing  
> **Maturity:** Design 93/100 · Operational implementation Phases 2–6 ⏳  
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

## Document Stack (v3.1)

```text
DATABASE GOVERNANCE
├── DATABASE-GOVERNANCE.md
├── DATABASE-ADAPTIVE-GOVERNANCE.md
├── DATABASE-INTELLIGENCE-LAYER.md      — this file
├── DATABASE-INTELLIGENCE-SAFETY.md     — tiers, integrity gate, explainability, versioning
├── DATABASE-KNOWLEDGE-BASE.md          — PostgreSQL inference rules
├── SIS-DOMAIN-KNOWLEDGE-BASE.md        — business criticality + domain rules
├── DATABASE-OPTIMIZATION-LEARNING.md   — historical learning
├── DATABASE-OPTIMIZATION-CONTEXT.md    — context fingerprint + similarity
├── DATABASE-KNOWLEDGE-DRIFT.md         — drift detection + pattern deprecation
├── DATABASE-WORKLOAD-CLASSIFICATION.md — OLTP vs dashboard vs bulk
├── DATABASE-COST-MODEL.md              — performance + ops cost
├── DATABASE-SIMULATION-POLICY.md       — what-if before production
├── SELF-HEALING-RUNBOOK.md
├── PERFORMANCE-BUDGET.md               — versioned, workload-aware
└── adr/ (001–010)
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
