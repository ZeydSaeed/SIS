# SIS DATABASE — PHASE 3C.7  
# GRADUATION / COMPLETION DATA MODEL (LOGICAL)

**Document type:** LOGICAL MODEL — NOT DDL  
**Date:** 2026-09-10  

```text
LOGICAL ≠ PHYSICAL TABLE
NO CREATE TABLE
```

---

## 1. Principle

```text
STALE: blueprint graduation.eligibility_rules / graduation.records
AUTHORITATIVE DESIGN: this model + Phase 3C.0–3C.7
LIVE: graduation schema empty
```

Ownership: Academic Lifecycle module (monolith) consuming Results evidence.  
SSOT for scores: grades. Graduation/completion = derived official outcomes.

Not every concept must become a table.

---

## 2. Candidate Entities

### 2.1 CompletionOutcome (business identity)

| Aspect | Definition |
|--------|------------|
| Purpose | Stable completion identity for an enrollment/program scope |
| Identity (PROPOSED) | school_id + enrollment_id + completion_scope (+ program/curriculum version) |
| Ownership | Lifecycle / graduation module |
| SSOT? | NO |
| Derived? | YES |
| Official? | When finalized |
| Mutable? | Via versions only when official |

### 2.2 CompletionOutcomeVersion

| Aspect | Definition |
|--------|------------|
| Purpose | One evaluation instance |
| Pins | policy_version, calculation_version, source set, fingerprints, calculated_at |
| Lifecycle | operational / official states |
| Audit | Required for official |

### 2.3 RequirementDefinition / RequirementVersion

| Aspect | Definition |
|--------|------------|
| Purpose | Versioned requirement catalog entry |
| Policy lifecycle | DRAFT → PUBLISHED → EFFECTIVE → RETIRED (DL-014 spirit) |
| Values (thresholds) | **HDR — never invent** |
| Mutable published? | NO in place |

### 2.4 RequirementEvaluation

| Aspect | Definition |
|--------|------------|
| Purpose | Result of one requirement against evidence for a CompletionOutcomeVersion |
| Statuses | See Lifecycle candidates |
| Fail closed | Missing mandatory evidence ≠ SATISFIED |

### 2.5 EvidenceSet / EvidenceItem

| Aspect | Definition |
|--------|------------|
| Purpose | Explicit evidence links |
| Identity | Typed `source_type` + `source_id` (+ version where applicable) |
| Sources | Grade, Term/Annual Result versions, GPA version, attendance (if required), admin clearance, etc. — **none mandatory a priori** |
| Integrity | Avoid untyped polymorphism |

### 2.6 EligibilityEvaluation

| Aspect | Definition |
|--------|------------|
| Purpose | Aggregate eligibility distinct from per-requirement rows |
| Related HDs | HD-20… |

### 2.7 GraduationDecision / GraduationApproval

| Aspect | Definition |
|--------|------------|
| Purpose | Institutional decision distinct from calculation |
| Authority | HD-31 |
| Separable from calculation version | **YES — recommended** |

### 2.8 GraduationAward

| Aspect | Definition |
|--------|------------|
| Purpose | Official recognition (number, dates, honors slots) |
| Semantics | HD-32/33 |
| Blueprint honors/final_gpa | Slots only — values HDR |
| school_id | Required |

### 2.9 Supersession / Provenance / SourceSet / SourceItem

Same roles as Results: lineage, actor, correlation, causation, reasons, full source pins. Fingerprint complements — does not replace — provenance.

### 2.10 PolicyVersion / CalculationVersion / Publication

Policy immutability when published; calc pin; publication orthogonal (HD-38).

### 2.11 StudentStatusGraduatedProjection (compatibility)

| Aspect | Definition |
|--------|------------|
| Purpose | Optional sync of `StudentStatus::Graduated` |
| SSOT? | **NO** — projection of award |
| Direction | Award → status update (policy-timed), never reverse as authority |

---

## 3. Relationships

```text
CompletionOutcome 1──* CompletionOutcomeVersion
CompletionOutcomeVersion 1──* RequirementEvaluation
RequirementEvaluation *── EvidenceSet/Items
CompletionOutcomeVersion ── EligibilityEvaluation
CompletionOutcomeVersion 0..1 GraduationApproval / Award chain
Award ── Supersession / Provenance
RequirementVersion ← Policy catalog
```

---

## 4. Identifier Design

- Prefer enrollment-scoped identity + school_id  
- Evidence: strong typing (`source_type` + `source_id` + optional `source_version`)  
- Document integrity implications of any polymorphic refs  
- Forbid ambiguous bare IDs across entity kinds  

---

## 5. Mutability

| Entity | Official | Operational |
|--------|----------|-------------|
| RequirementVersion (published) | Immutable | N/A |
| CompletionOutcomeVersion official | Immutable; supersede | Replaceable |
| Approval/Award | Immutable facts; supersede/revoke per HD | N/A |
| Publication | May change visibility | — |
| StudentStatus | Projection mutable under rules | — |

---

## 6. Retention

Official completion/graduation history retained for audit (HD-37 duration unresolved). Default: no hard delete (DL-015 spirit).
