# SIS DATABASE — PHASE 3C.1 GATE REPORT  
# POLICY & CALCULATION CATALOGS

**Date:** 2026-09-10  
**Phase:** 3C.1 — Policy & Calculation Catalogs  
**Authorization:** Human-approved Phase 3C.1 design-only execution  

---

## A. Execution Scope

```text
Documentation only
No DDL
No migrations
No database writes
No application code
No APIs
No UI
No calculators
No ranking engine
No transcript issuance
```

Confirmed: live `sis` and `sis_test` were not modified; no migrate/wipe/DROP/TRUNCATE.

---

## B. Documents Created / Changed

| File | Action |
|------|--------|
| `docs/database/SIS-DATABASE-PHASE-3C.1-POLICY-CATALOG.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.1-CALCULATION-CONTRACT.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.1-GOLDEN-VECTORS.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.1-POLICY-DEPENDENCY-GRAPH.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.1-HUMAN-DECISION-CLOSURE.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.1-GATE.md` | **Created** (this report) |
| ADR-021…028 files | **Not modified** — remain DRAFT in Phase 3C.0 gate; still `DRAFT / HUMAN APPROVAL REQUIRED` |
| Application / migrations | **None** |

---

## C. Policy Catalog Coverage

| Policy | Designed | Human Decision | Blocks |
|--------|----------|----------------|--------|
| Taxonomy / versioning / scope | YES | Scope conflict resolution if overlapping | Overlap algorithm HDR |
| Grade-status eligibility | YES (slots) | HD-04 | Official calc |
| Dataset eligibility (HD-18) | YES (slots) | HD-18 | Official finalize |
| Weighting | YES (slots) | HD-03 | Term aggregation |
| Absence/exempt/incomplete | YES (separated) | HD-05 | Treatments |
| Retake | YES (slots) | HD-06 | Canonical attempt |
| Term/annual aggregation | YES (pipeline) | HD-07 | Incomplete outcomes |
| GPA | YES (contract) | HD-01, HD-15 | GPA engine |
| Letter grades | YES (band schema) | HD-02 | Letters |
| Ranking scope/ties/privacy | YES (catalogs) | HD-08…10, HD-14 | Ranking |
| Transcript policy | YES (fields) | HD-11, HD-12; HD-16 packaging | 3D issuance |
| Rounding | YES (policy dim) | Rounding HDR | Deterministic numerics |
| Operational SLO | YES (fields) | HD-13 | Published SLO |
| Term calendar binding | YES (compat) | HD-17 | Finalize automation |

---

## D. HD Status (HD-01…HD-18)

| ID | Status |
|----|--------|
| HD-01 | **UNRESOLVED** |
| HD-02 | **UNRESOLVED** |
| HD-03 | **UNRESOLVED** |
| HD-04 | **UNRESOLVED** |
| HD-05 | **UNRESOLVED** |
| HD-06 | **UNRESOLVED** |
| HD-07 | **UNRESOLVED** |
| HD-08 | **UNRESOLVED** |
| HD-09 | **UNRESOLVED** |
| HD-10 | **UNRESOLVED** |
| HD-11 | **UNRESOLVED** |
| HD-12 | **UNRESOLVED** |
| HD-13 | **UNRESOLVED** |
| HD-14 | **UNRESOLVED** |
| HD-15 | **UNRESOLVED** |
| HD-16 | **DEFERRED** (await human CONFIRM of 3C/3D split) |
| HD-17 | **UNRESOLVED** |
| HD-18 | **UNRESOLVED** |

**None marked RESOLVED** (no invented approvals).

---

## E. Calculation Contract Status

| Item | Status |
|------|--------|
| Technology-neutral pipeline | **COMPLETE** |
| Inputs / preconditions / stages / outputs / errors | **COMPLETE** |
| Policy vs calculation version separation | **COMPLETE** |
| Fingerprint logical coverage | **COMPLETE** |
| Determinism / equivalence definition | **COMPLETE** |
| Filled academic parameters | **INCOMPLETE** (HD-blocked) |
| Executable algorithm | **NOT PRODUCED** (correct) |

**Deterministic contract: structurally complete.** Official execution remains blocked on HDs.

---

## F. Golden Vector Status

| Class | Vectors |
|-------|---------|
| Structurally fully specified | GV-12, GV-13, GV-15 (invariant), GV-14 (history structure) |
| Policy-dependent | GV-01, GV-02, GV-04, GV-10 |
| Blocked on HD / rounding | GV-03, GV-05…09, GV-11; numeric expectations broadly |

No fabricated expected GPA/letters/ranks.

---

## G. Conflict Register

```text
CONFLICT
Topic: Grade uniqueness natural key
AUTHORITATIVE SOURCE: Phase 3B LIVE — partial UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current
NON-AUTHORITATIVE SOURCE: data-quality-rules.md — UNIQUE (exam_session_id, student_id)
PROPOSED RESOLUTION: Results catalogs/contracts use exam_enrollment + is_current
HUMAN DECISION REQUIRED: documentation cleanup of data-quality-rules (separate docs task)
```

No other blocking repository conflicts newly discovered in 3C.1.

---

## H. Security Gate

Results policy design does **not** introduce a bypass of:

```text
Policy → SchoolContext → Handler → Composite FK → RLS → FORCE RLS
```

School-scoped catalogs must remain tenant-bound at future implementation (DL-011). Ranking privacy remains HD-10.

---

## I. Historical Truth Gate

| Control | Status |
|---------|--------|
| No silent mutation of official Results | Affirmed (DL-007) |
| Policy versioning immutability | Affirmed (DL-014) |
| Calculation versioning | Affirmed (DL-008) |
| Source fingerprint concept | Affirmed |
| Supersession architecture | Affirmed |
| Issued transcript after correction | **Open HD-11** |

---

## J. Deterministic Rebuild Gate

**DL-016 intact:** same authoritative inputs + policies + calculation version + eligibility boundary ⇒ equivalent Results; rebuild from grades + policies, not processed outbox alone (DL-009).

---

## K. Implementation Boundary

```text
IMPLEMENTATION AUTHORIZATION:
NOT GRANTED FOR DATABASE / APPLICATION IMPLEMENTATION

PHASE 3C.1:
DOCUMENTATION / DESIGN ONLY
```

---

## L. Recommendation

Phase 3C.1 **design objectives are met**: catalogs, calculation contract, golden vectors, dependency graph, and HD closure matrix exist **without inventing academic policy**.

Proceeding to **Phase 3C.2 — Term Results Architecture** is appropriate as the **next design/architecture phase**, with the understanding that **official calculation implementation** remains blocked until P0 HDs (especially HD-03, HD-04, HD-05, HD-06, HD-18) are resolved.

```text
NEXT PHASE:
PHASE 3C.2 — TERM RESULTS ARCHITECTURE
```

**Do not auto-start 3C.2.** Require explicit human approval.

---

## Sub-phase Coverage Checklist

| Sub-phase | Covered in |
|-----------|------------|
| 3C.1-A Taxonomy | Policy Catalog §2 |
| 3C.1-B Versioning | Policy Catalog §3 |
| 3C.1-C Term/annual | Policy Catalog §5.6 + Calc Contract |
| 3C.1-D Grade eligibility | Policy Catalog §5.1 |
| 3C.1-E Weighting | Policy Catalog §5.3 |
| 3C.1-F Absence/etc. | Policy Catalog §5.4 |
| 3C.1-G Retake | Policy Catalog §5.5 |
| 3C.1-H GPA | Policy Catalog §5.7 |
| 3C.1-I Letters | Policy Catalog §5.8 |
| 3C.1-J Ranking | Policy Catalog §5.9 |
| 3C.1-K Transcript | Policy Catalog §5.10 |
| 3C.1-L Deterministic contract | Calculation Contract |
| 3C.1-M Golden vectors | Golden Vectors |
| 3C.1-N HD closure | Human Decision Closure |

---

```text
PHASE 3C.1 GATE: PASS WITH CONDITIONS

IMPLEMENTATION AUTHORIZATION: NOT GRANTED

DATABASE CHANGES: NONE
MIGRATIONS: NONE
API CHANGES: NONE
UI CHANGES: NONE
APPLICATION CODE CHANGES: NONE

NEXT PHASE: PHASE 3C.2 — TERM RESULTS ARCHITECTURE

HUMAN APPROVAL REQUIRED BEFORE PHASE 3C.2
```

### Conditions

1. HD-01…HD-18 remain largely UNRESOLVED (by design — not silently closed).  
2. ADR-021…028 remain DRAFT until human acceptance.  
3. No database/application implementation authorized.  
4. Phase 3C.2 must not begin without explicit human approval.  
5. Official term calculation **implementation** should wait for P0 HDs even if 3C.2 architecture design proceeds.
