# ADR-009: Adaptive Database Governance

**Status:** Accepted  
**Date:** 2026-09-05

## Decision

Database optimizations (indexes, partitions, cache, materialized views, infrastructure) are governed by **Adaptive Database Governance** — decisions require measurement and periodic re-evaluation, not static configuration from initial design docs.

**Core principle:**

```text
DO NOT OPTIMIZE FOR A NUMBER.
OPTIMIZE FOR A MEASURED WORKLOAD.
```

## Why

Initial design targets 45,000 students / 20 schools / 10 years. Over 10–20 years:

- Student count may rise to 250K+ or fall to 8K
- Tables may grow from 89 to 150+
- Query patterns shift
- Static "mandatory" indexes and partitions become wrong

Governance docs (v2.1) provide excellent **change control** but need a **feedback loop** for optimization decisions.

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| Static optimization list forever | Becomes wrong as system evolves |
| Rebuild docs every year manually | Unsustainable |
| Auto-indexing every FK | Write overhead, unused indexes |
| No governance — developer adds indexes ad hoc | Inconsistent, no audit trail |

## Consequences

- New doc: DATABASE-ADAPTIVE-GOVERNANCE.md
- New docs: PERFORMANCE-BUDGET, DATA-LIFECYCLE-MATRIX, capacity-planning (variable model)
- schema-change-impact.md required per schema change
- 45K is **baseline** in capacity-planning.md, not ceiling
- Quarterly review: unused indexes, MV usage, partition benefit
- Optimization claims require before/after metrics

## Review Conditions

Revisit this ADR when:

- Student count changes > 50% from baseline
- Largest table exceeds 100M rows
- DBMS migration considered
- P95 budget breached for 30 consecutive days

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](../DATABASE-ADAPTIVE-GOVERNANCE.md)
- [DATABASE-GOVERNANCE.md](../DATABASE-GOVERNANCE.md)
