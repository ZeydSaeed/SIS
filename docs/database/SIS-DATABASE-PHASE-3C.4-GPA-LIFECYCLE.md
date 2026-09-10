# SIS DATABASE — PHASE 3C.4  
# GPA LIFECYCLE

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
CALCULATED          ← operational GPA
      ↓
FINALIZED           ← official GPA version
      ↓
SUPERSEDED          ← prior official retained
```

### Optional markers (not separate academic truth)

| Marker | Meaning |
|--------|---------|
| `STALE` | Underlying grades / Annual-Term inputs / policies changed |
| `FAILED` | Technical calculation failure |
| `INELIGIBLE` | Eligibility gate failed — rule values **HDR (HD-18)** |
| `BLOCKED_MISSING_POLICY` | Required GPA/credit/grade-point/rounding policy absent |

Incomplete / transfer-adjusted academic outcomes as official GPA semantics:

```text
HUMAN DECISION REQUIRED (HD-07 / transfer policy / HD-18)
```

---

## 2. State Semantics

| State | Official? | Content mutable? | Downstream official use |
|-------|-----------|------------------|-------------------------|
| NOT_CALCULATED | NO | N/A | NO |
| CALCULATED | NO | Replaceable by newer ops calc | NO (no masquerade) |
| FINALIZED | YES | NO in place | YES for consumers requiring official GPA |
| SUPERSEDED | Historical official | NO | Historical / lineage / audit only |

---

## 3. Transitions

| From | To | Trigger | Notes |
|------|----|---------|-------|
| NOT_CALCULATED | CALCULATED | CalculateGPA / RebuildGPA | System / authorized |
| CALCULATED | CALCULATED | Operational refresh | Eventual consistency (DL-013) |
| CALCULATED | FINALIZED | FinalizeGPA | Elevated authority — roles **HDR** |
| FINALIZED | SUPERSEDED | Newer FINALIZED same business identity | Audit + lineage required |
| * | STALE / FAILED / INELIGIBLE | Input change / tech / policy gate | Ops markers |

### Forbidden

- In-place mutate FINALIZED  
- Hard delete FINALIZED / SUPERSEDED (DL-015)  
- Label CALCULATED as FINALIZED without finalize  
- Cross-school finalize  
- Implying ranking / transcript / graduation / year-closed from GPA FINALIZED alone  

---

## 4. Operational vs Official

### Operational GPA

- Async / eventual refresh allowed  
- May be temporarily stale  
- Recalculated and replaced by newer operational version  
- Must **not** masquerade as official  

### Official GPA

- Policy pinned  
- Calculation pinned  
- Fingerprinted  
- Auditable  
- Immutable in place  
- Superseded, never overwritten  

---

## 5. Dual Current Pointers (PROPOSED)

- ≤1 operational current per GPA business identity  
- ≤1 official current (FINALIZED not superseded) per GPA business identity  
- May diverge until finalize  

---

## 6. Separation from Other Closures

| Concept | Distinct from GPA FINALIZED? |
|---------|------------------------------|
| Term FINALIZED | YES |
| Annual FINALIZED | YES |
| Ranking completed | YES (HD-14 linkage **HDR**) |
| Transcript issued | YES |
| Graduation approved | YES |
| Academic year / term calendar closed (HD-17) | YES |

---

## 7. Versioning Fields

| Field | Role |
|-------|------|
| `gpa_version` | Monotonic version number |
| Operational current flag | Points to latest CALCULATED (or equivalent) |
| Official current flag | Points to latest FINALIZED not superseded |
| `supersedes` / `superseded_by` | Lineage |
| `supersession_reason` | Correction, policy change, rebuild, … |
| `calculated_at` | DL-008 |
| `finalized_at` / `superseded_at` | Audit |
| Actor / correlation / causation | Provenance |

---

## 8. Grade Correction Path

```text
Official GPA v1 FINALIZED
  → grade VOID+INSERT (SSOT)
  → outbox trigger
  → GPA impact detection
  → reconstruct GPA Input Dataset
  → GPA v2 CALCULATED (ops)
  → optional Finalize → v2 FINALIZED
  → v1 SUPERSEDED
```

No overwrite of v1.

---

## 9. Annual Result Correction Path

```text
Annual Result v1 → v2
  → GPA impact evaluation against governed input dataset
  → if material: new GPA version; else: no new official current
```

Not every Annual change forces GPA change.

---

## 10. Consistency Model (DL-013)

| Kind | Consistency |
|------|-------------|
| Grades | Strong |
| Operational GPA | Eventual |
| Official GPA | Strong finalize + immutable/superseding |
| Ranking | Eventual (separate) |
| Issued transcript | Immutable artifact (separate) |
