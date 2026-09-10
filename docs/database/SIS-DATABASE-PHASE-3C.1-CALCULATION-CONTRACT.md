# SIS DATABASE — PHASE 3C.1  
# CALCULATION CONTRACT (TECHNOLOGY-NEUTRAL)

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  
**Locks:** DL-001…DL-016  
**Status:** CONTRACT STRUCTURE COMPLETE — academic rule slots largely UNRESOLVED  

```text
NO EXECUTABLE ALGORITHM · NO CODE · NO DDL
```

---

## 1. Purpose

Define a deterministic, rebuildable calculation contract for derived Results without inventing GPA, letters, ties, or eligibility rules.

```text
Authoritative Inputs
      + Academic Structure
      + Policy Version(s)
      + Calculation Version
      + Eligibility Policy
            ↓
   Deterministic Calculation
            ↓
      Derived Result + Metadata + Fingerprint
```

---

## 2. Separation of Concerns

| Layer | Role |
|-------|------|
| `exams.student_grades` | Sole grade SSOT (DL-001) |
| Policy versions | Immutable published academic rules (DL-014) |
| Calculation version | Engine/algorithm identity (DL-008) |
| Derived Results | Versioned projections/snapshots (DL-002/003/005) |
| Outbox | Async invalidation trigger only (DL-009) — **not** rebuild SSOT |

---

## 3. Inputs

### 3.1 Authoritative grade inputs

From LIVE grades (conceptual selection; filters depend on HD-04 / HD-18):

| Input | Notes |
|-------|-------|
| Grade identity `(id, academic_year_id)` | Composite identity |
| `is_current` | SSOT pointer |
| `status` | GradeStatus 1…5 |
| `score` / `max_score` | NUMERIC; absent ⇒ score NULL |
| `is_absent` | Authoritative |
| `exam_enrollment_id` / `exam_session_id` / `subject_id` | Graph keys |
| `enrollment_id` / `student_id` / `school_id` | Scope |
| `correction_of_grade_id` | Trace only; prefer current for live calc |

### 3.2 Academic structure inputs

| Input | Source evidence |
|-------|-----------------|
| Academic year | `academic.academic_years` |
| Term | `academic.terms` (+ `term_order`) |
| Exam / type / session | `exams.*` |
| `weight_percentage` | `exam_types` |
| Enrollment placement | class/section for ranking cohorts |
| Subject / optional credits | `curriculum.subjects` (`credit_hours` nullable) |

### 3.3 Policy inputs

Published versions of catalogs in `SIS-DATABASE-PHASE-3C.1-POLICY-CATALOG.md` applicable to scope/time.

### 3.4 Engine input

| Input | Notes |
|-------|-------|
| `calculation_version` | Required on official outputs |

---

## 4. Preconditions

| Precondition | Failure mode |
|--------------|--------------|
| School context available for school-scoped calc | Reject / unauthorized |
| Required policy versions published & effective | Incomplete / blocked |
| Calculation version known | Reject |
| Partition/year grades readable | Infra error (ops) |
| No attempt to write authoritative scores into Results | Hard forbid |

---

## 5. Eligibility (two gates)

### Gate A — Grade-status eligibility (HD-04)

Which statuses may contribute — **HUMAN DECISION REQUIRED**.

### Gate B — Dataset completeness (HD-18)

Whether official calculate/finalize is allowed — **HUMAN DECISION REQUIRED**.

Outputs of eligibility:

| State | Meaning |
|-------|---------|
| `ELIGIBLE` | May calculate / may finalize per policy |
| `INELIGIBLE` | Must not produce official finalize |
| `INCOMPLETE` | May produce incomplete operational result if policy allows (HD-07) — UNRESOLVED |

---

## 6. Transformation Stages (logical)

```text
1. Collect candidate grades (school/year/enrollment/term scope)
2. Apply retake selection policy (HD-06) → canonical contributors
3. Apply status eligibility (HD-04)
4. Apply absence/missing/exempt treatments (HD-05) — UNRESOLVED treatments
5. Apply weighting (HD-03) — UNRESOLVED normalization
6. Aggregate subject-term totals
7. Aggregate term / annual rollups as requested
8. Optionally compute GPA (HD-01) — formula UNRESOLVED
9. Optionally map letters (HD-02) — bands UNRESOLVED
10. Optionally emit ranking snapshot inputs (HD-08…10) — UNRESOLVED
11. Apply rounding policy — UNRESOLVED
12. Emit derived result + metadata + fingerprint
```

Stages 8–10 are **optional modules**; absence of approved policy ⇒ stage outputs `HUMAN DECISION REQUIRED` / not executable for official use.

---

## 7. Outputs

### 7.1 Derived result payload (logical)

| Output | Notes |
|--------|-------|
| Result kind | term_line / term_set / annual / gpa / ranking_snapshot / transcript_projection |
| Semantic values | Totals, pass flags, ranks, GPA — **values policy-dependent** |
| Contributing grade identities | Required for audit/rebuild |
| Eligibility state | From §5 |
| Incomplete flags | If allowed |

### 7.2 Metadata (required for official)

| Field | Lock |
|-------|------|
| `policy_version` refs (per family used) | DL-014 |
| `calculation_version` | DL-008 |
| `calculated_at` | DL-008 |
| `source_fingerprint` | DL-008 / DL-016 |
| Actor / system identity | Audit |
| Correlation id | Platform |
| Result version / supersedes | DL-007 |

---

## 8. Source Fingerprint (logical contents)

Fingerprint conceptually covers:

```text
contributing grade identities + academic_year_id
grade status / is_current / is_absent
score + max_score (canonical string/decimal form per Rounding Policy once approved)
academic structure keys (term, exam graph, enrollment, school)
policy_version ids used
calculation_version
eligibility gate outcomes / boundary id
deterministic ordering of contributors
```

**Hash algorithm:** FUTURE IMPLEMENTATION REQUIREMENT (unless later mandated). Logical coverage is specified now.

---

## 9. Errors (logical classes)

| Class | Examples |
|-------|----------|
| `UNAUTHORIZED` | School isolation failure |
| `POLICY_MISSING` | No effective published policy |
| `ELIGIBILITY_FAILED` | HD-18 ineligible |
| `INPUT_INCONSISTENT` | Corrupt chain / missing structure |
| `WEIGHT_INVALID` | If HD-03 chooses reject |
| `CALCULATION_UNSUPPORTED` | GPA requested without HD-01 |
| `NON_DETERMINISTIC_CONFIG` | Rounding policy missing when required |

Do not expose raw SQL to clients (future API rule).

---

## 10. Determinism & Equivalence (DL-016)

Same:

- authoritative grades (as selected by policy)
- academic structure
- policy versions
- calculation version
- ranking policy (if used)
- eligibility boundary

⇒ **equivalent** Results.

### Equivalence means (conceptual)

| Aspect | Equivalent if |
|--------|----------------|
| Semantic values | Same totals/flags/GPA/letters/ranks after approved rounding |
| Contributing records | Same ordered set of grade identities |
| Eligibility state | Same gate outcomes |
| Policy references | Same version ids |
| Ordering | Stable sort keys where multi-row |

Non-goals: bitwise identical floating intermediates if Rounding Policy defines canonical finals.

---

## 11. Consistency Modes (DL-013)

| Mode | Contract use |
|------|----------------|
| Operational (eventual) | May emit `current`/stale-refresh projections async |
| Official finalize (strong) | Explicit finalize; then immutable except supersede |
| Issued transcript | Out of calc engine; Phase 3D artifact (HD-16) |

Grade handlers must **not** synchronously write Results (DL-009).

---

## 12. Contract Completeness Statement

| Area | Status |
|------|--------|
| Pipeline shape / stages | **COMPLETE** |
| Metadata / fingerprint / versions | **COMPLETE** |
| Determinism invariant | **COMPLETE** |
| Academic rule parameters | **INCOMPLETE — HD-blocked** |
| Executable algorithm | **NOT IN SCOPE** |

**Deterministic contract structure: COMPLETE.**  
**Executable official calculation: BLOCKED pending human decisions.**
