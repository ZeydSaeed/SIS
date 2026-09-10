# SIS DATABASE — PHASE 3C.8  
# HUMAN DECISION CLOSURE & DECISION CONSOLIDATION

**Document type:** AUDIT + DECISION-CLOSURE DESIGN ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.7 Gate (`PASS WITH CONDITIONS`)  

```text
HUMAN APPROVAL REQUIRED
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
DATABASE CHANGES: FORBIDDEN
POLICY INVENTION: FORBIDDEN
STOP AFTER REPORT GENERATION
```

---

## 1. Executive Purpose

Close, classify, dependency-order, and govern unresolved human decisions inherited from Phase 3C.7 (and prior 3C.0–3C.6) **without inventing academic rules** and **without authorizing implementation**.

---

## 2. Decision Status Vocabulary (normalized)

| Status | Meaning |
|--------|---------|
| APPROVED | Explicit human approval recorded |
| APPROVED_WITH_CONDITION | Approved with stated conditions |
| REJECTED | Explicitly rejected |
| DEFERRED | Explicitly postponed (not a silent rename of unresolved) |
| SUPERSEDED | Replaced by a later decision |
| UNRESOLVED | Still requires human decision |
| BLOCKED_BY_DEPENDENCY | Cannot be decided until listed dependency closes |

**In this phase:** No HD marked APPROVED. No UNRESOLVED silently converted to DEFERRED.

---

## 3. Current Decision State (summary)

| Set | State |
|-----|-------|
| HD-01…15, HD-17, HD-18, Rounding | **UNRESOLVED** (prior) |
| HD-16 | **DEFERRED** (prior packaging) |
| HD-19…42 | **UNRESOLVED** (3C.7) — none closed |
| DL-001…016 | **Preserved** (prior locks) |
| DL-017…022 | **PROPOSED** — recommendations in DL Consolidation (not auto-approved) |
| GC-INV-001…050 | Design invariants — unchanged this phase |

---

## 4. Primary Answers

### 4.1 Genuinely blocking (graduation/completion path)

**Structural / integrity P0:** HD-19, HD-31, HD-35, HD-36, HD-39  

**Policy-content P0 for official engine:** HD-20, HD-21 (+ HD-22…30 as required by chosen model)  

**Award P0 for award engine:** HD-32 (+ HD-33/34 for date semantics before award issuance claims)

### 4.2 Safe to decide independently (architecturally)

Humans may approve independently of threshold values:

- HD-19 concept distinction (Completion ≠ Graduation)  
- HD-40 independence from year closure (arch recommendation)  
- HD-41 separation from promotion (arch recommendation)  
- HD-42 packaging (delivery naming)  
- DL-017…022 as **architecture locks** (not academic thresholds)

Threshold/content HDs (20–30, 22) are **not** independent of each other.

### 4.3 May safely remain deferred for early logical schema

HD-16 (transcript packaging), HD-37 (retention years), HD-38 (publication audiences), HD-42 (phase naming), Ranking HDs (08–10, 14) **unless** graduation policy couples ranking, UI preferences, reporting MVs.

Logical schema for evidence/versioned outcomes can proceed **only after** human accepts DL-017…022 + identity/correction locks — **still NOT authorized by this phase**.

### 4.4 Must close before…

| Milestone | Must close |
|-----------|------------|
| Logical schema authorization | DL-017…022 acceptance; HD-19; HD-39 identity; HD-35/36 correction model shape |
| Physical schema / migration | Above + HD-31 authority slots; HD-32 award fields shape; requirement extensibility from HD-20/21 shape (not values) |
| Evaluation engine | HD-20, HD-21, relevant HD-22…30, upstream HD-04…07/18 as needed |
| Approval workflow | HD-31 |
| Award issuance | HD-32, HD-33, HD-34 |
| Publication | HD-38 |
| Application StudentStatus sync | Confirm DL-022; timing may need HD-32/35 |
| Full production graduation | All P0 + required P1 |

---

## 5. Critical Domain Audit

| Domain | Finding | Status |
|--------|---------|--------|
| **A Identity** | school + enrollment/program scope — architecturally sufficient; student_id-only rejected | Arch OK; **HD-39** still UNRESOLVED for multi-program edge cases |
| **B Completion vs Graduation** | Distinct in 3C.7; not `is_graduated` | **HD-19** UNRESOLVED for legal naming |
| **C Evidence** | Grades SSOT; missing ≠ satisfied | Covered by DL-018/020; values UNRESOLVED |
| **D GPA** | Not hidden requirement | **HD-22** UNRESOLVED; depends on HD-20 |
| **E Ranking** | Separate by default | No coupling unless future policy |
| **F Promotion** | ≠ Graduation | **HD-41** UNRESOLVED; arch recommends separate |
| **G Transcript** | Consumer only | HD-11/12/16 prior; not graduation SSOT |
| **H Immutability** | Supersede model designed | **DL-019** proposed; **HD-35/36** for correction/revoke |
| **I Corrections** | Impact ≠ auto-revoke | **HD-35/36 P0** — **BLOCK implementation** until closed |
| **J Human approval** | Roles undefined | **HD-31** UNRESOLVED — do not invent roles |
| **K StudentStatus** | Projection | **DL-022** proposed ACCEPT |
| **L School isolation** | Fail-closed design | Implementation later; no RLS this phase |
| **M Versioning** | Requirements/policies/outcomes/approvals versioned in design | Thresholds not invented |
| **N Provenance** | WHAT/WHY/WHEN/SOURCES/POLICY/CALC/EVIDENCE/APPROVER/SUPERSESSION | Sufficient as design; authority actors = HD-31 |

---

## 6. Architectural Recommendations vs Academic Policy

| Item | Type |
|------|------|
| Completion ≠ Graduation; supersession; evidence fail-closed; StudentStatus projection; Promotion ≠ Graduation; Year-closure ≠ Graduation; GPA optional unless pinned | **ARCHITECTURAL RECOMMENDATION** (propose for DL acceptance) |
| Any min GPA/credits/subjects/attendance; who may approve; revoke rules; honors bands; retention years | **ACADEMIC / INSTITUTIONAL POLICY** — **HUMAN DECISION REQUIRED** |

---

## 7. Human Decision Packs (for workshops)

### Pack A — Conceptual locks (no thresholds)

```text
Question: Confirm Completion ≠ Graduation; StudentStatus projection; Promotion separate; Year-closure independent; GPA not automatic.
Why: Prevents irreversible SSOT/identity mistakes.
Options: Accept DL-017…022 + HD-19/40/41 architectural stance | Amend | Reject
Consequences: Enables logical schema authorization request | Blocks | Forces redesign
ARCHITECTURAL RECOMMENDATION: Accept DL-017…022; confirm HD-19/40/41 as independent/separate.
POLICY DECISION: Still required for institutional naming of Completion vs Graduation (HD-19).
```

### Pack B — Correction / revocation (integrity)

```text
Question: After award, how do grade corrections affect graduation? (HD-35/36)
Why: Prevents silent rewrite/revoke.
Options: Leave award + re-evaluate candidate | Supersede award | Manual-only revoke | Other institution rule
ARCHITECTURAL RECOMMENDATION: Impact analysis + new version + no silent mutate (already designed).
POLICY DECISION: HUMAN DECISION REQUIRED — choose institutional path.
```

### Pack C — Eligibility content (thresholds)

```text
Question: What makes a student complete/eligible? (HD-20…30, HD-22)
Why: Engine correctness.
Options: Institution-defined requirement set (no defaults from blueprint)
ARCHITECTURAL RECOMMENDATION: Extensible requirement/evidence model only.
POLICY DECISION: HUMAN DECISION REQUIRED — do not use blueprint min_gpa.
```

### Pack D — Authority & award

```text
Question: Who evaluates/approves/awards/publishes? What is an award? (HD-31…34, HD-38)
ARCHITECTURAL RECOMMENDATION: Separate calculate / approve / award / publish.
POLICY DECISION: HUMAN DECISION REQUIRED — roles and award semantics.
```

---

## 8. Cross-references

| Artifact | Role |
|----------|------|
| `SIS-DATABASE-PHASE-3C.8-DECISION-MATRIX.md` | Full HD-19…42 matrix |
| `SIS-DATABASE-PHASE-3C.8-DECISION-DEPENDENCY-GRAPH.md` | Dependencies |
| `SIS-DATABASE-PHASE-3C.8-DL-CONSOLIDATION.md` | DL-017…022 review |
| `SIS-DATABASE-PHASE-3C.8-IMPLEMENTATION-BLOCKERS.md` | Blocker matrix + closure order |
| `SIS-DATABASE-PHASE-3C.8-CONFLICT-REGISTER.md` | Conflicts + stale protection + invariant coverage |
| `SIS-DATABASE-PHASE-3C.8-GATE.md` | Gate score & STOP |
