# Intelligence Layer Glossary

> **Purpose:** Shared terminology for Smart / Expert / Self-Learning docs.  
> **Audience:** Developers, DBAs, ops — Arabic team reference with English acronyms.

---

## Core Concepts

| Term | Meaning |
|------|---------|
| **Smart / Adaptive** | Monitor → measure → analyze → detect → suggest (not auto-fix schema) |
| **Expert System** | Knowledge Base + inference → diagnosis + alternatives + risk |
| **Self-Learning** | Learn from optimization events — improves recommendations only |
| **Self-Healing** | Safe Tier-1 auto-fixes (pool, replica routing) — not schema changes |
| **Autonomous** | Detect → decide → execute without human — **not a SIS target** |
| **Intelligence Layer** | Analysis + recommendation — PostgreSQL remains source of truth |
| **Workload** | Nature of load: OLTP, Dashboard, Bulk, Reporting, etc. |
| **Baseline** | Reference snapshot (students, P95, table sizes) for drift comparison |
| **Drift** | Environment changed enough that old rules/patterns may be wrong |

---

## Observability (Phase 2)

| Term | Meaning |
|------|---------|
| **Prometheus** | Metrics collection (CPU, disk, connections, latency, replication lag) |
| **pg_stat** | PostgreSQL built-in statistics views |
| **pg_stat_statements** | Tracks query frequency, total time, mean time — top slow queries |
| **APM** | Application Performance Monitoring — traces Laravel → DB vs Redis vs app |
| **EXPLAIN / EXPLAIN ANALYZE** | Query plan + actual execution stats |
| **Correlation ID** | Request trace ID across app logs and audit |

---

## Performance Metrics

| Term | Meaning |
|------|---------|
| **P95** | 95% of requests faster than this value — general user experience |
| **P99** | 99% of requests faster — catches worst 1% tail latency |
| **Performance Budget** | Versioned SLO targets per operation + workload (PB-2026.09) |
| **Budget breach** | P95 exceeds target for sustained window (e.g. 7 days) |
| **SLO** | Service Level Objective — agreed latency/availability target |

**Note:** Lower P95 is better. Success = **P95 reduction ≥ 30%** (not "P95 increase").

---

## Expert System

| Term | Meaning |
|------|---------|
| **KB / Knowledge Base** | Versioned rules (KB-2026.09) — indexing, partitioning, SIS domain |
| **Signal** | Indicator triggering review (high P95, low pruning, idx_scan=0) |
| **Inference** | IF signals match THEN diagnosis + recommendation |
| **Diagnosis** | Root cause — not just symptom ("partition key mismatch") |
| **ADR** | Architecture Decision Record — documents why a structural change was made |
| **Risk Tier** | 0 Observe → 1 Safe Auto → 2 Human → 3 ADR+DBA → 4 Forbidden |
| **Explainability Package** | WHY, WHAT, EVIDENCE, ALTERNATIVES, RISK, ROLLBACK, CONFIDENCE |

---

## Self-Learning

| Term | Meaning |
|------|---------|
| **Optimization Event** | Logged Tier 2+ change with before/after metrics (OPT-2026-047) |
| **Context Fingerprint** | Environment snapshot attached to every event |
| **Context Similarity** | How comparable current situation is to historical events |
| **Pattern (LP-xxx)** | Learned rule from events — lifecycle: candidate → validated → active |
| **Confidence** | Statistical estimate of recommendation success — **≠ authorization** |
| **Confidence Calibration** | Compare predicted vs actual success rates over time |
| **Recency Weight** | Recent events weighted higher than old events |
| **Human Feedback** | Approved / rejected / modified + reason — feeds learning |

---

## Simulation & Cost

| Term | Meaning |
|------|---------|
| **What-If Simulation** | Test Index vs Partition vs MV on staging before production |
| **Cost Model** | Performance + storage + CPU + maintenance + complexity — not latency alone |
| **Blast Radius** | How many tables, APIs, reports affected by a change |
| **Dependency Graph** | Table → FK → views → MV → models → API → reports |
| **Integrity Gate** | FK=0, orphans=0, CRITICAL row counts unchanged before/after |

---

## Data Quality for Learning

| Term | Meaning |
|------|---------|
| **Garbage In → Garbage Knowledge** | Bad event data produces bad patterns — observability first |
| **False Positive** | Alert without real problem (e.g. bulk import spike) |
| **False Recommendation** | Expert suggests suboptimal fix — simulation + human gate catch |
| **Evidence-based Threshold** | Threshold derived from when performance actually degraded — not sacred static number |

---

## Status Labels

| Label | Meaning |
|-------|---------|
| **Design 93/100** | Architecture documentation quality — not production readiness |
| **Operational ~60/100** | Monitoring, events, automation not yet live |
| **Production Proven** | Phases 2–4 operational + load test + DR drill + measured events |

---

## Related

- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
- [DATABASE-OPTIMIZATION-LEARNING.md](./DATABASE-OPTIMIZATION-LEARNING.md)
