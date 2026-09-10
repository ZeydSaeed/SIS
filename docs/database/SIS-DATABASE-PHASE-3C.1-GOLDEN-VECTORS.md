# SIS DATABASE — PHASE 3C.1  
# GOLDEN VECTOR CATALOG

**Document type:** DESIGN ONLY — Test Vector Specification  
**Date:** 2026-09-10  
**Status:** VECTORS CATALOGUED — most expected values policy-blocked  

```text
NO TEST IMPLEMENTATION · NO FIXTURES SEEDED · NO DATABASE WRITES
```

---

## 1. Vector Schema

Each vector includes:

| Field | Purpose |
|-------|---------|
| `vector_id` | Stable ID |
| `purpose` | What is proven |
| `authoritative_inputs` | Grades + structure (logical) |
| `policy_version` | When known; else `UNRESOLVED` |
| `calculation_version` | When known; else `UNRESOLVED` |
| `expected_eligibility` | ELIGIBLE / INELIGIBLE / INCOMPLETE / HDR |
| `expected_output` | Semantic Results — often HDR |
| `expected_metadata` | Policy/calc pins present |
| `expected_fingerprint_semantics` | Same inputs → same fingerprint class |
| `edge_case_class` | Category |
| `human_approval_requirement` | Which HDs |

Legend: **HDR** = `EXPECTED VALUE = HUMAN DECISION REQUIRED`

---

## 2. Vector Catalog

### GV-01 — Normal result

| Field | Value |
|-------|-------|
| Purpose | Happy-path subject-term aggregation |
| Inputs | One current non-absent grade; valid enrollment/seat |
| Policy / calc | UNRESOLVED / UNRESOLVED |
| Expected eligibility | HDR (HD-04, HD-18) |
| Expected output | HDR |
| Fingerprint | Deterministic once policies fixed |
| Edge | normal |
| HD | HD-03, HD-04, HD-18 |

**Status:** policy-dependent

---

### GV-02 — Absent

| Field | Value |
|-------|-------|
| Purpose | `is_absent=true`, score NULL preserved |
| Inputs | Current absent grade |
| Expected eligibility | HDR |
| Expected output | HDR (exclude/zero/fail/incomplete — HD-05) |
| Edge | absent |
| HD | HD-05, HD-04 |

**Status:** policy-dependent (absence mechanics LIVE; calc treatment HDR)

---

### GV-03 — Missing grade

| Field | Value |
|-------|-------|
| Purpose | Required assessment without grade row |
| Expected eligibility | HDR (HD-18) |
| Expected output | HDR (block / incomplete — HD-07) |
| Edge | missing grade |
| HD | HD-07, HD-18 |

**Status:** blocked on HD

---

### GV-04 — Multiple assessments

| Field | Value |
|-------|-------|
| Purpose | Midterm+final weights combine |
| Inputs | Multiple current grades, distinct exam types |
| Expected output | HDR (HD-03) |
| Edge | multiple assessments |
| HD | HD-03, Rounding |

**Status:** policy-dependent

---

### GV-05 — Weight mismatch

| Field | Value |
|-------|-------|
| Purpose | Weights sum ≠ configured total |
| Expected output | HDR — reject vs normalize (HD-03) |
| Edge | weight mismatch |
| HD | HD-03 |

**Status:** blocked on HD-03

---

### GV-06 — Retake

| Field | Value |
|-------|-------|
| Purpose | Two attempts; canonical contributor selected |
| Inputs | Two grade rows (mechanism: new enrollments) |
| Expected output | HDR (latest/highest/first/all — HD-06) |
| Traceability | Must list chosen grade id(s) |
| Edge | retake |
| HD | HD-06 |

**Status:** blocked on HD-06

---

### GV-07 — Cancelled enrollment

| Field | Value |
|-------|-------|
| Purpose | Academic enrollment cancelled / effective_to set |
| Expected eligibility | HDR (HD-18 / HD-07) |
| Expected output | HDR |
| Edge | cancelled enrollment |
| HD | HD-07, HD-18 |

**Status:** blocked on HD

---

### GV-08 — Incomplete term

| Field | Value |
|-------|-------|
| Purpose | Partial subjects/assessments in term |
| Expected output | HDR |
| Edge | incomplete term |
| HD | HD-07, HD-18 |

**Status:** blocked on HD

---

### GV-09 — Incomplete year

| Field | Value |
|-------|-------|
| Purpose | Missing term for annual |
| Expected output | HDR (block / incomplete / partial — HD-07) |
| Edge | incomplete year |
| HD | HD-07 |

**Status:** blocked on HD-07

---

### GV-10 — Boundary score

| Field | Value |
|-------|-------|
| Purpose | Score at pass boundary / max_score |
| Inputs | score = pass_grade or max_score |
| Expected letter/pass | HDR (HD-02) |
| Edge | boundary score |
| HD | HD-02, Rounding |

**Status:** policy-dependent

---

### GV-11 — Rounding boundary

| Field | Value |
|-------|-------|
| Purpose | Value exactly on rounding edge |
| Expected output | HDR until Rounding Policy approved |
| Edge | rounding boundary |
| HD | Rounding (linked HD-01/03) |

**Status:** blocked

---

### GV-12 — Policy version change

| Field | Value |
|-------|-------|
| Purpose | Same grades under Policy v1 vs v2 |
| Expected | Different outputs possible; each fingerprint stable |
| Edge | policy version change |
| HD | DL-014; related HDs |

**Status:** fully specified *structurally*; values HDR

---

### GV-13 — Calculation version change

| Field | Value |
|-------|-------|
| Purpose | Same policy, Calculation v1 vs v2 |
| Expected | Outputs may differ; must not silent-rewrite old official rows (supersede) |
| Edge | calculation version change |
| HD | DL-008, DL-007 |

**Status:** structurally specified

---

### GV-14 — Grade correction

| Field | Value |
|-------|-------|
| Purpose | VOID+INSERT 75→82; operational results stale then recalc |
| Expected official prior version | Unchanged (DL-007) |
| Expected issued transcript | HDR (HD-11 A vs B) |
| Expected new current derived | Recalc under policies |
| Edge | grade correction |
| HD | HD-11, HD-13 |

**Status:** historical truth structurally specified; transcript HDR

---

### GV-15 — Deterministic rebuild

| Field | Value |
|-------|-------|
| Purpose | Rebuild with identical inputs/policies/calc version |
| Expected | Equivalent semantics + fingerprint class (DL-016) |
| Edge | deterministic rebuild |
| HD | Requires resolved policies for numeric equality |

**Status:** invariant fully specified; numeric golden values blocked until HDs resolved

---

## 3. Summary Table

| Vector | Fully specified | Policy-dependent | Blocked on HD |
|--------|-----------------|------------------|---------------|
| GV-01 | Structure | Yes | Partial |
| GV-02 | Structure + LIVE absent | Calc treatment | HD-05 |
| GV-03 | Structure | Yes | HD-07/18 |
| GV-04 | Structure | Yes | HD-03 |
| GV-05 | Structure | — | HD-03 |
| GV-06 | Structure | — | HD-06 |
| GV-07 | Structure | — | HD-07/18 |
| GV-08 | Structure | — | HD-07/18 |
| GV-09 | Structure | — | HD-07 |
| GV-10 | Structure | Yes | HD-02 |
| GV-11 | Structure | — | Rounding |
| GV-12 | Structure | Yes | Policy content |
| GV-13 | Structure | Yes | Engine |
| GV-14 | Structure | Transcript | HD-11 |
| GV-15 | Invariant | Numeric | All formula HDs |

**No invented expected academic scores or letter outcomes.**
