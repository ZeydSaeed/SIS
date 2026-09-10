# SIS DATABASE — PHASE 3C.3  
# ANNUAL RESULTS ARCHITECTURE

**Document type:** ARCHITECTURE DESIGN ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.2 Gate (`PASS WITH CONDITIONS`)  
**Status:** ARCHITECTURE DESIGNED — academic rule slots remain UNRESOLVED  

```text
NO DDL · NO MIGRATIONS · NO APPLICATION CODE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 1. Mission Statement

```text
ANNUAL RESULT
```

is a **versioned, derived, auditable, school-isolated, year-level academic projection** of annual academic outcome for a student enrollment. It is **not** Grade SSOT (DL-001) and **must not** become a writable second grade ledger.

### Canonical dependency (LOCKED — DL-003)

```text
exams.student_grades
         |
         +--------------------+
         |                    |
         v                    v
   Term Results         Annual Results
         |                    |
         +--------+-----------+
                  |
         GPA / Ranking / Transcript (future consumers)
```

**FORBIDDEN sole chain:**

```text
student_grades → Term Results → Annual Results   (as only rebuild path)
```

Term Results **MAY** be used as an operational convenience/consistency check.  
A complete Annual Result **rebuild MUST** remain possible from grades + structure + policies + calculation version + eligibility (DL-009/016).

---

## 2. Core Questions Answered

| # | Question | Architectural answer | Status |
|---|----------|----------------------|--------|
| 1 | What is it? | Year-level derived projection per business identity | **LOCKED** concept |
| 2 | Business identity | school + enrollment + academic_year + subject (see §3) | **PROPOSED** |
| 3 | Grain | Align with LIVE: subject under enrollment/year | **PROPOSED** |
| 4 | Source grade set | Explicit contributing grade identities for the year | **LOCKED** requirement |
| 5 | Terms related how? | Terms contribute via exam graph (`exams.term_id`); not sole SSOT | **LOCKED** |
| 6 | Completeness | Separate academic-year / dataset completeness slots | **LOCKED** structure; rules **HDR** |
| 7 | Eligibility | HD-18 distinct from HD-04 | **LOCKED** structure; rules **HDR** |
| 8 | Policy pin | Immutable policy version ids on official versions | **LOCKED** |
| 9 | Calculation pin | Separate `calculation_version` | **LOCKED** |
| 10 | Fingerprint | Logical coverage of inputs | **LOCKED** concept |
| 11 | Rebuild | Independent from grades bundle | **LOCKED** |
| 12 | Supersession | New version; prior retained | **LOCKED** |
| 13 | Grade correction | Outbox triggers; grades SSOT; new annual version | **LOCKED** |
| 14 | Historical truth | No silent mutate; no hard delete official | **LOCKED** |
| 15 | School isolation | SchoolContext → … → RLS → FORCE RLS | **LOCKED** |
| 16 | vs GPA/ranking/transcript | Consumers only; not implied by annual finalize | **LOCKED** |
| 17 | Incomplete year | Representable; outcome **HDR (HD-07)** | **HDR** |
| 18 | Transfer/withdraw | Representable via enrollment + eligibility; rules **HDR** | **HDR** |
| 19 | Concurrency/idempotency | Per business identity; fingerprint idempotency | **PROPOSED** |
| 20 | Failure/recovery | Classified technical / academic / security / HDR | See Failure Model |

---

## 3. Identity: Business / Version / Storage

### 3.1 Business identity (PROPOSED)

**ARCHITECTURAL RECOMMENDATION** based on LIVE structures (grades denorm `enrollment_id`, `subject_id`, `academic_year_id`, `school_id`; exams bind terms):

```text
(school_id, enrollment_id, academic_year_id, subject_id)
```

Optional future extensibility (course/module/competency) must **not** redesign the academic model in this phase — reserve extension points without inventing new grains.

### 3.2 Version identity

```text
business identity + annual_result_version
```

Monotonic versions. Operational current ≠ official current.

### 3.3 Storage identity

Surrogate PK / composite PK = FUTURE IMPLEMENTATION. Must not redefine business meaning.

---

## 4. Term → Annual Relationship

| Concern | Architecture | Decision status |
|---------|--------------|-----------------|
| How terms contribute | Grades linked through exam sessions → exams.term_id within year | Structure **LOCKED** |
| How many terms | Determined by academic calendar for year | Count **not invented** |
| Term identity in source set | Record contributing `term_id`(s) per grade/source | **PROPOSED** |
| Term completeness | Slot: required terms present? | **HDR (HD-07, HD-17)** |
| Missing terms | Detectable; outcome HDR | **HDR** |
| Duplicate term participation | Detectable anomaly | Technical/academic classification TBD by policy |
| Closed/open terms | Binding slot to calendar | **HDR (HD-17)** |
| Incomplete year | Lifecycle/eligibility outcome slot | **HDR (HD-07)** |
| Transfer | Enrollment/school change affects source set + isolation | Rules **HDR** |
| Withdrawal / cancelled enrollment | Enrollment status + HD-18 | **HDR** |

---

## 5. Completeness ≠ Eligibility ≠ Grade Status

| Concept | Question | HD |
|---------|----------|-----|
| **A. Academic-year completeness** | Does structure contain required annual components? | HD-07 / HD-17 |
| **B. Dataset completeness** | Do required grades/terms exist? | HD-18 / HD-07 |
| **C. Eligibility** | May official annual calc/finalize proceed? | HD-18 |
| **D. Grade status** | What statuses do source grades have? | HD-04 / HD-05 |

**Mandatory:** do not collapse A–D into one flag.

---

## 6. Operational vs Official

| | Operational | Official |
|--|-------------|----------|
| Refresh | Async eventual | Explicit finalize |
| Stale | Allowed | Not silently rewritten |
| Pins | May lag | Policy + calc + fingerprint required |
| Masquerade | **Forbidden** as official | Labeled official |

ANNUAL FINALIZED does **not** imply GPA approved, ranking complete, transcript issued, or academic year closed.

---

## 7. Policy & Calculation Binding

Official Annual Result versions pin:

- weighting / eligibility / status / absence / incomplete / retake / annual aggregation / rounding policy version ids (as used)  
- `calculation_version` (separate)  
- `calculated_at`, actor, correlation  

Values of those policies: **HUMAN DECISION REQUIRED** (do not invent).

---

## 8. Boundaries

### Term Results
Term-level projection; parallel consumer of grades; **not** sole annual rebuild source.

### GPA (Phase 3C.4 boundary only)
```text
Annual Results (and/or grades) → GPA input contract → GPA engine
```
No formula/scale/credits (HD-01, HD-15).

### Ranking
Separate versioned snapshots (DL-005). HD-08/09/10/14 unresolved.

### Transcript
Hybrid live + issued artifact (DL-006). Issuance Phase 3D (HD-16). HD-11/12 unresolved.

---

## 9. CQRS Contracts (conceptual)

**Commands:** `CalculateAnnualResult`, `RebuildAnnualResult`, `FinalizeAnnualResult`, `SupersedeAnnualResult`  
**Queries:** `GetCurrentAnnualResult`, `GetOfficialAnnualResult`, `GetAnnualResultHistory`, `GetAnnualResultLineage`, `GetAnnualResultProvenance`

No handlers/APIs/repos in this phase.

---

## 10. School Isolation

```text
SchoolContext → command/query → enrollment/year → annual result
  → composite relational integrity → RLS → FORCE RLS
```

Access by enrollment/student/result/year/subject **without** school boundary = **SECURITY FAILURE** (fail closed). No RLS SQL here.

`student_id` alone is insufficient.

---

## 11. Concurrency & Idempotency

Protect: dual rebuild, duplicate outbox, concurrent finalize/supersede, stale workers.

| Mechanism | Role |
|-----------|------|
| Serialize per business identity | FUTURE IMPLEMENTATION |
| Fingerprint equality | Idempotent official no-op |
| Optimistic concurrency on official current | FUTURE IMPLEMENTATION |
| Correlation / causation ids | Provenance |

---

## 12. Performance Blueprint (no indexes/partitions)

Candidate access: school+year, enrollment+year, subject board, current ops/official, history, fingerprint, provenance, rebuild source lookup.

```text
NO PARTITIONING INITIALLY (DL-010)
```

Revisit if measured: row growth, vacuum, year-scoped latency, index bloat.

---

## 13. Failure Model (classes)

| Scenario | Class (architecture) |
|----------|----------------------|
| Missing term / incomplete year | ACADEMIC — outcome **HDR** |
| Duplicate term participation | Detect → policy/tech review |
| Missing / invalid grade state | ACADEMIC / TECHNICAL per HD-04/05 |
| Withdrawn / transfer / cancelled | ACADEMIC — **HDR** |
| Missing/incompatible policy | Blocking — no invent |
| Missing calculation version | Blocking technical |
| Fingerprint / rebuild mismatch | Blocking / human-review |
| Concurrent rebuild | Retryable / serialize |
| Duplicate event | Idempotent |
| Stale operational | Non-blocking until finalize |
| School-context failure | **Security failure — fail closed** |

---

## 14. Security Failure Model

Missing SchoolContext, ambiguous school, unverifiable composite relation, cross-school source ⇒ **fail closed**. Never unrestricted fallback, never bypass RLS/FORCE RLS, never infer school from arbitrary IDs.

---

## 15. Traceability (summary)

| Lock/HD | 3C.0 | 3C.1 | 3C.2 | 3C.3 |
|---------|------|------|------|------|
| DL-001 SSOT | ✓ | ✓ | ✓ | ✓ preserved |
| DL-002 Term | ✓ | ✓ | designed | boundary preserved |
| DL-003 Annual rebuild | ✓ | ✓ | noted | **designed here** |
| DL-004…006 GPA/rank/transcript | ✓ | catalogs | out of term SSOT | annual boundary only |
| DL-007…016 | ✓ | ✓ | ✓ | ✓ preserved |
| HD-01…18 | unresolved | slots | unresolved | **unresolved preserved** |
| Rounding | unresolved | policy dim | unresolved | unresolved |

---

## 16. Architectural Q&A Gate

| Check | Answer |
|-------|--------|
| Independently rebuildable from grades? | **YES** |
| Term Results sole source forbidden? | **YES** |
| Version/lifecycle/official immutability? | **YES** |
| Source-set / policy / calc / fingerprint? | **YES** |
| School isolation designed? | **YES** |
| HDs invented? | **NO** |
| Implementation performed? | **NO** |
