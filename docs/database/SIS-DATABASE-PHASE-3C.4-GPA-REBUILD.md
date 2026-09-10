# SIS DATABASE — PHASE 3C.4  
# GPA REBUILD ARCHITECTURE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO JOBS · NO CODE · NO DDL
```

---

## 1. Canonical Rebuild Flow

```text
GPA Input Dataset
      ↓
Policy Resolution (published/effective pins)
      ↓
Calculation Version
      ↓
Deterministic Calculation   ← F = HUMAN DECISION REQUIRED
      ↓
Fingerprint
      ↓
New GPA Version
      ↓
operational and/or official state
```

**Does NOT require:** previous GPA result as academic input.  
**Previous GPA:** historical evidence / lineage only.

---

## 2. Reconstructing the GPA Input Dataset

Before calculation, reconstruct the governed input set:

```text
grades SSOT
  + academic structure
  + enrollment context
  + referenced Annual Result version(s) (if year-scoped / policy uses them)
  + referenced Term Result version(s) (if term-scoped / policy uses them)
  + eligibility / completeness / retake / transfer policy outcomes
  + credit bindings (HD-15)
  + grade-point mappings (HD-01/02)
```

### Independence rules

| Rule | Status |
|------|--------|
| GPA rebuild from governed input dataset | **LOCKED** |
| Input dataset reconstructible without prior GPA | **LOCKED** |
| Annual Results remain independently rebuildable from grades | **LOCKED (DL-003)** |
| Term/Annual projections may optimize construction | Allowed |
| Term/Annual as sole irreplaceable academic SSOT for GPA | **FORBIDDEN** if it blocks grade-based reconstruction |

---

## 3. Impact Detection

### 3.1 Grade correction

```text
Grade correction (VOID+INSERT)
      ↓
Outbox (trigger only)
      ↓
Identify affected GPA business identities (school, enrollment, scopes, anchors)
      ↓
Reconstruct GPA Input Dataset
      ↓
Determine material impact (included set / credits / points / eligibility)
      ↓
If material → new GPA version (ops; official via finalize)
      ↓
Prior official SUPERSEDED when new official exists
```

Not every grade event necessarily changes every GPA scope.

### 3.2 Annual Result change

```text
Annual Result v1 → v2
      ↓
GPA impact evaluation against input dataset rules
      ↓
New GPA version only if material
```

Same for Term Result changes affecting term-scoped GPA.

---

## 4. Fingerprint

Logical coverage per Calculation Contract §8. Algorithm = FUTURE IMPLEMENTATION.

---

## 5. Deterministic Equivalence

Same input dataset + policies + calculation version + rounding + eligibility ⇒ equivalent GPA (GPA-INV-018).

Drift classes: source · policy · calculation · implementation · non-determinism.

---

## 6. Modes

| Mode | Purpose |
|------|---------|
| Operational refresh | CALCULATED current |
| Official finalize | FINALIZED + supersede prior official if needed |
| Verification rebuild | Compare fingerprint/semantics; alert on drift — no silent overwrite |

---

## 7. Idempotency

Identical fingerprint + same policy/calc/rounding pins + same official intent ⇒ **no uncontrolled new official current**.

Fingerprint alone is insufficient for concurrency — combine with per-identity serialization / optimistic concurrency (FUTURE IMPLEMENTATION).

---

## 8. Concurrency

Serialize per GPA business identity. Concurrent finalize → one winner; loser conflict/idempotent. Stale workers re-read grades and governed projections before write.

---

## 9. Official recalc without mutation

Official GPA recalculation always yields a **new version**. Never overwrite FINALIZED content in place.

---

## 10. Blockers for official rebuild (until human decisions)

Official GPA rebuild/finalize is **blocked** without approved:

- HD-01 (formula/scale)  
- HD-02 (if grade points/letters required by HD-01)  
- HD-15 (if credit-hour GPA required)  
- Rounding (if numeric GPA produced)  
- HD-04/05/06/07/18 as required to build eligible input set  

Until then: architecture holds; implementation must refuse inventing defaults.
