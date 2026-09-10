# SIS DATABASE — PHASE 3C.3  
# ANNUAL RESULT REBUILD ARCHITECTURE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO JOBS · NO CODE · NO DDL
```

---

## 1. Canonical Rebuild Flow

```text
grades SSOT
     ↓
academic structure (year, terms, exams, enrollment, subject)
     ↓
annual source-set resolution
     ↓
completeness evaluation (structure + dataset)     ← rules HDR
     ↓
eligibility evaluation (HD-18)                    ← rules HDR
     ↓
policy resolution (published/effective pins)
     ↓
calculation version
     ↓
deterministic calculation                        ← formulas HDR
     ↓
fingerprint
     ↓
new Annual Result version
     ↓
operational and/or official state
```

**Does NOT require:** existing Annual Result row, stored Term Results as sole input, processed outbox history.

**MAY use:** Term Results as optional consistency/reference optimization.

---

## 2. Authoritative Input Bundle

```text
exams.student_grades
+ academic structure
+ enrollment context
+ applicable immutable policy versions
+ calculation version
+ eligibility / completeness inputs
(+ rounding policy when approved)
```

---

## 3. Source-Set Resolution

Resolve contributing grades for:

```text
(school_id, enrollment_id, academic_year_id, subject_id)
```

via LIVE grade denorm + exam→term graph.

Apply (when approved): retake selection (HD-06), status eligibility (HD-04), absence/missing treatments (HD-05). Until approved: **official** rebuild refuses with policy-missing — do not invent.

Record each included/excluded grade identity in AnnualResultSource semantics.

---

## 4. Fingerprint (logical coverage)

Must cover material inputs, including as applicable:

- contributing grade identities + year  
- grade versions/current/status/score/max/absence  
- inclusion/exclusion  
- academic year + term identities involved  
- enrollment/school/subject structure  
- policy version ids  
- calculation version  
- eligibility / completeness outcomes  
- rounding policy version (when exists)  

Cryptographic algorithm = FUTURE IMPLEMENTATION REQUIREMENT.

---

## 5. Deterministic Equivalence (DL-016)

Same input bundle ⇒ logically equivalent Annual Result (semantics, contributors, pins, eligibility outcomes, stable ordering).

Mismatch classes: calculation drift · policy drift · source drift · implementation defect · non-deterministic dependency.

---

## 6. Grade Correction Propagation

```text
Grade correction (VOID+INSERT)
      ↓
outbox event (trigger only)
      ↓
identify affected Annual business identities
      ↓
source-set reconstruction from grades
      ↓
deterministic recalculation
      ↓
new version (ops; official via finalize)
      ↓
prior official SUPERSEDED when new official exists
```

**Affected identity detection (conceptual):** by enrollment + academic_year + subject (and school) of corrected grade.

---

## 7. Modes

| Mode | Purpose |
|------|---------|
| Operational refresh | CALCULATED current |
| Official finalize | FINALIZED + supersede prior official if needed |
| Verification rebuild | Compare fingerprint/semantics; alert on drift — no silent overwrite |

---

## 8. Idempotency

Identical fingerprint + same policy/calc versions + same official intent ⇒ **no uncontrolled new official current**.

---

## 9. Concurrency

Serialize per business identity (FUTURE IMPLEMENTATION). Concurrent finalize → one winner + conflict/idempotent loser. Stale workers must re-read grades before write.

---

## 10. Calculation Contract (technology-neutral)

**Input:** source grades, structure, enrollment context, policy versions, calculation version, eligibility/completeness state  

**Output:** Annual Result projection, metadata, fingerprint, eligibility/completeness metadata, audit metadata  

**Properties:** deterministic · version-pinned · reproducible · independently rebuildable  

**No executable code in this phase.**
