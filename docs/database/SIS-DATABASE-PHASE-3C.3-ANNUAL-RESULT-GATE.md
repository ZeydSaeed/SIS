# SIS DATABASE — PHASE 3C.3 GATE REPORT  
# ANNUAL RESULTS ARCHITECTURE

**Date:** 2026-09-10  
**Phase:** 3C.3 — Annual Results Architecture  
**Authorization:** Design/architecture only  

---

## Executive Summary

Phase 3C.3 designs Annual Results as a **versioned, derived, school-isolated, year-level projection** that is **independently rebuildable from `exams.student_grades`**, without making Term Results the sole rebuild source, without inventing GPA/ranking/transcript/absence/retake/completeness policies, and without any implementation.

---

## Scope Executed

- Architecture, lifecycle, logical data model, invariants, rebuild/propagation design  
- Traceability to 3C.0–3C.2  
- Conflict documentation (stale uniqueness docs)  
- Gate report  

## Scope Explicitly Not Executed

```text
No DDL, migrations, SQL, indexes, RLS, partitions
No models/repos/services/handlers/commands/jobs/APIs/UI
No GPA/ranking/transcript engines
No database writes to sis or sis_test
```

---

## Documents Created

| File | Action |
|------|--------|
| `docs/database/SIS-DATABASE-PHASE-3C.3-ANNUAL-RESULTS-ARCHITECTURE.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.3-ANNUAL-RESULT-LIFECYCLE.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.3-ANNUAL-RESULT-DATA-MODEL.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.3-ANNUAL-RESULT-INVARIANTS.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.3-ANNUAL-RESULT-REBUILD.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.3-ANNUAL-RESULT-GATE.md` | Created (this report) |
| ADR-021…028 | Unchanged (DRAFT) |
| Application / migrations | None |

---

## Architecture Decisions

| Topic | Decision |
|-------|----------|
| SSOT | Grades only; Annual derived |
| Rebuild | Independent from grades bundle; Term Results optional aid only |
| Grain (PROPOSED) | enrollment × academic_year × subject |
| Dual current | Operational ≠ official |
| Lifecycle | NOT_CALCULATED → CALCULATED → FINALIZED → SUPERSEDED |
| Finalize scope | Does not imply GPA/ranking/transcript/year-closed |

---

## Annual Result Identity

**Business (PROPOSED):** `(school_id, enrollment_id, academic_year_id, subject_id)`  
**Version:** business + monotonic `annual_result_version`  
**Storage:** FUTURE — not confused with business identity  

---

## Annual Result Grain

Anchored on LIVE subject/enrollment/year structures; extensible later for vocational units **without** redesigning academics in this phase.

---

## Term Relationship

Terms contribute via exam graph; completeness/closure outcomes are **HDR (HD-07, HD-17)** slots.

---

## Source Grade Set

Explicit reconstructible contributors (identity, term, status, scores, absence, inclusion).

---

## Completeness vs Eligibility

Separated: academic-year completeness · dataset completeness · eligibility (HD-18) · grade status (HD-04/05). Not collapsed.

---

## Lifecycle / Operational vs Official / Versioning

Documented in Lifecycle artifact; dual currents; supersession lineage; no silent mutate.

---

## Policy Pins / Calculation Version / Fingerprint

Pinned on official versions; calc version separate from policy; fingerprint logical coverage defined; hash algo FUTURE.

---

## Rebuild Architecture / Grade Correction Propagation

Canonical flow and outbox-as-trigger-only path documented; affected identity via school/enrollment/year/subject.

---

## Supersession / School Isolation / CQRS / Concurrency

Documented; fail-closed tenant path; command/query contracts only; idempotency via fingerprint + per-identity serialization (FUTURE IMPLEMENTATION).

---

## Performance Blueprint

Access patterns listed; **NO PARTITIONING INITIALLY**; evidence thresholds for later revisit.

---

## Failure Model / Security Failure Model

Technical / academic / security / HDR classes; school failures fail closed; no invent of HD outcomes.

---

## GPA / Ranking / Transcript Boundaries

Consumers only; no formulas/scopes/issuance; HD-01/08–12/14–16 preserved unresolved/deferred.

---

## Invariants

AR-INV-001 … AR-INV-025 in invariants document.

---

## Traceability Matrix

| Item | Status in 3C.3 |
|------|----------------|
| DL-001…DL-016 | **Preserved** |
| HD-01…HD-15, HD-17, HD-18 | **Unresolved** (dependent slots designed) |
| HD-16 | **Deferred** (await confirm) |
| Rounding | **Unresolved** |
| 3C.2 Term Results | **Boundary preserved** — parallel consumer of grades |

---

## Human Decisions Remaining

| Status | IDs |
|--------|-----|
| RESOLVED | *(none)* |
| UNRESOLVED | HD-01…HD-15, HD-17, HD-18, Rounding |
| DEFERRED | HD-16 |

---

## Stale Documentation / Conflicts

```text
CONFLICT: Grade uniqueness documentation
AUTHORITATIVE: Phase 3B LIVE partial UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current
NON-AUTHORITATIVE: data-quality-rules.md UNIQUE (exam_session_id, student_id)
PROPOSED RESOLUTION: Annual source set uses exam_enrollment / is_current semantics
HUMAN DECISION REQUIRED: documentation cleanup task (not executed here)

STALE SKETCH: blueprint results.annual_results lacks versioning/school_id/policy pins/source set
— superseded by Phase 3C.3 logical model for architecture purposes
```

---

## Risks

| Risk | Mitigation |
|------|------------|
| Implement annual calc before HD-03/04/05/06/18 | Gate conditions |
| Accidental Term→Annual sole dependency | AR-INV-014/015 |
| Finalize confused with transcript/GPA | AR-INV-021 |
| Cross-school IDOR | AR-INV-002/019 |

---

## Conditions

1. No academic HDs silently resolved.  
2. No implementation authorized.  
3. Official annual **calculation implementation** blocked on P0 HDs.  
4. Phase 3C.4 requires separate human approval.  
5. Known doc conflicts recorded, not “fixed” via DDL.

---

## Final Gate

Gate criteria verified: independent rebuild, no Term-sole source, versioning, lifecycle, official immutability, supersession, source-set, policy/calc pins, fingerprint, determinism, school isolation, CQRS, concurrency/idempotency, failure model, performance blueprint, no implementation, HDs preserved.

```text
PHASE 3C.3 GATE: PASS WITH CONDITIONS

IMPLEMENTATION AUTHORIZATION: NOT GRANTED

DATABASE CHANGES: NONE

MIGRATIONS: NONE

API CHANGES: NONE

UI CHANGES: NONE

APPLICATION CODE CHANGES: NONE

DATABASE WRITES: NONE

NEXT PHASE: PHASE 3C.4 — GPA ARCHITECTURE

HUMAN APPROVAL REQUIRED BEFORE PHASE 3C.4
```

**STOP.** Do not begin Phase 3C.4. Do not implement.
