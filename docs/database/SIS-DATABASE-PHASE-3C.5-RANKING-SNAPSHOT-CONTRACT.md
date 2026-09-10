# SIS DATABASE — PHASE 3C.5  
# RANKING SNAPSHOT CONTRACT (TECHNOLOGY-NEUTRAL)

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  
**Related:** Phase 3C.1 Policy Catalog §5.9 · Phase 3C.4 GPA Calculation Contract  

```text
NO EXECUTABLE RANKING ALGORITHM · NO CODE · NO DDL
NO INVENTED SCOPE / TIE / PRIVACY / METRIC SCALE
```

---

## 1. Purpose

Define a deterministic, version-pinned, rebuildable **Ranking Snapshot contract** without selecting institutional ranking rules.

```text
Ranking Identity
+ Population Definition
+ Ranking Input Dataset (pinned source metric versions)
+ Eligibility Outcomes
+ Policy Versions (scope/membership, tie, privacy, metric-related)
+ Calculation Version
+ Rounding Policy (if metric comparison requires it)
            ↓
   Deterministic Ranking Function  R(...)   ← R = HUMAN DECISION REQUIRED
            ↓
   Ranking Snapshot + Metadata + Fingerprints + Provenance
```

---

## 2. Separation of Concerns

| Layer | Role |
|-------|------|
| Grades | SSOT |
| Annual / Term / GPA | Upstream derived inputs |
| Ranking Input Dataset | Explicit considered population + metrics |
| Ranking Snapshot | Non-SSOT ordering projection (DL-005) |
| Publication | Separate visibility projection (HD-10) |
| Outbox | Propagation trigger only (DL-009) |

---

## 3. Inputs

### 3.1 Ranking Identity inputs

- school_id  
- ranking_scope (+ typed scope_anchor_type / scope_anchor_id)  
- ranking_period_type / ranking_period_id  
- population_definition_id (+ version)  

### 3.2 Ranking Input Dataset

Per participant/item:

| Input | Notes |
|-------|-------|
| Participant identity | enrollment preferred |
| Source metric identity | GPA version or approved alternate — **selection HDR** |
| Source metric value | As produced by pinned source — no recompute inside ranking |
| Scope membership | In/out |
| Eligibility layer outcomes | Separated |
| Inclusion / exclusion + reason | Deterministic |
| Metric / policy / calc metadata | Pins |

### 3.3 Policy inputs (unresolved values)

| Policy | HD |
|--------|-----|
| Scope / population | HD-08 |
| Tie policy | HD-09 |
| Privacy / publication | HD-10 |
| Ranking vs Annual finalize coupling | HD-14 |
| Metric deps (if GPA-based) | HD-01, HD-02, HD-15, Rounding |
| Eligibility deps | HD-04…07, HD-18 |

### 3.4 Engine input

`calculation_version` required for official snapshots.

---

## 4. Preconditions

| Precondition | Failure |
|--------------|---------|
| SchoolContext unambiguous | Security fail closed |
| Required policy versions published | Blocking — no invent / no silent fallback |
| Calculation version known | Blocking |
| Source metric versions available and school-consistent | Blocking |
| Population definition explicit | Blocking |
| Typed scope anchors resolvable | Blocking (scope confusion) |
| No write-back to grades/GPA/Annual | Hard forbid |

---

## 5. Ranking Function Placeholder

```text
HUMAN DECISION REQUIRED (HD-08 / HD-09 + metric policy)

R = approved_function(
  included_participants[],
  source_metrics[],
  tie_policy_version,
  calculation_version,
  ordering_determinism_rules
)
```

**Not specified:** competition/dense/ordinal/average ranks; GPA/percentage scales; top_n cutoffs; percentile formulas.

---

## 6. Outputs

### 6.1 Snapshot payload

| Output | Notes |
|--------|-------|
| Per included participant: rank_position, tie_group, metric ref | Semantics **HDR** |
| Optional percentile/band slots | **HDR** — not invented |
| Excluded participant list + reasons | Required for audit |
| Population counts | Considered / included / excluded |

### 6.2 Official metadata (required)

| Field | Lock |
|-------|------|
| Policy version refs (incl. tie, privacy as used) | DL-014 |
| `calculation_version` | DL-008 |
| `calculated_at` | DL-008 |
| Source / policy / calculation fingerprints | DL-008 / DL-016 |
| Source GPA/result version ids | Contract |
| Actor / correlation / causation | Audit |
| `ranking_version` / supersession | DL-007 |

### 6.3 Publication output (separate)

Publication status + privacy_policy_version + audience — **HD-10 HDR**. Calculation success ≠ publication approval.

---

## 7. Fingerprints (logical)

### Source fingerprint
Participant set + source metric version ids + metric values (canonical form) + inclusion/exclusion + eligibility outcomes + population definition version + scope/period identity.

### Policy fingerprint
All policy version ids used (scope/membership, tie, privacy, metric-related, rounding if any).

### Calculation fingerprint
`calculation_version` + deterministic ordering rules identity + engine-relevant parameters (once approved).

**Hash algorithm:** FUTURE IMPLEMENTATION REQUIREMENT.

---

## 8. Ordering Determinism

Until tie policy is approved, official ranking must **refuse** non-deterministic ordering (e.g. unstable sorts, wall-clock, random).  

When approved, tie policy version pins how equals are ordered/grouped. Secondary unstable keys forbidden unless explicitly part of approved policy.

---

## 9. Deterministic Equivalence (DL-016)

Identical Ranking Identity + population + input dataset + eligibility + policy/calc/tie/rounding pins ⇒ logically equivalent snapshot (participant sets, ranks/tie groups under policy, exclusions).

Mismatch classes: source drift · policy drift · calculation drift · ordering non-determinism · implementation defect.

---

## 10. Semantic Equivalence for Idempotent Finalize

If fingerprints match an existing official current and intent is identical finalize ⇒ **no uncontrolled new official current** (combine with concurrency controls — fingerprint alone insufficient).

---

## 11. Non-goals

- Inventing scopes, ties, privacy  
- Recalculating GPA inside ranking  
- Transcript fields  
- Executable SQL window functions as policy  
- Adopting blueprint `rank_in_*` as SSOT  
