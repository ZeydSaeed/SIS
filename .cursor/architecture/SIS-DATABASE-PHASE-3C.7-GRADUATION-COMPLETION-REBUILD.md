# SIS DATABASE — PHASE 3C.7  
# GRADUATION / COMPLETION REBUILD & PROVENANCE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO JOBS · NO CODE · NO DDL
```

---

## 1. Deterministic Evaluation Equation

```text
Source Grades (SSOT)
+ Academic Structure
+ Program / Curriculum Version
+ Results Versions (Term / Annual as required)
+ GPA Version (only if required by policy)
+ Requirement Policy Version
+ Eligibility Policy Version
+ Calculation Version
+ Explicit Evidence Set
=
Deterministic Completion Evaluation
```

Optional later: Ranking version **only if** explicit policy requires it (default: exclude).

---

## 2. Rebuild Flow

```text
Resolve SchoolContext + enrollment/program identity
      ↓
Load effective Requirement Policy Version
      ↓
Assemble EvidenceSet (typed sources + versions)
      ↓
Evaluate each requirement → RequirementEvaluation statuses
      ↓
Aggregate Eligibility / Completion outcome
      ↓
Fingerprint + full Provenance trail
      ↓
New CompletionOutcomeVersion (operational or official finalize)
      ↓
Optional approval / award workflow (human)
```

Prior official versions remain historical; differences explainable via source/policy/calc drift.

---

## 3. Provenance Must Answer

- Which enrollment / school / year / program / curriculum version?  
- Which Term/Annual/GPA/grade versions (as applicable)?  
- Which requirement & eligibility policy versions?  
- Which calculation version?  
- Which evidence items and results?  
- Which human approval (actor, time, reason)?  
- Which prior version superseded and why?  

Fingerprint = change/equivalence detector — **not** the entire audit trail.

---

## 4. Correction Propagation

```text
GRADE_CORRECTED (outbox)
  → Term / Annual / GPA impact (existing Results design)
  → COMPLETION impact detection
  → RecalculateCompletion (candidate version)
  → If official award exists: HD-35/36 human action path
  → Transcript impact (3C.6 / HD-11)
  → StudentStatus / certificates projection updates if policy says so
```

**Not assumed:** automatic revoke of graduation.

---

## 5. Concurrency Scenarios

| Scenario | Control |
|----------|---------|
| Two simultaneous evaluations | Serialize per completion identity |
| Repeated evaluation request | Idempotency key |
| Approval racing recalculation | Version conflict detection |
| Grade correction during evaluation | Re-read sources; abort stale finalize |
| Stale approval | Reject; require refresh |
| Policy version change | New evaluations only; history pinned |
| Supersession race | One official current winner |

---

## 6. Completeness Semantics

| State | Meaning |
|-------|---------|
| Missing data | Not evaluated / pending / blocked — **not** satisfied |
| Failed requirement | NOT_SATISFIED |
| Not applicable | N/A |
| Exempt | EXEMPT under policy |
| Pending evidence | PENDING_EVIDENCE |

Fail closed for official finalize/award when mandatory evidence absent.

---

## 7. Modes

| Mode | Purpose |
|------|---------|
| Operational evaluate | Working status |
| Official finalize completion | Governed completion version |
| Request / approve graduation | Human authority |
| Award | Official recognition |
| Verification rebuild | Drift detection — no silent overwrite |
| Publish | Visibility only |

---

## 8. Blockers for Implementation

Official graduation/completion **engine** blocked until human resolves relevant HD-19…HD-42 (especially eligibility, requirements, approval, correction) and upstream Results/GPA readiness for required evidence types.
