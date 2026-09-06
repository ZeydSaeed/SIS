# ADR-011: Intelligence Layer v3.1 Enhancements

**Status:** Accepted  
**Date:** 2026-09-06

## Decision

Extend v3.0 Intelligence Layer with seven design documents before operational Phases 2–6:

1. Optimization Context Fingerprint
2. Rule/Knowledge Versioning + Explainability Package
3. What-If Simulation Policy
4. Optimization Cost Model
5. Knowledge Drift Management
6. Workload Classification + versioned Performance Budget
7. SIS Domain Knowledge Base (data criticality)

Separate **Design Score (93/100)** from **Operational Score (~60/100)** until Prometheus, optimization_events, and staging simulation are live.

## Why

v3.0 documented Smart/Expert/Self-Learning conceptually but lacked:

- Context similarity (20 events ≠ 20 comparable events)
- Pattern lifecycle (candidate → validated → active)
- Mandatory explainability for audit
- What-if comparison before schema changes
- Total cost ranking (not latency alone)
- Drift detection when rules become stale
- SIS business criticality affecting risk tier

## Consequences

- 7 new architecture docs under `.cursor/architecture/`
- Updated: INTELLIGENCE-LAYER, OPTIMIZATION-LEARNING, KNOWLEDGE-BASE, PERFORMANCE-BUDGET
- LLM explicitly optional — not required for Phases 2–6
- Production Smart/Expert requires operational implementation, not more docs

## Related

- [DATABASE-INTELLIGENCE-LAYER.md](../DATABASE-INTELLIGENCE-LAYER.md)
- [ADR-010-intelligence-layer.md](./ADR-010-intelligence-layer.md)
