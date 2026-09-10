# SIS DATABASE — PHASE 3C.4  
# GPA CALCULATION CONTRACT (TECHNOLOGY-NEUTRAL)

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  
**Related:** Phase 3C.1 Calculation Contract (general Results) · Phase 3C.1 GPA Policy Catalog §5.7  

```text
NO EXECUTABLE ALGORITHM · NO CODE · NO DDL
NO INVENTED FORMULA / SCALE / CREDITS / ROUNDING
```

---

## 1. Purpose

Define a deterministic, version-pinned, rebuildable **GPA calculation contract** without selecting institutional GPA rules.

```text
GPA Input Dataset
  + Policy Versions (GPA, grade-point, eligibility, retake, transfer, rounding as applicable)
  + Calculation Version
  + Eligibility State
            ↓
   Deterministic GPA Calculation  F(...)   ← F = HUMAN DECISION REQUIRED
            ↓
   GPA Result + Metadata + Fingerprint + Provenance
```

---

## 2. Separation of Concerns

| Layer | Role |
|-------|------|
| `exams.student_grades` | Sole grade SSOT |
| Annual / Term Results | Optional governed projections into GPA Input |
| GPA Input Dataset | Explicit considered / included / excluded items |
| Policy versions | Immutable published academic rules (DL-014) |
| Calculation version | Engine/algorithm identity (DL-008) |
| GPA Result Version | Derived metric (DL-004) |
| Outbox | Invalidation trigger only (DL-009) |

---

## 3. Inputs

### 3.1 Eligible GPA records (after gates)

Each record conceptually provides:

| Input | Notes |
|-------|-------|
| Source identity | Grade and/or Annual/Term version refs |
| Academic unit | Subject / future unit |
| Inclusion flag | Contributes or not |
| Exclusion reason | If excluded — codes **HDR** |
| Credit value | **HD-15 HDR** |
| Credit source / context | Provenance |
| Grade-point value | **HD-01/HD-02 HDR** |
| Grade-point policy version | Pin |
| Eligibility outcomes | Distinct from inclusion |
| Retake / transfer markers | Policy-controlled |

### 3.2 Policy inputs

| Policy family | HD / status |
|---------------|-------------|
| GPA formula / scale | HD-01 **UNRESOLVED** |
| Letter / grade-point conversion | HD-02 **UNRESOLVED** |
| Weighting (if GPA uses weighted outcomes) | HD-03 **UNRESOLVED** |
| Grade-status eligibility | HD-04 **UNRESOLVED** |
| Absent/exempt/withdrawn/incomplete | HD-05 **UNRESOLVED** |
| Retake selection | HD-06 **UNRESOLVED** |
| Incomplete year / transfer / withdraw | HD-07 **UNRESOLVED** |
| Credit hours | HD-15 **UNRESOLVED** |
| Dataset completeness / calc eligibility | HD-18 **UNRESOLVED** |
| Rounding | **UNRESOLVED** |

### 3.3 Engine input

| Input | Required for official |
|-------|------------------------|
| `calculation_version` | YES |
| `gpa_scope` + anchors | YES |
| School / enrollment context | YES |

---

## 4. Preconditions

| Precondition | Failure |
|--------------|---------|
| SchoolContext present and unambiguous | Security fail closed |
| Required policy versions published & effective | Blocking — no invent |
| Calculation version known | Blocking |
| GPA Input Dataset reconstructible | Blocking |
| Credit / grade-point bindings complete when policy requires them | Blocking / HDR |
| No write of authoritative scores into GPA store | Hard forbid |

---

## 5. Eligibility Gates (structure only)

| Gate | Question | HD |
|------|----------|-----|
| A | Grade-status / outcome eligibility | HD-04 |
| B | Absence / incomplete / withdraw / exempt treatment | HD-05 |
| C | Retake selection among candidates | HD-06 |
| D | Dataset completeness for scope | HD-18 / HD-07 |
| E | Credit presence when required | HD-15 |
| F | Grade-point mappable | HD-01/02 |

Gate outcomes recorded separately from numeric contribution.

Until gates are human-approved: **official** GPA calculation must **refuse** with policy-missing — do not invent defaults.

---

## 6. Formula Placeholder

```text
HUMAN DECISION REQUIRED (HD-01)

F = approved_function(
  included_items[],
  credit_values[],
  grade_point_values[],
  rounding_policy,
  calculation_version,
  scale_metadata
)
```

**Not specified here:** 4.0 / 5.0 / 100 / weighted / unweighted / vocational scales.

Output must carry **scale metadata** describing which approved scale was used (once chosen), not invent one.

---

## 7. Outputs

### 7.1 GPA projection

| Output | Notes |
|--------|-------|
| `gpa_value` | Numeric result under approved F — **HDR until F exists** |
| `scale_metadata` | Describes approved scale family once chosen |
| Included / excluded summaries | Counts + refs |
| Eligibility / completeness metadata | Distinct slots |

### 7.2 Metadata (required for official)

| Field | Lock |
|-------|------|
| Policy version refs | DL-014 |
| `calculation_version` | DL-008 |
| `calculated_at` | DL-008 |
| `source_fingerprint` | DL-008 / DL-016 |
| Actor / correlation / causation | Audit |
| `gpa_version` / supersession | DL-007 |

---

## 8. Fingerprint (logical coverage)

Must cover, as applicable:

```text
gpa_scope + scope anchors + school + enrollment
source record identities + versions
inclusion / exclusion + exclusion reasons
credit values + credit sources
grade-point values + mapping policy versions
eligibility gate outcomes
policy version ids used
calculation_version
rounding policy version
deterministic ordering of contributors
```

**Hash algorithm:** FUTURE IMPLEMENTATION REQUIREMENT (unless later mandated).

---

## 9. Deterministic Equivalence (DL-016)

Identical:

- eligible input records  
- credit values  
- grade-point values  
- policy versions  
- calculation version  
- rounding policy  
- eligibility state  

⇒ logically equivalent GPA Result.

Mismatch classes: source drift · policy drift · calculation drift · implementation defect · non-deterministic dependency.

---

## 10. Rounding

```text
ROUNDING POLICY: UNRESOLVED — HUMAN DECISION REQUIRED
```

Fingerprint includes rounding policy when present. No half-up/even/floor/ceiling/precision chosen here.

---

## 11. Non-goals

- Ranking order  
- Transcript issuance  
- Graduation eligibility  
- Executable calculator code  
- Golden numeric GPA vectors with invented expected values  

Numeric golden vectors for GPA require HD-01/02/15 + Rounding closure (see Phase 3C.1 GV status).
