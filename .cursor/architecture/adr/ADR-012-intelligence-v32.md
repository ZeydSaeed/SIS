# ADR-012: Intelligence Layer v3.2 Refinements

**Status:** Accepted  
**Date:** 2026-09-06

## Decision

Extend v3.1 with operational and statistical refinements before Phase 2 implementation:

1. **Confidence calibration** + multi-factor confidence (not `success × similarity` alone)
2. **Recency weighting** for historical events
3. **Data distribution** in context fingerprint (selectivity skew)
4. **Blast radius** + dependency graph before Tier 2+ changes
5. **Human feedback learning** (approved / rejected / modified + reason)
6. **Evidence-based adaptive thresholds** (replace static 10M/100M when measured)
7. **Confidence ≠ authorization** — explicit separation
8. **P95 wording fix** — success = P95 reduction ≥ 30%
9. **Implementation phases reordered** — Self-Learning after optimization events (Phases 1–10)
10. **INTELLIGENCE-GLOSSARY.md** — shared terminology

## Why

v3.1 design scored ~93/100 but review identified:
- Over-reliance on simple confidence heuristic
- Static thresholds risk becoming "sacred numbers"
- Missing blast radius and human rejection signal in learning
- Design score must not be confused with operational readiness (~60%)

## Consequences

- 3 new docs: GLOSSARY, DEPENDENCY-GRAPH, EVIDENCE-THRESHOLDS
- Major updates: OPTIMIZATION-LEARNING, INTELLIGENCE-SAFETY, INTELLIGENCE-LAYER
- No new autonomous capabilities — safety unchanged
- Next work is Phase 2 implementation, not more docs

## Related

- [DATABASE-INTELLIGENCE-LAYER.md](../DATABASE-INTELLIGENCE-LAYER.md)
- [ADR-011-intelligence-v31.md](./ADR-011-intelligence-v31.md)
