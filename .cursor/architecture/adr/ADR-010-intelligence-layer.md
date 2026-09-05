# ADR-010: Database Intelligence Layer

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Add a **Database Intelligence Layer** above Adaptive Governance — combining Rule Engine, Expert Knowledge Base, and Controlled Historical Learning — with **Human Gate** for all Tier 2+ schema changes and **Tier 1 only** for safe operational auto-actions (Self-Healing).

Target architecture name:

```text
Adaptive Expert Database Architecture
with Controlled Self-Learning and Self-Healing
```

**PostgreSQL remains the sole source of truth.** Intelligence layer provides analysis and recommendations — never replaces FK, RLS, transactions, or audit.

## Why

v2.2 Adaptive Governance responds to measurements with predefined policies. For 10–20 year operation, the system needs:

- **Expert inference** beyond "table is big" (diagnosis with context)
- **Historical learning** from optimization outcomes (improve recommendations)
- **Self-Healing** for known operational failures (replica lag, connection spikes)
- **Explicit safety tiers** preventing autonomous schema modification

We explicitly **reject** full autonomy (detect → execute schema change without human) for an academic SIS.

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| Full ML auto-optimization | Risk to grades, enrollment, audit integrity |
| No intelligence layer — adaptive only | Misses expert diagnosis and learning loop |
| Immediate ML from day one | No baseline metrics or event history to learn from |
| AI as source of truth | Violates PostgreSQL authority + ADR-004 |

## Consequences

- New docs: DATABASE-INTELLIGENCE-LAYER, DATABASE-KNOWLEDGE-BASE, DATABASE-OPTIMIZATION-LEARNING, SELF-HEALING-RUNBOOK
- Phased roadmap Phases 1–8 (ML deferred to Phase 7+ if ever)
- Automation limited to Tier 0–1 without human approval
- Optimization events must be logged for learning loop
- Version bump: SIS architecture v3.0

## Implementation Phases

| Phase | Status |
|-------|--------|
| 1 Governance | ✅ |
| 2 Monitoring | ⏳ |
| 3 Rule Engine | ⏳ |
| 4 Knowledge Base | ✅ Documented |
| 5 Recommendation Engine | ⏳ |
| 6 Historical Learning | ⏳ |
| 7 Controlled Automation | ⏳ |
| 8 Self-Healing | ⏳ Documented |

## Review Conditions

Revisit when:

- Tier 1 auto-action causes production incident
- Optimization event log reaches 100 entries (evaluate ML need)
- Regulatory requirement changes automation boundaries

## Related

- [DATABASE-INTELLIGENCE-LAYER.md](../DATABASE-INTELLIGENCE-LAYER.md)
- [ADR-009-adaptive-governance.md](./ADR-009-adaptive-governance.md)
