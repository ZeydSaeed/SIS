# SIS DATABASE — PHASE 3C.5  
# RANKING DATA MODEL (LOGICAL)

**Document type:** LOGICAL MODEL — NOT DDL  
**Date:** 2026-09-10  

```text
LOGICAL ENTITY ≠ PHYSICAL TABLE
NO CREATE TABLE · NO INDEXES · NO RLS SQL
```

---

## 1. Principle

Ranking is a **separate versioned snapshot** (DL-005), not a column on Term/Annual Results.

```text
STALE: blueprint rank_in_section / rank_in_class on term_results / annual_results
AUTHORITATIVE DESIGN: this logical Ranking Snapshot model
```

Ownership: **Results** bounded context (DL-012).  
SSOT for scores: **grades only**. Ranking never owns academic truth.

Not every concept below must become a physical table — implementation may embed payloads where justified.

---

## 2. Logical Concepts

### 2.1 RankingScope

| Aspect | Definition |
|--------|------------|
| Purpose | Named scope type (section/class/… examples only) |
| Identity | scope_code (+ school if school-specific catalogs) |
| Official set | **HD-08 HUMAN DECISION REQUIRED** |
| Mutability | Catalog versioned with policy |

### 2.2 Ranking (business identity)

| Aspect | Definition |
|--------|------------|
| Purpose | Stable identity for a ranking run family |
| Identity (PROPOSED) | school_id + ranking_scope + scope_anchor_type + scope_anchor_id + ranking_period_type + ranking_period_id + population_definition_id |
| Grain | One identity per school-scoped ranking definition instance |
| SSOT? | **NO** |
| School isolation | school_id mandatory |

**Typed anchors required** — no bare polymorphic ID.

### 2.3 RankingVersion

| Aspect | Definition |
|--------|------------|
| Purpose | One calculated snapshot instance |
| Identity | Ranking identity + ranking_version |
| Lifecycle | CALCULATED / FINALIZED / SUPERSEDED |
| Immutable when official | YES (content) |
| Required pins | policy, calculation, tie, privacy (as used), source metric versions, fingerprints |

### 2.4 RankingPopulation

| Aspect | Definition |
|--------|------------|
| Purpose | Versioned definition of who belongs in scope |
| Identity | population_definition_id (+ version) |
| Rules | **HDR (HD-08)** — architecture stores definition, not invents membership |

### 2.5 RankingParticipant

| Aspect | Definition |
|--------|------------|
| Purpose | A candidate person/enrollment considered for a version |
| Identity | RankingVersion + enrollment_id (preferred) |
| Captures | membership, eligibility layers, inclusion/exclusion, exclusion reason |

### 2.6 RankingInputSet / RankingInputItem

| Aspect | Definition |
|--------|------------|
| Purpose | Reconstructible inputs for the version |
| Captures | source GPA/result version refs, metric value, metric version, inclusion, exclusion reason, eligibility outcomes |
| Required for official | **YES** |

### 2.7 RankingResult

| Aspect | Definition |
|--------|------------|
| Purpose | Ordered outcome for an included participant |
| Captures | rank_position, optional percentile/band slots (**HDR**), metric displayed, tie_group_id |
| Values of rank algorithm | **HDR (HD-09)** |

### 2.8 RankingTieGroup

| Aspect | Definition |
|--------|------------|
| Purpose | Group participants with equal metric under pinned tie policy |
| Captures | tie_key, tie_policy_version, members |
| Policy | **HD-09 UNRESOLVED** |

### 2.9 RankingPolicyBinding / RankingCalculationBinding

| Aspect | Definition |
|--------|------------|
| Purpose | Pin policy family versions and calculation_version on RankingVersion |
| Mutability | Fixed at finalize |

### 2.10 RankingEligibility

| Aspect | Definition |
|--------|------------|
| Purpose | Record layered eligibility outcomes separately from ranks |
| Related HDs | HD-04…07, HD-18 (+ metric deps) |

### 2.11 RankingPublication

| Aspect | Definition |
|--------|------------|
| Purpose | Publication/visibility projection separate from calculation |
| Captures | published flag, privacy_policy_version, audience grants (logical), published_at |
| Policy | **HD-10 UNRESOLVED** |

### 2.12 RankingSupersession / RankingProvenance

| Aspect | Definition |
|--------|------------|
| Purpose | Lineage + audit (actor, timestamps, correlation, causation, fingerprints) |
| Historical | Superseded retained; no hard delete |

### 2.13 External references

| Concept | Role |
|---------|------|
| GpaResultVersion | Common source metric (pinned) |
| AnnualResultVersion / TermResultVersion | Alternate/additional inputs if policy allows |
| Grade | Ultimate SSOT for upstream rebuild — not direct ranking SSOT |

---

## 3. Relationships (logical)

```text
Ranking 1──* RankingVersion
RankingVersion 1──1 RankingInputSet
RankingInputSet 1──* RankingInputItem
RankingVersion 1──* RankingParticipant
RankingParticipant 0..1 RankingResult (if included)
RankingResult *──? RankingTieGroup
RankingVersion *── RankingPolicyBinding / CalculationBinding
RankingVersion ── RankingPublication
RankingVersion ── Supersession / Provenance
```

---

## 4. Attribute Mutability

| Attribute class | Operational | Official |
|-----------------|-------------|----------|
| Rank positions / ties | Replace via new version | Immutable in place |
| Input set | Replace via new version | Fixed at finalize |
| Publication metadata | May change per HD-10 without rewriting ranks if policy allows | Must not invent silent rank mutation |
| school_id / identity keys | Immutable | Immutable |

---

## 5. Retention

Official and superseded ranking history retained for audit/reproducibility (DL-015). Exact retention duration = future policy (related to academic retention matrices — not invented here).

---

## 6. Provenance Path

```text
Participant rank
 ↓
RankingVersion
 ↓
policy + calculation + tie + privacy versions
 ↓
RankingInputSet
 ↓
source GPA/result version + metric
 ↓
upstream Annual/Term/grades (as needed for reconstruction)
 ↓
excluded participants + reasons
```
