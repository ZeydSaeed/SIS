# SIS DATABASE — PHASE 3C.1  
# POLICY CATALOG (LOGICAL CONTRACT)

**Document type:** DESIGN ONLY — Policy Catalog  
**Date:** 2026-09-10  
**Authority:** Phase 3C.0 Decision Gate · Phase 3C Results Architecture Audit · Phase 3B/3B.1  
**Status:** CATALOG DESIGNED — most academic values UNRESOLVED  

```text
NO DDL · NO MIGRATIONS · NO APPLICATION CODE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 1. Purpose

Transform Phase 3C.0 Design Locks (DL-001…DL-016) and Human Decisions (HD-01…HD-18) into **logical policy catalogs** that can later be persisted as immutable published versions (DL-014) without inventing institutional academic rules.

---

## 2. Policy Taxonomy (3C.1-A)

| # | Policy family | Answers | Related HDs |
|---|---------------|---------|-------------|
| 1 | Academic structure binding | Which year/term/curriculum/program applies | HD-17 |
| 2 | Grade-status eligibility | Which `GradeStatus` values may enter official calc | HD-04 |
| 3 | Assessment weighting | How exam-type weights combine | HD-03 |
| 4 | Result aggregation | How assessments → term → annual totals | HD-03, HD-07 |
| 5 | Missing / incomplete semantics | Absent, missing, incomplete, withdrawn | HD-05, HD-07 |
| 6 | Retake selection | Canonical attempt among multiples | HD-06 |
| 7 | GPA | Scale/formula/credits/rounding | HD-01, HD-15 |
| 8 | Letter conversion | Score → letter / grade points | HD-02 |
| 9 | Ranking | Scope, ties, privacy | HD-08…10, HD-14 |
| 10 | Transcript | Live vs issued; supersession; retention ref | HD-11, HD-12, HD-16 |
| 11 | Finalization | What may become official | HD-04, HD-17, HD-18 |
| 12 | Versioning | Policy vs calculation versions | DL-008, DL-014 |
| 13 | Eligibility / completeness | Dataset ready for official calc | HD-18 |
| 14 | Privacy | Who sees ranks/results | HD-10 |
| 15 | Retention | Artifact retention reference | HD-12 |
| 16 | Operational freshness | Staleness / SLO | HD-13 |
| 17 | Rounding | Precision / mode | Determinism (DL-016) |

**Forbidden:** collapsing unrelated families into one opaque JSON “settings” blob as the sole historical authority (DL-014).

---

## 3. Policy Identity & Versioning (3C.1-B)

### Logical identity

| Field | Meaning |
|-------|---------|
| `policy_family` | One of taxonomy families above |
| `policy_code` | Stable logical code (e.g. `GPA_PRIMARY`) |
| `policy_version` | Immutable version identity once published |
| `status` | Lifecycle state |
| `effective_from` / `effective_to` | Temporal applicability (nullable to = open-ended) |
| `published_at` | When published |
| `superseded_by` | Next version id (nullable) |
| `scope` | Applicability dimensions (see §4) |
| `content` | Family-specific contract payload (logical) |
| `audit` | created_by, published_by, reason, correlation |

### Lifecycle states

```text
DRAFT → PUBLISHED → EFFECTIVE → RETIRED
```

| State | Content mutable? | Notes |
|-------|------------------|-------|
| DRAFT | YES | Edits allowed before publish |
| PUBLISHED | NO | Content frozen; may await effective window |
| EFFECTIVE | NO | Active for calculations in window |
| RETIRED | NO | Historical; superseded or expired |

**Correction rule:** never edit PUBLISHED/EFFECTIVE content; create **new** `policy_version` (DL-014).

### Calculation version (independent)

| Concept | Purpose |
|---------|---------|
| `policy_version` | Academic/business rules |
| `calculation_version` | Algorithm/build of the calculation engine |

A code change must not masquerade as a policy change (DL-008).

---

## 4. Scope Dimensions

| Dimension | Typical use | Required? |
|-----------|-------------|-----------|
| Institution / system | Global defaults | Optional |
| School | School-specific override | Often required for school-scoped Results |
| Academic year | Year-bound rules | Optional / often used |
| Program / specialization | Vocational tracks | Optional |
| Curriculum | Curriculum-bound weights | Optional |
| Grade level | Level-specific bands | Optional |
| Term | Term-specific rules | Optional |
| Subject | Subject override | Optional |
| Assessment type (`exam_types`) | Weights | Common for weighting |

**Inheritance (conceptual):** more specific scope overrides less specific **only if** a published resolution policy exists — resolution algorithm is **HUMAN DECISION REQUIRED** if multiple overlapping EFFECTIVE policies match.

**Forbidden assumption:** every policy needs every dimension.

---

## 5. Catalog Contracts by Family

### 5.1 Grade Status Eligibility Policy (HD-04) — 3C.1-D

**Question:** Which authoritative `GradeStatus` values may participate in official calculation?

**Authoritative statuses (LIVE — do not invent new values):**

| Value | Name |
|-------|------|
| 1 | Draft |
| 2 | Entered |
| 3 | Submitted |
| 4 | Finalized |
| 5 | Voided |

**Contract fields:**

| Field | Notes |
|-------|-------|
| `eligible_statuses` | Subset of {1..5} — **UNRESOLVED** |
| `require_is_current` | Typically true for SSOT — **proposed architectural default: true**; still confirm |
| `exclude_voided` | Architectural expectation: Voided not current — align with 3B |

```text
OFFICIAL GRADE-STATUS ELIGIBILITY SET: HUMAN DECISION REQUIRED (HD-04)
```

Distinct from HD-18 (dataset completeness).

---

### 5.2 Official Result Eligibility Policy (HD-18) — dataset completeness

**Question:** Is the student dataset complete enough for official calculate/finalize?

**Candidate factors (none mandatory until HD-18):**

| Factor | Status |
|--------|--------|
| Valid academic enrollment | Candidate |
| Valid exam enrollment / active seat | Candidate (LIVE seat rules exist for grade entry) |
| Required subject exists | Candidate |
| Required assessment exists | Candidate |
| Required grade exists | Candidate |
| Grade status eligible (HD-04) | Candidate |
| Required subjects complete | Candidate |
| Withdrawal resolved | Candidate |
| Incomplete resolved | Candidate |
| Term eligible / closed (HD-17) | Candidate |
| Academic year eligible | Candidate |
| Policy requirements satisfied | Candidate |

```text
OFFICIAL RESULT ELIGIBILITY BOUNDARY: HUMAN DECISION REQUIRED (HD-18)
```

---

### 5.3 Weighting Policy (HD-03) — 3C.1-E

**Evidence:** `exam_types.weight_percentage` CHECK 0–100 per type. No sum rule.

| Field | Status |
|-------|--------|
| Assessment type → weight map | Representable |
| Expected total weight | **UNRESOLVED** |
| If sum ≠ expected: reject / normalize / allow | **HUMAN DECISION REQUIRED** |
| Rounding of normalized weights | Via Rounding Policy — **UNRESOLVED** |
| Missing assessment | **HUMAN DECISION REQUIRED** |
| Duplicate assessment | **HUMAN DECISION REQUIRED** |

```text
SUM MUST = 100: UNRESOLVED — HUMAN DECISION REQUIRED (HD-03)
```

---

### 5.4 Absence / Exempt / Incomplete Policy (HD-05) — 3C.1-F

**Authoritative LIVE grade rule:**

```text
is_absent = true  ⇒  score MUST be NULL
```

**Must remain intact** (Phase 3B/3B.1).

| Concept | Equivalence | Status |
|---------|-------------|--------|
| Absent (grade) | Distinct | LIVE semantics |
| Exam seat Absent/Withdrawn | Distinct from grade score | LIVE seat statuses |
| Enrollment Cancelled | Distinct | LIVE |
| Exempt | Not a LIVE Results enum | **Do not create** without later auth |
| Incomplete (result) | Not LIVE | **HUMAN DECISION REQUIRED** |
| Missing grade | Distinct from absent | **HUMAN DECISION REQUIRED** |
| Zero score | Distinct from absent | Must not conflate |

**Calc treatment for absent/missing/etc.:** HUMAN DECISION REQUIRED (exclude / zero / fail / incomplete …).

---

### 5.5 Repeat / Retake Policy (HD-06) — 3C.1-G

**Evidence:** Phase 3B — retake via new session/enrollment → new grade row (mechanism).

| Field | Status |
|-------|--------|
| Selection strategy | latest / highest / first / all / replacement — **UNRESOLVED** |
| Traceability to contributing grade identities | **REQUIRED** (architecture) |
| Scope of “repeat” (same subject/term/year) | **HUMAN DECISION REQUIRED** |

```text
RETAKE SELECTION STRATEGY: HUMAN DECISION REQUIRED (HD-06)
```

---

### 5.6 Term / Annual Aggregation Policy (3C.1-C)

| Field | Status |
|-------|--------|
| Aggregation stages | Assessments → subject-term → (optional) term set → annual |
| SSOT inputs | Current grades per DL-001 only |
| Missing term / incomplete year / transfer / withdraw outcomes | **HD-07 UNRESOLVED** |
| Output statuses | draft / current / finalized / superseded / incomplete — names logical |

```text
MISSING TERM / INCOMPLETE YEAR OUTCOME: HUMAN DECISION REQUIRED (HD-07)
```

---

### 5.7 GPA Policy (HD-01, HD-15) — 3C.1-H

```text
GPA FORMULA: UNRESOLVED — HUMAN DECISION REQUIRED
```

**Contract capable of representing a future approved policy:**

| Field | Notes |
|-------|-------|
| `scale` | 4.0 / 100 / institution — UNRESOLVED |
| `formula_family` | percentage / weighted / credit-hour / other — UNRESOLVED |
| `numerator` / `denominator` | Spec slots |
| `weighting` | Link to Weighting Policy |
| `credit_hours_required` | Distinct from “credits exist in schema” (nullable LIVE) — **HD-15** |
| `rounding` | Link to Rounding Policy |
| `precision` | UNRESOLVED |
| `min` / `max` | UNRESOLVED |
| `missing_value_treatment` | UNRESOLVED |
| `failed_subject_treatment` | UNRESOLVED |
| `repeated_subject_treatment` | Link HD-06 |
| `eligibility` | Link HD-04 + HD-18 |
| `policy_version` / effective period | Required when published |

**Do not invent** 4.0 or percentage as the institutional formula.

---

### 5.8 Letter-Grade Policy (HD-02) — 3C.1-I

**Future-compatible band row:**

| Field | Notes |
|-------|-------|
| `min_score` / `max_score` | Inclusive bounds — values UNRESOLVED |
| `letter` | UNRESOLVED — do not insert A/B/C/D/F |
| `grade_points` | Optional; UNRESOLVED |
| `is_pass` | UNRESOLVED |
| `sort_order` | For display |

```text
LETTER BANDS: HUMAN DECISION REQUIRED (HD-02)
```

---

### 5.9 Ranking Policy (HD-08…10, HD-14) — 3C.1-J

Ranking is **non-authoritative** (DL-005).

#### Scope catalog (examples only — not selected)

section · class · program · school · cohort · academic year

#### Tie policy catalog (examples only — not selected)

competition · dense · ordinal · no rank · institution-defined

#### Privacy catalog (examples only — not selected)

own rank · authorized staff · registrar · peer visibility

```text
RANKING SCOPE / TIES / PRIVACY: HUMAN DECISION REQUIRED (HD-08, HD-09, HD-10)
```

**Permission design (architecture):** separate `results.view_ranking` — not implemented.

**Annual finalize ↔ ranking dependency (HD-14):** catalog must support required / optional / non-blocking — **UNRESOLVED**.

---

### 5.10 Transcript Policy (HD-11, HD-12, HD-16) — 3C.1-K

| Field | Notes |
|-------|-------|
| Source result version pins | Required conceptually |
| `calculation_version` / `policy_version` | Required |
| Source fingerprint | Required |
| Artifact identity | Phase 3D |
| Issuance / revoke / supersede state | Lifecycle design |
| Retention policy reference | **Duration UNRESOLVED (HD-12)** |
| Behavior after later grade correction | **A immutable+supersede vs B auto-change — HD-11 UNRESOLVED** |

**Boundary (HD-16):** 3C = architecture/contracts; **3D = issuance implementation** — preserve unless human changes.

---

### 5.11 Rounding Policy

| Field | Status |
|-------|--------|
| Mode | half-up / banker's / truncate / other — **HUMAN DECISION REQUIRED** |
| Decimal precision | **HUMAN DECISION REQUIRED** |
| Intermediate vs final rounding | **HUMAN DECISION REQUIRED** |

Critical for DL-016 determinism — **do not assume floating-point defaults**.

---

### 5.12 Operational Freshness Policy (HD-13)

| Field | Status |
|-------|--------|
| Target latency | UNRESOLVED (1–5 min was candidate only) |
| Max staleness | UNRESOLVED |
| Warning / breach thresholds | UNRESOLVED |
| Operational mode | best-effort / SLA |

```text
OPERATIONAL RESULTS SLO: HUMAN DECISION REQUIRED (HD-13)
```

---

### 5.13 Term Calendar Binding (HD-17)

Logical states that a **future** calendar policy *may* represent (not invented as LIVE):

open · closed · finalized · reopened

```text
TERM CLOSED SEMANTICS & RESULTS FINALIZE DEPENDENCY: HUMAN DECISION REQUIRED (HD-17)
```

Do not create term lifecycle enums in this phase.

---

## 6. Security Binding

All school-scoped Results policy application must remain compatible with:

```text
Policy → SchoolContext → Handler → Composite FK → RLS → FORCE RLS
```

(DL-011). Policy catalogs themselves that are school-scoped must not provide a bypass path.

---

## 7. FUTURE IMPLEMENTATION REQUIREMENT

When authorized (not now):

- Persist policy families as versioned tables/collections  
- Publish workflow + immutability enforcement  
- Bind official Results rows to `policy_version` + `calculation_version`  
- RLS on school-scoped policy rows if stored per school  

```text
FUTURE IMPLEMENTATION REQUIREMENT — no DDL in Phase 3C.1
```
