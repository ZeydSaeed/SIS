# SIS DATABASE — PHASE 3C.4  
# GPA DATA MODEL (LOGICAL)

**Document type:** LOGICAL MODEL — NOT DDL  
**Date:** 2026-09-10  

```text
LOGICAL ENTITY ≠ PHYSICAL TABLE
NO CREATE TABLE · NO INDEXES · NO RLS SQL
```

---

## 1. Principle

GPA is a **separate versioned derived Result** (DL-004), not merely a column on Annual Results.

```text
STALE SKETCH: blueprint results.annual_results.gpa / total_credits
AUTHORITATIVE DESIGN: this logical model + Phase 3C.0–3C.4
```

Ownership: **Results** bounded context (DL-012).  
SSOT for scores: **`exams.student_grades` only** (DL-001).

---

## 2. Logical Concepts

### 2.1 GpaScope

| Aspect | Definition |
|--------|------------|
| Purpose | Discriminates term / year / cumulative / program / … |
| Identity | Scope code + optional anchor ids |
| SSOT? | NO — classification metadata |
| Supported set for v1 | **HUMAN DECISION REQUIRED** |
| Mutability | Scope definitions versioned with policy where needed |

### 2.2 GpaResult (business identity)

| Aspect | Definition |
|--------|------------|
| Purpose | Stable GPA identity for a student enrollment under a scope |
| Identity (PROPOSED) | school_id + enrollment_id + gpa_scope + scope_anchor_id? |
| Ownership | Results |
| SSOT? | **NO** |
| Relationships | Enrollment, school, optional term/year/program |

### 2.3 GpaResultVersion

| Aspect | Definition |
|--------|------------|
| Purpose | One calculated GPA instance (ops or official) |
| Identity | Business identity + gpa_version |
| Lifecycle | CALCULATED / FINALIZED / SUPERSEDED |
| Mutability | Official content immutable in place |
| Historical | Superseded retained (DL-007/015) |

#### Field categories (ARCHITECTURAL)

**Identity:** school_id, enrollment_id, student_id (denorm), gpa_scope, scope anchors, gpa_version  

**Outcome:** gpa_value, scale_metadata — **values/scale HDR (HD-01)**; NULL semantics ≠ invented defaults  

**Eligibility / completeness metadata:** distinct slots — rules **HDR**  

**Input summary:** input_set fingerprint ref / counts included/excluded  

**Policy pins:** GPA policy, grade-point policy, eligibility, retake, transfer, rounding (as used)  

**Calculation:** calculation_version, calculated_at, source_fingerprint  

**Lifecycle:** state, ops/official current flags, finalized_at, superseded_at, supersedes / superseded_by, reason  

**Audit:** actor, correlation_id, causation_id  

**Tenant:** school_id mandatory  

### 2.4 GpaInputSet

| Aspect | Definition |
|--------|------------|
| Purpose | Versioned set of candidates considered for a GPA version |
| Identity | Tied to GpaResultVersion (or immutable payload thereof) |
| SSOT? | NO — derived projection |
| Required for official | **YES** (reconstructible) |

### 2.5 GpaInputItem

| Aspect | Definition |
|--------|------------|
| Purpose | One considered academic unit/record in the input set |
| Captures | Source identity (grade / annual / term version refs), inclusion flag, exclusion reason, credit value + credit source, grade-point value + mapping policy ref, retake role, transfer flags |
| Provenance | Must support “why this GPA?” audit |
| Policy values | **HDR** — architecture stores slots only |

### 2.6 AcademicUnit (reference)

| Aspect | Definition |
|--------|------------|
| Purpose | Subject/course/module under LIVE curriculum (extensible later) |
| LIVE anchor | `curriculum.subjects` (+ future units without redesign now) |
| Credit link | Via CreditDefinition |

### 2.7 CreditDefinition

| Aspect | Definition |
|--------|------------|
| Purpose | Bind a credit value to an academic unit / input item |
| Captures | value, source, effective context, version |
| Semantics | **HD-15 HUMAN DECISION REQUIRED** |
| LIVE note | `subjects.credit_hours` nullable ≠ policy approved |

### 2.8 GradePointInterpretation

| Aspect | Definition |
|--------|------------|
| Purpose | Map grade/outcome → grade points under policy |
| Captures | input outcome ref, points, policy version |
| Semantics | **HD-01 / HD-02 HDR** |

### 2.9 EligibilityOutcome

| Aspect | Definition |
|--------|------------|
| Purpose | Record eligibility gate results separately from contribution |
| Related HDs | HD-04, HD-05, HD-06, HD-07, HD-18 |

### 2.10 PolicyVersion / CalculationVersion / Fingerprint / Supersession / Provenance

Same roles as Term/Annual Results: immutable published policies (DL-014); engine pin (DL-008); logical fingerprint coverage; predecessor/successor lineage; actor/time/correlation/causation.

### 2.11 AnnualResult / TermResult / Grade (external to GPA store)

| Concept | Role for GPA |
|---------|--------------|
| AnnualResultVersion | Primary year-scoped input projection |
| TermResultVersion | Optional term-scoped input |
| Grade | Ultimate SSOT for reconstruction/verification |

GPA must not own or mutate these.

---

## 3. Relationships (logical)

```text
GpaResult 1──* GpaResultVersion
GpaResultVersion 1──1 GpaInputSet (logical)
GpaInputSet 1──* GpaInputItem
GpaInputItem *──? AnnualResultVersion / TermResultVersion / Grade
GpaInputItem *──? CreditDefinition
GpaInputItem *──? GradePointInterpretation
GpaResultVersion *──* PolicyVersion (pins)
GpaResultVersion ── CalculationVersion
GpaResultVersion ── Fingerprint
GpaResultVersion ── Supersession lineage
```

---

## 4. Provenance Path (“Why this GPA?”)

```text
GPA value
 ↓
GpaResultVersion
 ↓
policy versions + calculation_version + rounding policy
 ↓
GpaInputSet
 ↓
each GpaInputItem (included)
    ↓ credit value + source
    ↓ grade-point interpretation + policy
    ↓ source grade / Annual / Term version
 ↓
excluded items + exclusion reasons
```

Auditor must reconstruct this path for every official GPA.

---

## 5. Mutability Matrix

| Concept | Operational | Official |
|---------|-------------|----------|
| GpaResultVersion content | Replaceable via new version | Immutable in place |
| Input set of official version | Fixed at finalize | Immutable |
| Superseded versions | Retained | Retained |
| Grades | SSOT mutable via VOID+INSERT | N/A |

---

## 6. Historical Behavior

- Official GPA never hard-deleted  
- Corrections create new versions  
- Prior official → SUPERSEDED with lineage  
- Rebuild does not require prior GPA row as input  
