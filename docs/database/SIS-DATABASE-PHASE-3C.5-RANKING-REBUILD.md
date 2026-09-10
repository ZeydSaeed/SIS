# SIS DATABASE — PHASE 3C.5  
# RANKING REBUILD ARCHITECTURE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO JOBS · NO CODE · NO DDL
```

---

## 1. Canonical Rebuild Equation

```text
Ranking Identity
+ Approved Policy Version(s)   (scope/membership, tie, privacy, metric-related)
+ Approved Calculation Version
+ Source Result/GPA Versions
+ Eligibility Rules / Outcomes
+ Population Definition
+ Tie Policy
+ Rounding Policy (if required for metric comparison)
=
Ranking Snapshot
```

Mathematical function `R(...)` remains **HUMAN DECISION REQUIRED**.

---

## 2. Canonical Rebuild Flow

```text
Ranking Identity
      ↓
Resolve typed scope anchors + period + population definition
      ↓
Reconstruct Ranking Input Dataset from pinned source metric versions
      ↓
Evaluate eligibility layers (existence → contribution)
      ↓
Policy resolution (no silent fallback)
      ↓
Calculation version
      ↓
Deterministic ranking (when R approved)
      ↓
Source / policy / calculation fingerprints
      ↓
New RankingVersion
      ↓
Operational and/or official state
      ↓
Optional publication (HD-10) — separate step
```

**Does NOT require:** previous RankingVersion as academic input.  
**Previous ranking:** historical evidence / lineage only.

---

## 3. Independent Reconstructibility

Inputs must be reconstructible from:

- grades SSOT (upstream)  
- Annual / Term Result versions (as referenced)  
- GPA versions (as referenced) — without recalculating GPA inside ranking  
- academic structure / enrollment / school  
- population definition  
- policy / calculation / tie / privacy / rounding pins  

Ranking must not prevent Annual/GPA independent rebuild from their own contracts.

---

## 4. Correction Propagation

```text
Grade correction
      ↓
Outbox event (trigger only)
      ↓
Impact detection (grades → Annual/Term → GPA → Ranking)
      ↓
Affected Ranking Identity detection (school, scope, period, population)
      ↓
Affected participant detection
      ↓
Reconstruct ranking input
      ↓
Calculate new RankingVersion
      ↓
Supersede previous operational/official according to lifecycle policy
      ↓
Audit lineage
```

### Rules

- Material impact only creates new versions when needed  
- Historical official ranking never silently mutated  
- Stale source versions mid-job ⇒ re-read / fail; no partial official  
- Publication refresh follows HD-10 (HDR) separately from calc  

---

## 5. Impact Detection (conceptual)

| Change | May affect |
|--------|------------|
| Grade correction | Upstream Annual/GPA → ranking identities containing participant |
| Annual Result supersession | Rankings consuming that annual path / year scopes |
| GPA supersession | Rankings pinned to that GPA version/scope |
| Enrollment/section/class move | Population membership for HD-08 scopes |
| Policy version retirement | New rankings only; history preserved |

Exact scope membership rules = **HD-08 HDR**.

---

## 6. Determinism Requirements

- Deterministic inputs  
- Deterministic ordering under pinned tie policy  
- Source / policy / calculation fingerprints  
- Equivalent rebuild behavior (RK-INV-021)  

Unstable sort keys, randomness, or wall-clock ordering forbidden for official snapshots.

---

## 7. Four Control Mechanisms

| Mechanism | Rebuild role |
|-----------|--------------|
| Idempotency | Duplicate outbox/rebuild does not create unintended official duplicates |
| Concurrency control | Serialize per Ranking Identity (FUTURE IMPLEMENTATION) |
| Version conflict detection | Stale worker cannot finalize over newer official current unchecked |
| Fingerprint equivalence | Detect unchanged logical snapshot — **not** sole idempotency |

---

## 8. Modes

| Mode | Purpose |
|------|---------|
| Operational refresh | CALCULATED current |
| Official finalize | FINALIZED + supersede prior official if needed |
| Verification rebuild | Compare fingerprints/semantics; alert on drift — no silent overwrite |
| Publish / unpublish | HD-10 projection only |

---

## 9. Blockers for Official Ranking Implementation

Official ranking calculation/finalize blocked without approved:

- HD-08 (scope / population)  
- HD-09 (tie policy)  
- HD-10 (at least for publication; calculation may still need privacy metadata pins)  
- Source metric readiness (often HD-01/02/15/Rounding + GPA architecture)  
- Eligibility deps as required (HD-04…07, HD-18)  

HD-14 remains unresolved for any Annual↔Ranking coupling; default rebuild independence preserved.

Until approved: architecture holds; implementation must refuse inventing defaults.
