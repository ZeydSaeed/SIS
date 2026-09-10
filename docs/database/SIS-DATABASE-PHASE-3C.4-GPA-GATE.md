# SIS DATABASE — PHASE 3C.4 GATE REPORT  
# GPA ARCHITECTURE

**Date:** 2026-09-10  
**Phase:** 3C.4 — GPA Architecture  
**Authorization:** Design/architecture only  

---

## Executive Summary

Phase 3C.4 designs GPA as a **versioned, derived, school-isolated academic calculation** with an explicit **GPA Input Dataset**, policy/calculation/rounding pins, fingerprints, supersession, and rebuild — **without inventing** GPA scale, formula, credits, grade points, or rounding. Grades remain SSOT; Annual Results remain independently rebuildable from grades.

---

## Scope Executed

- GPA architecture, lifecycle, logical data model, calculation contract, invariants, rebuild/propagation  
- Boundaries with Annual Results, ranking, transcript, graduation  
- Traceability to 3C.0–3C.3  
- Stale documentation / conflicts recorded  
- Gate report  

## Scope Explicitly Not Executed

```text
No DDL, migrations, SQL, indexes, RLS, partitions
No models/repos/services/handlers/commands/jobs/APIs/UI
No GPA/ranking/transcript engines or formulas
No database writes to sis or sis_test
```

---

## Documents Created

| File | Action |
|------|--------|
| `docs/database/SIS-DATABASE-PHASE-3C.4-GPA-ARCHITECTURE.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.4-GPA-LIFECYCLE.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.4-GPA-DATA-MODEL.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.4-GPA-CALCULATION-CONTRACT.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.4-GPA-INVARIANTS.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.4-GPA-REBUILD.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.4-GPA-GATE.md` | Created (this report) |
| ADR-021…028 | Unchanged (DRAFT) |
| Application / migrations | None |

---

## Authoritative Sources

| Precedence | Source |
|------------|--------|
| 1 | LIVE Phase 3B/3B.1 grades (`exams.student_grades`, VOID+INSERT, partial UNIQUE) |
| 2 | Migrations / schema reality |
| 3 | Phase 3C.0 Design Locks DL-001…016 |
| 4 | Phase 3C.1–3C.3 design artifacts |
| 5 | Older docs (blueprint GPA column sketch, data-quality scale text) — non-authoritative where conflicting |

---

## Architecture Decisions

| Topic | Decision |
|-------|----------|
| Nature | Derived metric (DL-004); grades SSOT |
| Path | Grades → Term/Annual projections → GPA Input → GPA |
| Input | Explicit GpaInputSet / GpaInputItem with include/exclude/reasons |
| Dual current | Operational ≠ official |
| Lifecycle | NOT_CALCULATED → CALCULATED → FINALIZED → SUPERSEDED |
| Formula/scale/credits/rounding | **Not chosen** — HDR |
| Ranking/transcript | Consumer boundaries only |

---

## GPA Identity

**Business (PROPOSED):** `(school_id, enrollment_id, gpa_scope, scope_anchor_id?)`  
**Version:** business + monotonic `gpa_version`  
**Storage:** FUTURE — distinct from business identity  

---

## GPA Scope

Term / academic-year / cumulative = architecture-supported classifications.  
Program / graduation / transfer-adjusted = policy-dependent or future.  
Official v1 supported set = **HUMAN DECISION REQUIRED**.

---

## GPA Input Dataset

Modeled: source identity, inclusion, exclusion, reason, credit binding, grade-point binding, policy pins. Reconstructible; values HDR.

---

## Annual Result → GPA Boundary

Annual → GPA Input Projection → Calculation → GPA Version.  
Annual FINALIZED ≠ GPA FINALIZED; GPA does not mutate Annual Results; impact detection required.

---

## Eligibility Model

Existence ≠ validity ≠ completeness ≠ academic eligibility ≠ contribution. HD-04/05/06/07/15/18 unresolved.

---

## Credit-Hour Boundary

Architectural binding only. HD-15 unresolved. LIVE nullable `credit_hours` ≠ mandate.

---

## Grade-Point Boundary

Policy-controlled mapping contract. HD-01/HD-02 unresolved. No invented tables.

---

## Calculation Contract

Technology-neutral `F(...)` placeholder; inputs/outputs/fingerprint/determinism defined; no executable formula.

---

## Rounding / Retake / Transfer Boundaries

Rounding unresolved (fingerprint dependency). Retake HD-06 slots. Transfer provenance slots; no invented transfer policy. Absent≠zero preserved at grade layer; GPA semantics HDR (HD-05).

---

## Lifecycle / Operational vs Official / Versioning

Documented; dual currents; supersession lineage; no silent mutate; GPA FINALIZED ≠ ranking/transcript/graduation/year-closed.

---

## Fingerprint / Rebuild

Logical coverage defined; rebuild from input dataset without prior GPA; Annual independent rebuild preserved.

---

## Grade Correction Propagation / Annual Correction Propagation

Outbox-triggered impact detection → reconstruct → new version → supersede. Not every change forces GPA change.

---

## School Isolation / CQRS / Concurrency / Idempotency

Fail-closed tenant path; command/query contracts only; per-identity serialization + fingerprint + optimistic concurrency (FUTURE IMPLEMENTATION).

---

## Performance Blueprint

Access patterns listed; **NO PARTITIONING INITIALLY**; evidence thresholds for later revisit.

---

## Failure Model / Security Failure Model

Technical / academic / HDR / security classes documented; school failures fail closed; no invent of HD outcomes.

---

## Ranking / Transcript / Graduation Boundaries

GPA → Ranking Input Contract (no rules). GPA → Transcript consumer (HD-11/12/16). Graduation/promotion may consume official GPA; eligibility not defined here.

---

## Logical Data Model / Provenance

GpaResult, Version, Scope, InputSet, InputItem, CreditDefinition, GradePointInterpretation, Eligibility, pins, fingerprint, supersession. Provenance path answers “why this GPA?”

---

## GPA Invariants

GPA-INV-001 … GPA-INV-032.

---

## Traceability Matrix

| Item | 3C.0 | 3C.1 | 3C.2 | 3C.3 | 3C.4 |
|------|------|------|------|------|------|
| DL-001 SSOT | ✓ | ✓ | ✓ | ✓ | **preserved** |
| DL-002 Term | ✓ | ✓ | designed | boundary | **preserved** |
| DL-003 Annual | ✓ | ✓ | noted | designed | **preserved** (GPA must not break) |
| DL-004 GPA | ✓ | catalog slots | — | boundary | **designed here** |
| DL-005 Ranking | ✓ | slots | — | boundary | **boundary only** |
| DL-006 Transcript | ✓ | slots | — | boundary | **boundary only** |
| DL-007…016 | ✓ | ✓ | ✓ | ✓ | **preserved** |
| HD-01 | unresolved | slots | — | — | **unresolved P0** |
| HD-02 | unresolved | slots | — | — | **unresolved P0** (if needed) |
| HD-03…07, HD-18 | unresolved | slots | unresolved | unresolved | **unresolved P0 for inputs** |
| HD-08…12, HD-14 | unresolved | — | — | — | **deferred to ranking/transcript** |
| HD-13 | unresolved | — | — | — | **unresolved** |
| HD-15 | unresolved | slots | — | — | **unresolved P0** |
| HD-16 | deferred | deferred | — | deferred | **deferred** |
| HD-17 | unresolved | — | unresolved | unresolved | **dependency for term GPA** |
| Rounding | unresolved | dim | unresolved | unresolved | **unresolved P0** |

### P0 human decisions blocking GPA **implementation**

```text
HD-01  GPA scale/formula
HD-15  Credit hours (if credit-hour GPA)
HD-02  Letter/grade-point conversion (if required by HD-01)
Rounding policy
HD-04 / HD-05 / HD-06 / HD-07 / HD-18  (eligible input construction)
```

---

## Human Decisions Remaining

| Status | IDs |
|--------|-----|
| RESOLVED | *(none invented)* |
| UNRESOLVED | HD-01…HD-15, HD-17, HD-18, Rounding |
| DEFERRED | HD-16 |

---

## Stale Documentation / Conflicts

```text
STALE: blueprint results.annual_results embeds gpa/total_credits without versioning/school_id/pins/input set
  → superseded for architecture by Phase 3C.4 logical GPA model

STALE: data-quality-rules.md “GPA 0–4 or 0–100”
  → scale UNRESOLVED (HD-01); not authoritative selection

CONFLICT: Grade uniqueness docs
  AUTHORITATIVE: Phase 3B LIVE partial UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current
  NON-AUTHORITATIVE: UNIQUE(exam_session_id, student_id)
  → documentation cleanup FUTURE; no DDL in this phase

STALE RISK: treating blueprint promotion.min_gpa as approved GPA scale
  → consumer expectation only; formula still HDR
```

---

## Risks

| Risk | Mitigation |
|------|------------|
| Implement GPA with invented 4.0/credits | Gate + GPA-INV-032 |
| Embed GPA only as annual column (lose versioning) | Separate GpaResultVersion model |
| GPA blocks Annual rebuild from grades | GPA-INV-020 |
| Operational GPA treated as official | GPA-INV-028 |
| Cross-school GPA access | GPA-INV-003/025 |

---

## Conditions

1. No academic HDs silently resolved.  
2. No implementation authorized.  
3. Official GPA **calculation implementation** blocked on P0 HDs above.  
4. Phase 3C.5 requires separate human approval.  
5. Known doc conflicts recorded, not “fixed” via DDL.

---

## Final Gate

Gate criteria verified: GPA derived; grades SSOT; Annual boundary; input dataset; eligibility separation; credit/grade-point/formula/rounding policy-controlled; versioning; lifecycle; ops/official; supersession; provenance; fingerprint; deterministic rebuild; correction propagation; school isolation; CQRS; concurrency/idempotency; failure model; performance blueprint; ranking/transcript boundaries; no implementation; HDs explicit.

```text
PHASE 3C.4 GATE: PASS WITH CONDITIONS

IMPLEMENTATION AUTHORIZATION: NOT GRANTED

DATABASE CHANGES: NONE

MIGRATIONS: NONE

API CHANGES: NONE

UI CHANGES: NONE

APPLICATION CODE CHANGES: NONE

DATABASE WRITES: NONE

NEXT PHASE: PHASE 3C.5 — RANKING ARCHITECTURE

HUMAN APPROVAL REQUIRED BEFORE PHASE 3C.5
```

**STOP.** Do not begin Phase 3C.5. Do not implement GPA. Do not invent formulas.
