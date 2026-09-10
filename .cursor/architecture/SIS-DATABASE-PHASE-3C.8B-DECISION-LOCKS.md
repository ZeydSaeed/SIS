# SIS DATABASE — PHASE 3C.8B  
# DECISION LOCKS (DL-017 … DL-022)

**Document type:** DECISION LOCK RECORD  
**Date:** 2026-09-10  
**Supersedes for lock status:** Phase 3C.7/3C.8 “PROPOSED” / 3C.8A “UNDECIDED” recommendations  

```text
ACCEPTED locks below are architectural.
They do NOT authorize implementation.
```

---

## DL-017 — ACCEPTED

```text
Completion ≠ Graduation.
Both are distinct versioned derived outcomes.
```

| Check | Result |
|-------|--------|
| Aligns HD-19 | YES |
| Aligns HD-32 | YES |
| Conflicts DL-001…016 | NO |
| Conflicts GC-INV-006/007 | NO |

---

## DL-018 — ACCEPTED

```text
Graduation/Completion is NOT SSOT for:
grades, results, GPA, ranking, transcript source data.
```

| Check | Result |
|-------|--------|
| Aligns DL-001, DL-005, DL-006 | YES |
| Aligns HD-20 evidence model | YES |
| Conflicts GC-INV-001…005 | NO |

---

## DL-019 — ACCEPTED

```text
Official outcomes are immutable in place.
Correction occurs through supersession/version lineage.
```

| Check | Result |
|-------|--------|
| Aligns HD-35, HD-36 | YES |
| Aligns DL-007, DL-015 | YES |
| Conflicts GC-INV-008/009/022 | NO |

---

## DL-020 — ACCEPTED

```text
Evidence-based evaluation.
Missing evidence ≠ satisfied requirement.
```

| Check | Result |
|-------|--------|
| Aligns HD-20 framework | YES |
| Policy values invented? | NO |
| Conflicts GC-INV-017…019 | NO |

---

## DL-021 — ACCEPTED

```text
GPA, Ranking, Promotion, Academic-year closure
must not become hidden or automatic graduation dependencies.
They participate only when an explicit approved policy says so.
```

| Check | Result |
|-------|--------|
| Aligns HD-22 (no default GPA gate) | YES |
| Aligns HD-40/41 intent (still open as HDs; arch constraint locked) | YES |
| Hidden GPA created? | NO |

---

## DL-022 — ACCEPTED

```text
StudentStatus::Graduated is a projection.
It is not the graduation SSOT.
```

| Check | Result |
|-------|--------|
| Aligns HD-19, HD-32 | YES |
| Aligns GC-INV-036 | YES |
| StudentStatus as SSOT? | FORBIDDEN |

---

## Prior Locks

| Set | Status |
|-----|--------|
| DL-001…DL-016 | **Preserved** — no weakening |
| DL-017…DL-022 | **ACCEPTED** (this phase) |

---

## Non-Locks (remain open)

Institutional policy *content*, role matrices, thresholds, honors, numbering, publication rules — **not** locked as values.
