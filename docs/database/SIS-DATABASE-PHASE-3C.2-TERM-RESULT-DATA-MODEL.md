# SIS DATABASE — PHASE 3C.2  
# TERM RESULT DATA MODEL (LOGICAL)

**Document type:** LOGICAL DATA MODEL — NOT DDL  
**Date:** 2026-09-10  

```text
LOGICAL ENTITY ≠ PHYSICAL TABLE
NO CREATE TABLE · NO INDEXES · NO RLS SQL
```

---

## 1. Modeling Principle

Balance:

- 3NF correctness for relationships  
- Version/history auditability  
- Rebuildability  
- Read patterns for student/term boards  
- Avoid entity explosion  

Blueprint sketch `results.term_results` is **non-authoritative incomplete DDL**; this model supersedes it for architecture.

---

## 2. Logical Entities

### 2.1 `TermResult` (logical aggregate root identity)

| Aspect | Definition |
|--------|------------|
| Purpose | Stable business identity for a subject-term derived result |
| Ownership | Results bounded context |
| Identity | Business identity (school, enrollment, year, term, subject) |
| Lifecycle | Long-lived; versions attach |
| Tenant | `school_id` required |
| Authoritative? | **NO** — derived |
| Required? | YES for term Results feature |
| Physical table? | OPTIONAL — may be implicit via version rows only |

**ARCHITECTURAL RECOMMENDATION:** Prefer version rows carrying business identity (avoid orphan header if empty) **or** thin header + versions — either acceptable; choose at implement with write amplification evidence.

---

### 2.2 `TermResultVersion`

| Aspect | Definition |
|--------|------------|
| Purpose | One calculated instance (ops or official) |
| Identity | Business identity + `result_version` |
| Lifecycle | CALCULATED / FINALIZED / SUPERSEDED |
| Tenant | `school_id` |
| Authoritative? | Official versions are **authoritative derived**; still not grade SSOT |
| Required? | YES |

#### Conceptual field catalog

**Identity**

- school_id  
- enrollment_id  
- student_id (denorm)  
- academic_year_id  
- term_id  
- subject_id  
- result_version  

**Academic context**

- class_id / section_id (denorm optional for ranking cohorts later)  
- curriculum/program (optional; not assumed required)  

**Outcome (policy-dependent values)**

- total / weighted total — **HDR formulas**  
- pass/fail flag — **HDR**  
- letter — **HDR (HD-02)**  
- incomplete markers — **HDR**  
- NULL/0 semantics per Architecture doc  

**Policy**

- weighting_policy_version_id  
- grade_status_eligibility_policy_version_id  
- dataset_eligibility_policy_version_id  
- absence_treatment_policy_version_id (if separate)  
- rounding_policy_version_id  
- other family version ids as used  

**Calculation**

- calculation_version  
- calculated_at  
- source_fingerprint  
- eligibility_outcome summary  

**Lifecycle / versioning**

- lifecycle_state  
- is_operational_current  
- is_official_current  
- finalized_at  
- superseded_at  
- supersedes_version / superseded_by_version  
- recalculation_reason  

**Audit**

- calculated_by / finalized_by (user or system:)  
- correlation_id  
- created_at / updated_at (technical)  

**Tenant isolation**

- school_id on every row; composite relationships to enrollment/school  

All above are **ARCHITECTURAL FIELDS**, not final SQL types.

---

### 2.3 `TermResultSource` (contributing grades)

| Aspect | Definition |
|--------|------------|
| Purpose | Explicit reconstructible contributor set |
| Cardinality | Version 1—N sources |
| Identity | version + grade (id, academic_year_id) |
| Captures | status, is_current, score, max_score, is_absent, exam_enrollment_id, contribution role after retake policy |
| Authoritative? | Snapshot of inputs at calc time; grades remain SSOT |
| Required for official? | **YES** (traceability) |
| Physical? | Table or structured immutable payload — implement choice later |

---

### 2.4 `TermResultPolicyBinding`

| Aspect | Definition |
|--------|------------|
| Purpose | Normalize multi-policy pins if not embedded |
| Required? | OPTIONAL if version embeds policy ids |
| Avoid over-normalization | Embed on version unless many families force join hell |

---

### 2.5 `TermResultCalculationMetadata`

| Aspect | Definition |
|--------|------------|
| Purpose | calculation_version, fingerprint, engine diagnostics |
| Required? | OPTIONAL separate entity; may embed on version (DL-008) |
| Recommendation | **Embed on TermResultVersion** for official rows |

---

## 3. Relationships (conceptual)

| From | To | Cardinality | Ownership | Delete behavior |
|------|----|-------------|-----------|-----------------|
| TermResultVersion | Enrollment | N—1 | Derived ref | **No CASCADE** of academic history |
| TermResultVersion | Term / Year / School / Subject | N—1 | Ref | Restrict / no cascade truth |
| TermResultVersion | Grades (via sources) | N—N | Snapshot | Grades never deleted hard; sources retained |
| TermResultVersion | Policy versions | N—N | Pin | Policies immutable published |
| Official vN | Official vN-1 | supersession | Lineage | Retain both |

**ON DELETE CASCADE** of official Term Result history when enrollment/student removed: **FORBIDDEN** without explicit future human justification. Prefer restrict / soft academic end dates.

---

## 4. Foreign Key Design Notes

- Prefer composite school-aware FKs where LIVE pattern exists (`enrollment_id, school_id`).  
- Grade references must include `academic_year_id` (partitioned grade identity).  
- Do not FK-only on `student_id` for tenant safety.

---

## 5. Delete Policy

| Object | Behavior |
|--------|----------|
| Operational CALCULATED | May be superseded/replaced; no need to retain every ops attempt forever — retention of ops history **HDR/ops policy** |
| Official FINALIZED / SUPERSEDED | **No hard delete** (DL-015) |
| Source rows for official | Retain with version |

---

## 6. Index Strategy Blueprint (NOT implemented)

See Architecture doc §15. No migration SQL.

---

## 7. Partitioning

```text
NO PARTITIONING INITIALLY (DL-010)
```

Future trigger: measured row count, index bloat, year-scoped query dominance, vacuum cost.

---

## 8. Normalization Decision

| Approach | Verdict |
|----------|---------|
| Fully separate header/version/source/policy/meta always | May over-normalize |
| Single wide version row + source children | **ARCHITECTURAL RECOMMENDATION** |
| JSON-only opaque blob as sole store | **REJECTED** for official (audit/query/RLS) |

Intentional denorm: `school_id`, `student_id`, placement ids for read/RLS — justified like grades/attendance patterns.
