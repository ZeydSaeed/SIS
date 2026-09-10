# SIS DATABASE — PHASE 3C.5  
# RANKING LIFECYCLE

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
CALCULATED          ← operational ranking snapshot
      ↓
FINALIZED           ← official ranking snapshot
      ↓
SUPERSEDED          ← prior official retained
```

### Optional markers (not academic truth)

| Marker | Meaning |
|--------|---------|
| `STALE` | Underlying GPA/Annual/grades/population changed |
| `FAILED` | Technical calculation failure |
| `INELIGIBLE` | Population/eligibility gate failed — rules **HDR** |
| `BLOCKED_MISSING_POLICY` | Scope/tie/privacy/metric policy absent |
| `CALCULATED_UNPUBLISHED` | Exists but not published (privacy/publication gate) |
| `PUBLISHED` | Publication projection active under privacy policy version |

`PUBLISHED` is a **publication state**, not a substitute for FINALIZED. A snapshot may be FINALIZED but unpublished, or published under policy — exact coupling = **HDR (HD-10)**.

---

## 2. State Semantics

| State | Official? | Content mutable? | Downstream official use |
|-------|-----------|------------------|-------------------------|
| NOT_CALCULATED | NO | N/A | NO |
| CALCULATED | NO | Replaceable by newer ops | NO (no masquerade) |
| FINALIZED | YES | NO in place | YES for consumers needing official rank |
| SUPERSEDED | Historical official | NO | Lineage / audit only |

---

## 3. Transitions

| From | To | Trigger | Notes |
|------|----|---------|-------|
| NOT_CALCULATED | CALCULATED | CalculateRanking / RebuildRanking | Authorized |
| CALCULATED | CALCULATED | Operational refresh | Eventual (DL-013) |
| CALCULATED | FINALIZED | FinalizeRanking | Elevated authority — roles **HDR** |
| FINALIZED | SUPERSEDED | Newer FINALIZED same identity | Audit + lineage |
| * | STALE / FAILED | Source change / tech error | Ops markers |
| FINALIZED / CALCULATED | PUBLISHED / UNPUBLISHED | PublishRanking / revoke publish | Privacy policy **HDR** |

### Forbidden

- In-place mutate FINALIZED  
- Hard delete FINALIZED / SUPERSEDED (DL-015)  
- Label CALCULATED as FINALIZED without finalize  
- Cross-school finalize/publish  
- Implying Annual/GPA/transcript/graduation/year-closed from Ranking FINALIZED  
- Making Ranking FINALIZED a prerequisite for Annual/GPA finalize by default (**HD-14**)  

---

## 4. Operational vs Official

### Operational Ranking

- Eventually consistent  
- Recalculated / replaced / refreshed  
- May be stale  
- Must **not** masquerade as official  

### Official Ranking

- Versioned  
- Provenance-preserving  
- Immutable in place  
- Superseded, never overwritten  
- Reproducible under pinned policies  

Not every CALCULATED ranking becomes official.

---

## 5. Dual Current Pointers (PROPOSED)

- ≤1 operational current per Ranking Identity  
- ≤1 official current (FINALIZED not superseded) per Ranking Identity  
- Publication pointer(s) separate from official current (HD-10)  

---

## 6. Separation from Other Closures

| Concept | Distinct from Ranking FINALIZED? |
|---------|----------------------------------|
| Term FINALIZED | YES |
| Annual FINALIZED | YES |
| GPA FINALIZED | YES |
| Transcript issued | YES |
| Graduation completed | YES |
| Academic year closed | YES |
| Ranking PUBLISHED | YES (related but separate) |

---

## 7. Versioning Fields

| Field | Role |
|-------|------|
| `ranking_version` | Monotonic |
| Ops / official current flags | Dual currents |
| `supersedes` / `superseded_by` | Lineage |
| `supersession_reason` | Correction, population change, policy change, rebuild |
| `calculated_at` | DL-008 |
| `finalized_at` / `superseded_at` / `published_at` | Audit |
| Actor / correlation / causation | Provenance |
| Source / policy / calculation / tie / privacy fingerprints | Pins |

---

## 8. Correction Path

```text
Official Ranking V1 FINALIZED
  → grade / Annual / GPA change
  → outbox
  → impact detection
  → Ranking V2 CALCULATED
  → optional Finalize → V2 FINALIZED
  → V1 SUPERSEDED
  → republication per HD-10 (HDR)
```

No overwrite of V1.

---

## 9. Consistency Model (DL-013)

| Kind | Consistency |
|------|-------------|
| Grades | Strong |
| Operational ranking | Eventual / snapshot refresh |
| Official ranking | Strong finalize + immutable/superseding |
| Publication views | Eventual relative to official + privacy policy |
