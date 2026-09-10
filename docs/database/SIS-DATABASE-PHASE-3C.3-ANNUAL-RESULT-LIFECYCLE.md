# SIS DATABASE — PHASE 3C.3  
# ANNUAL RESULT LIFECYCLE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

## 1. Lifecycle States (ARCHITECTURAL RECOMMENDATION)

```text
NOT_CALCULATED
      ↓
CALCULATED          ← operational year-level projection
      ↓
FINALIZED           ← official Annual Result version
      ↓
SUPERSEDED          ← prior official retained
```

Optional markers (not separate academic truth):

| Marker | Meaning |
|--------|---------|
| `STALE` | Underlying grades/structure/policy inputs changed |
| `FAILED` | Technical calculation failure |
| `INELIGIBLE` | Eligibility gate failed (HD-18) — rule values **HDR** |

`INCOMPLETE` as an official annual academic outcome:

```text
HUMAN DECISION REQUIRED (HD-07 / HD-18)
```

---

## 2. State Semantics

| State | Official? | Content mutable? | Downstream official use |
|-------|-----------|------------------|-------------------------|
| NOT_CALCULATED | NO | N/A | NO |
| CALCULATED | NO | Replaceable by newer ops calc | NO (no masquerade) |
| FINALIZED | YES | NO in place | YES for consumers that require official annual |
| SUPERSEDED | Historical official | NO | Historical / lineage only |

---

## 3. Transitions

| From | To | Trigger | Notes |
|------|----|---------|-------|
| NOT_CALCULATED | CALCULATED | Calculate / Rebuild | System / authorized |
| CALCULATED | CALCULATED | Recalc ops | Eventual consistency |
| CALCULATED | FINALIZED | FinalizeAnnualResult | Elevated authority — roles **HDR** |
| FINALIZED | SUPERSEDED | Newer FINALIZED same business identity | Audit required |
| * | STALE/FAILED | Grade change / tech error | Ops markers |

### Forbidden

- In-place mutate FINALIZED  
- Hard delete official/superseded  
- Label CALCULATED as FINALIZED without finalize  
- Cross-school finalize  
- Implying GPA/ranking/transcript/year-closed from FINALIZED alone  

---

## 4. Separation from Other Closures

| Concept | Distinct from Annual FINALIZED? |
|---------|----------------------------------|
| Term FINALIZED | YES |
| Term / year calendar closed (HD-17) | YES — **HDR** linkage |
| GPA approved | YES |
| Ranking completed | YES (HD-14 linkage **HDR**) |
| Transcript issued | YES |
| Academic-year closure | YES |

---

## 5. Dual Current Pointers

**PROPOSED:**

- ≤1 operational current per business identity  
- ≤1 official current (FINALIZED not superseded) per business identity  
- May diverge until finalize  

---

## 6. Grade Correction Path

```text
Official Annual v1 FINALIZED
  → grade VOID+INSERT
  → outbox trigger
  → ops Annual STALE → CALCULATED v2
  → Finalize → Official v2 FINALIZED; v1 SUPERSEDED
```

No overwrite of v1. Transcript issued artifacts follow HD-11 (**UNRESOLVED**).

---

## 7. Versioning Metadata (conceptual)

business identity · annual_result_version · is_operational_current · is_official_current · calculation_version · policy version ids · source_fingerprint · calculated_at · finalized_at · superseded_at · predecessor/successor · reason · actor · correlation_id · causation_id (where appropriate)

---

## 8. What Triggers New Versions

| Event | Typical effect |
|-------|----------------|
| Grade enter/correct/void/finalize | Ops stale/recalc; official unchanged until finalize |
| Policy publish (new effective) | Does not rewrite old official; new calc uses new pin |
| Calculation version change | New versions; old official retained |
| Structure/eligibility change | Recalc; official via supersession if finalized again |
| Rebuild | New version or idempotent no-op if fingerprint equal |
