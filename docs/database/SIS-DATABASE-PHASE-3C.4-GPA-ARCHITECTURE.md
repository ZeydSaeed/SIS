# SIS DATABASE — PHASE 3C.4  
# GPA ARCHITECTURE

**Document type:** ARCHITECTURE DESIGN ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.3 Gate (`PASS WITH CONDITIONS`)  
**Status:** ARCHITECTURE DESIGNED — academic rule slots remain UNRESOLVED  

```text
NO DDL · NO MIGRATIONS · NO APPLICATION CODE · NO GPA ENGINE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 1. Mission Statement

```text
GPA
```

is a **versioned, derived, auditable, school-isolated academic calculation** produced from a governed GPA Input Dataset. It is **not** Grade SSOT (DL-001) and **must not** become a writable second academic ledger.

### Core principle (LOCKED)

```text
GPA is derived. Grades remain SSOT.
```

### Conceptual dependency (LOCKED — aligned with DL-001…004)

```text
exams.student_grades
        |
        +----------------------+
        |                      |
        v                      v
  Term Results          Annual Results
                               |
                               v
                          GPA Input
                               |
                               v
                          GPA Result
```

**Clarifications (LOCKED):**

1. Annual Results **MAY** provide the primary governed input projection into GPA.  
2. Term Results **MAY** contribute to scoped GPA (e.g. term GPA) when policy allows — without making Term Results SSOT.  
3. GPA rebuild MUST be possible from its **governed GPA Input Dataset** + policy versions + calculation version + rounding policy (when approved).  
4. The GPA Input Dataset MUST remain reconstructible from grades + academic structure + (optional) Annual/Term Result versions used as projections — without creating a sole chain that prevents Annual Result independent rebuild from grades (DL-003).  
5. Outbox is propagation only (DL-009).

**FORBIDDEN:**

```text
Inventing GPA scale, formula, grade points, credits, rounding, retake, transfer, or eligibility rules
Embedding ranking or transcript issuance inside GPA
Silent mutation of official GPA
```

---

## 2. Identity: Business / Version / Storage

### 2.1 Business identity (PROPOSED)

```text
(school_id, enrollment_id, gpa_scope, scope_anchor_id?)
```

| Element | Role |
|---------|------|
| `school_id` | Tenant boundary (DL-011) |
| `enrollment_id` | LIVE enrollment anchor (preferred over bare `student_id`) |
| `gpa_scope` | Discriminator: term / academic_year / cumulative / program / … |
| `scope_anchor_id` | Optional: `term_id`, `academic_year_id`, `program_id`, etc. when required by scope |

`student_id` may be denormalized for query convenience; it is **not** a sufficient isolation key.

### 2.2 Version identity

```text
business identity + gpa_version
```

Monotonic per business identity. Operational current ≠ official current.

### 2.3 Storage identity

Surrogate / composite PK = FUTURE IMPLEMENTATION. Must not redefine business meaning.

---

## 3. GPA Scope Classification

Do **not** choose which scopes are officially supported for v1. Classify only:

| Scope | Classification | Notes |
|-------|----------------|-------|
| Term GPA | **Architecture-supported** | Needs term anchor; may use Term Results + grades |
| Academic-year GPA | **Architecture-supported** | Primary consumer of Annual Results projection |
| Enrollment / cumulative GPA | **Architecture-supported** | Multi-year; retention/eligibility **HDR** |
| Program GPA | **Policy-dependent / future extension** | Needs program identity authority |
| Graduation GPA | **Policy-dependent / future extension** | Graduation consumer boundary only |
| Transfer-adjusted GPA | **Policy-dependent** | Transfer semantics **HDR** |
| Class/section cohort GPA average | **Explicitly out of GPA Result scope** | Aggregation/reporting concern, not student GPA SSOT |

Official supported set = **HUMAN DECISION REQUIRED** (product/policy).

---

## 4. GPA Input Dataset (critical)

The architecture defines an explicit **GPA Input Dataset** so policy can later decide membership without redesign.

### 4.1 Distinctions (mandatory)

| Concept | Meaning |
|---------|---------|
| **Source identity** | Which academic records were considered (grades / annual / term versions) |
| **Inclusion** | Which records contributed to the numeric GPA |
| **Exclusion** | Which considered records did not contribute |
| **Exclusion reason** | Why excluded (status, incomplete, retake rule, transfer, credits missing, …) — codes **HDR** |
| **Credit value** | Bound credit for the item — semantics **HDR (HD-15)** |
| **Grade-point value** | Interpretation used — mapping **HDR (HD-01/HD-02)** |
| **Policy version** | Which policy authorized inclusion/mapping/credits |

### 4.2 Potential sources (architecture-supported; selection HDR)

- Official (or operational, for ops GPA) Annual Result versions  
- Term Result versions (especially term-scoped GPA)  
- Underlying `exams.student_grades` (for reconstruction / verification / when projection insufficient)  
- Academic unit metadata (subject/course)  
- Credit definitions (nullable LIVE `subjects.credit_hours` is **schema presence only**, not policy approval)  
- Transfer / external credit records (if/when exist — FUTURE)  
- Eligibility outcomes (HD-18 / HD-04 / HD-05 / HD-06 / HD-07)

### 4.3 Reconstructibility

```text
GPA Input Dataset
  ← reconstructible from:
       grades SSOT
     + academic structure
     + enrollment context
     + Annual/Term Result versions referenced (if used)
     + policy / calculation / rounding versions
```

Annual Results remaining independently rebuildable from grades is **preserved** (DL-003). GPA must not force Annual to depend on GPA.

---

## 5. Annual Result → GPA Boundary

```text
Annual Result (versioned)
      ↓
GPA Input Projection (versioned logical set)
      ↓
GPA Calculation (calculation_version + policies)
      ↓
GPA Version
```

| Rule | Status |
|------|--------|
| Annual FINALIZED ≠ GPA FINALIZED | **LOCKED** |
| GPA FINALIZED must not mutate Annual Results | **LOCKED** |
| Not every Annual change implies GPA change | Impact detection required |
| Projection design only — no implementation | This phase |

---

## 6. Eligibility Model

Eligibility is **separate** from existence, validity, completeness, and contribution.

| Layer | Question | HD |
|-------|----------|-----|
| Record exists | Is there a candidate academic unit/result/grade? | Structure |
| Record valid | Passes technical integrity checks? | Technical |
| Record complete | Dataset completeness for scope? | HD-18 / HD-07 |
| Academically eligible | May participate under policy? | HD-04/05/06/07/18 |
| Contributes to GPA | Included in numerator/denominator after eligibility? | Policy (HD-01/15) |

Do not collapse these. Values = **HUMAN DECISION REQUIRED**.

---

## 7. Credit-Hour Boundary (HD-15)

```text
Academic Unit
    ↓
Credit Definition (source, value, effective context)
    ↓
GPA Input Item
    ↓
GPA Calculation
```

Architecture supports binding:

- credit value  
- credit source (subject metadata, override, transfer, …)  
- credit version / effective context  
- credit inclusion/exclusion  
- provenance  

**Does NOT choose:** fixed vs variable, attempted vs earned, contact/practical/vocational hours.

LIVE evidence: `curriculum.subjects.credit_hours` is **nullable** — schema optionality ≠ policy mandate (HD-15 UNRESOLVED).

---

## 8. Grade → Grade Point Boundary (HD-01 / HD-02)

```text
Grade / Annual Outcome
        ↓
Grade Point Policy (immutable published version)
        ↓
Grade Point
        ↓
GPA Calculation
```

No invented conversion tables, letter bands, or point scales.

---

## 9. Formula Boundary (HD-01)

Technology-neutral contract only (see Calculation Contract artifact).

**PROHIBITED in this phase:** weighted/unweighted formulas, 4.0/5.0/percentage scales, vocational custom scales as “chosen.”

Placeholder:

```text
GPA_VALUE = F(eligible_items, credits, grade_points, rounding_policy, calculation_version)
```

`F` = **HUMAN DECISION REQUIRED**.

---

## 10. Rounding Boundary

Rounding is an explicit policy dependency. Fingerprint MUST include effective rounding policy once approved. Mode/precision = **HUMAN DECISION REQUIRED**.

---

## 11. Retake / Transfer / Status Boundaries

| Concern | Architecture support | Policy |
|---------|----------------------|--------|
| Retake/repeat (first/latest/highest/average/exclude/replace) | Provenance of which attempt included + why | **HD-06 HDR** |
| Absent/exempt/withdrawn/incomplete | Eligibility / mapping / credit / block slots | **HD-05 HDR** |
| Transfer credits/grades/equivalency | Source + inclusion + provenance slots | **HDR** (no invented transfer policy) |

Preserve LIVE grade rule: `is_absent ⇒ score NULL`; absent ≠ zero (semantics for GPA still HDR).

---

## 12. Lifecycle / Operational vs Official

See Lifecycle artifact.

```text
NOT_CALCULATED → CALCULATED → FINALIZED → SUPERSEDED
```

GPA FINALIZED does **not** imply ranking finalized, transcript issued, graduation approved, or academic year closed.

---

## 13. Versioning Metadata (official)

Required on official GPA versions (DL-008 / DL-014):

- `gpa_version` (monotonic)  
- policy version pins (GPA, grade-point, eligibility, retake, transfer, rounding as used)  
- `calculation_version`  
- `source_fingerprint`  
- `calculated_at`  
- actor  
- correlation_id / causation_id  
- supersession lineage  

No silent mutation (DL-007 / DL-015).

---

## 14. Rebuild & Propagation (summary)

Canonical rebuild:

```text
GPA Input Dataset → Policy Resolution → Calculation Version
  → Deterministic Calculation → Fingerprint → New GPA Version
```

Does **not** require previous GPA row (history only).

Grade correction → outbox → impact detection → reconstruct inputs → new version → supersede prior official if finalized anew.

Annual Result v1→v2 → GPA impact evaluation → new GPA version **if required**.

Details: Rebuild artifact.

---

## 15. School Isolation

```text
SchoolContext → GPA command/query → enrollment → year/program/scope
  → GPA Input Dataset → GPA Result → composite integrity → RLS → FORCE RLS
```

Ambiguous/missing school scope ⇒ **fail closed**. No unrestricted fallback; no school inference from arbitrary IDs; no RLS/FORCE RLS bypass.

---

## 16. CQRS Contracts (conceptual)

**Commands:** `CalculateGPA`, `RebuildGPA`, `FinalizeGPA`, `SupersedeGPA`  
**Queries:** `GetCurrentGPA`, `GetOfficialGPA`, `GetGPAHistory`, `GetGPAProvenance`, `GetGPALineage`, `ExplainGPAInputs`

No handlers/APIs/repos in this phase.

---

## 17. Concurrency & Idempotency

Protect: concurrent calc/finalize, duplicate outbox/rebuild, stale workers, concurrent supersession.

| Mechanism | Role |
|-----------|------|
| Serialize per GPA business identity | FUTURE IMPLEMENTATION |
| Fingerprint equivalence | Official no-op when identical inputs |
| Optimistic concurrency on official current | FUTURE — fingerprint alone insufficient |
| Correlation / causation | Provenance |

---

## 18. Performance Blueprint (no indexes/partitions)

Access patterns: current/official GPA; history; by enrollment/student; by year; by program (if scoped); rebuild; provenance; impact analysis.

```text
NO PARTITIONING INITIALLY (DL-010)
```

Revisit with evidence: row growth, year-scoped latency, vacuum/index bloat, rebuild cost vs SLO (HD-13 unresolved).

---

## 19. Failure Model (classes)

| Scenario | Class |
|----------|-------|
| No / incomplete GPA input | Blocking or ACADEMIC per HD-18 — **HDR** for academic outcome |
| Ineligible / conflicting eligibility | ACADEMIC — **HDR** |
| Missing/invalid credit | Blocking until HD-15 / data repair |
| Missing/conflicting grade-point mapping | Blocking — HD-01/02 |
| Missing/incompatible policy | Blocking — no invent |
| Missing calculation version | Blocking technical |
| Fingerprint / deterministic mismatch | Blocking / human-review |
| Retake / transfer / withdrawal / incomplete ambiguity | Human-review / HDR |
| Concurrent calculation | Retryable / serialize |
| Duplicate event | Idempotent |
| Stale operational GPA | Non-blocking until finalize |
| Official recalculation | New version + supersession — never overwrite |
| School-context failure | **Security failure — fail closed** |

---

## 20. Security Failure Model

Missing SchoolContext, ambiguous school, failed relational school boundary, cross-school source, unverifiable RLS assumptions ⇒ **fail closed**. Never bypass RLS/FORCE RLS.

---

## 21. Downstream Boundaries

### Ranking (Phase 3C.5)

```text
GPA → Ranking Input Contract → Ranking Engine
```

No scope/ties/privacy invented (HD-08/09/10). Ranking ≠ academic truth (DL-005).

### Transcript

```text
GPA → Transcript Projection / Consumer
```

Issuance/retention/correction behavior **HDR (HD-11/12)**; packaging Phase 3D (HD-16).

### Graduation / promotion

Blueprint consumers (`promotion.rules.min_gpa`, certificates) may **read** official GPA as input. Graduation eligibility policy **not defined** here.

---

## 22. Stale Blueprint Conflict (GPA)

```text
STALE: database-blueprint.md results.annual_results.gpa / total_credits
       and UNIQUE(enrollment_id) sketch — incomplete vs versioned GPA + school_id + pins

AUTHORITATIVE FOR DESIGN: Phase 3C.0–3C.4 logical model
  — GPA is a separate versioned derived Result (DL-004), not a silent column-only concept

STALE: data-quality-rules.md “GPA 0–4 or 0–100” — scale UNRESOLVED (HD-01)

KNOWN CONFLICT (grades): Phase 3B LIVE partial UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current
  vs older UNIQUE(exam_session_id, student_id) — LIVE wins; doc cleanup FUTURE
```

Do not invent corrective DDL in this phase.

---

## 23. Traceability (summary)

| Item | 3C.4 status |
|------|-------------|
| DL-001…DL-016 | **Preserved** |
| HD-01, HD-02, HD-15, Rounding | **Unresolved — P0 for GPA implementation** |
| HD-03…HD-07, HD-18 | **Unresolved — P0 for eligible input construction** |
| HD-08…HD-12, HD-14 | Ranking/transcript — boundary only |
| HD-13 | SLO — unresolved |
| HD-16 | Deferred (3C/3D) |
| HD-17 | Calendar — unresolved dependency for term-scoped GPA |

---

## 24. Architectural Q&A Gate

| Check | Answer |
|-------|--------|
| GPA derived; grades SSOT? | **YES** |
| Annual → GPA boundary explicit? | **YES** |
| Input dataset / eligibility / credits / points modeled? | **YES** (slots; values HDR) |
| Formula/rounding invented? | **NO** |
| Versioning / lifecycle / fingerprint / rebuild? | **YES** |
| Ranking/transcript separated? | **YES** |
| Implementation performed? | **NO** |
