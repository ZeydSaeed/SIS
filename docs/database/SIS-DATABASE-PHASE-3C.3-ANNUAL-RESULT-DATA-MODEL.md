# SIS DATABASE — PHASE 3C.3  
# ANNUAL RESULT DATA MODEL (LOGICAL)

**Document type:** LOGICAL MODEL — NOT DDL  
**Date:** 2026-09-10  

```text
LOGICAL ENTITY ≠ PHYSICAL TABLE
NO CREATE TABLE · NO INDEXES · NO RLS SQL
```

---

## 1. Principle

Same balance as Term Results: auditability, rebuildability, tenant isolation, avoid over-normalization. Blueprint `results.annual_results` sketch is **incomplete/stale** relative to versioning, school_id, policy pins, source set.

---

## 2. Logical Concepts

### 2.1 AnnualResult (business identity)

| Aspect | Definition |
|--------|------------|
| Purpose | Stable year-level subject (or future unit) identity |
| Identity (PROPOSED) | school_id + enrollment_id + academic_year_id + subject_id |
| Ownership | Results context |
| SSOT? | **NO** |
| Extensibility | Future vocational grains without redesigning now |

### 2.2 AnnualResultVersion

| Aspect | Definition |
|--------|------------|
| Purpose | One calculated instance (ops or official) |
| Identity | Business identity + annual_result_version |
| Lifecycle | CALCULATED / FINALIZED / SUPERSEDED |
| Mutability | Official content immutable in place |
| Historical | Superseded retained |

#### Field categories (ARCHITECTURAL FIELDS)

**Identity:** school_id, enrollment_id, student_id (denorm), academic_year_id, subject_id, annual_result_version  

**Academic context:** class/section denorm optional; contributing term_ids summary  

**Outcome:** year totals / pass flags / letters — **values HDR**; NULL≠0≠absent≠incomplete  

**Completeness metadata:** academic-year completeness outcome; dataset completeness outcome — **rules HDR**  

**Eligibility metadata:** HD-18 outcome — **rules HDR**; distinct from grade statuses present  

**Policy pins:** family version ids used  

**Calculation:** calculation_version, calculated_at, source_fingerprint  

**Lifecycle:** state, ops/official current flags, finalized_at, superseded_at, supersedes / superseded_by, reason  

**Audit/provenance:** actor, correlation_id, causation_id  

**Tenant:** school_id mandatory  

### 2.3 AnnualResultSource (grade contributions)

| Aspect | Definition |
|--------|------------|
| Purpose | Answer “which grades contributed?” |
| Captures | grade (id, academic_year_id), term_id, subject, enrollment, status, is_current, score, max_score, is_absent, inclusion/exclusion, retake role |
| Required for official | **YES** |
| Physical form | Table or immutable structured payload — FUTURE |

### 2.4 AnnualResultTermParticipation (optional logical)

| Aspect | Definition |
|--------|------------|
| Purpose | Which terms were expected vs observed for completeness evaluation |
| Required? | OPTIONAL if derivable from sources + calendar |
| Rules for expected terms | **HDR (HD-07/17)** |

### 2.5 Policy / Calculation bindings

Prefer **embed** policy version ids + calculation_version on AnnualResultVersion (same recommendation as Term). Separate binding entities only if join complexity justifies.

### 2.6 Academic Year / Enrollment / Term / Subject

External LIVE structures — referenced, not owned by Results. Annual Result does not redefine them.

---

## 3. Relationships

| From | To | Notes |
|------|----|-------|
| AnnualResultVersion | Enrollment / School / Year / Subject | N—1 refs; **no CASCADE** of official history |
| AnnualResultVersion | Grades via sources | Snapshot of contribution |
| AnnualResultVersion | Policy versions | Pins |
| Official vN | Official vN-1 | Supersession lineage |
| AnnualResultVersion | Term Results | **Optional** consistency reference only — not required for rebuild |

---

## 4. Delete Policy

| Object | Behavior |
|--------|----------|
| Operational versions | Ops retention policy **HDR**/ops; may replace current |
| Official FINALIZED/SUPERSEDED | **No hard delete** (DL-015) |
| Official sources | Retain with version |

---

## 5. Provenance

Every official version must support reconstruction of:

who/what calculated · when · which policies · which calc version · which grades · fingerprint · predecessor · why superseded
