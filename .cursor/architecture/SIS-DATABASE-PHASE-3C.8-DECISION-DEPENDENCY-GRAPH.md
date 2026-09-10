# SIS DATABASE — PHASE 3C.8  
# DECISION DEPENDENCY GRAPH

**Document type:** GOVERNANCE  
**Date:** 2026-09-10  

```text
NO POLICY INVENTION
```

---

## 1. Legend

```text
SOURCE → DEPENDENCY
REASON
BLOCKING LEVEL: HARD | SOFT | INFORMATIONAL
```

HARD = cannot safely decide/implement dependent without source.  
SOFT = can design slots but cannot finalize behavior.  
INFORMATIONAL = alignment only.

---

## 2. Core Conceptual Chain

```text
DL-001 Grades SSOT
  → DL-018 Graduation/Completion not SSOT
  → Evidence model (DL-020)
  → Completion evaluation
  → Graduation eligibility
  → HD-31 Approval
  → HD-32 Award
  → HD-38 Publication
  → Transcript display (3C.6 / HD-11)
```

| SOURCE | DEPENDENCY | REASON | BLOCKING |
|--------|------------|--------|----------|
| DL-001 | DL-018 | Graduation cannot own grades | HARD |
| DL-006 | Transcript≠Graduation SSOT | Issued artifact is consumer | HARD |
| DL-007/015 | DL-019 | Official immutability/supersession | HARD |
| DL-017 | HD-19 | Legal naming of two concepts | SOFT |
| DL-022 | StudentStatus sync app | Projection direction Award→Status | SOFT |

---

## 3. HD-19…42 Dependency Graph

```text
HD-19 (Completion vs Graduation semantics)
  ↓ HARD (concept grain)
Logical identity of CompletionOutcome vs GraduationAward
  ↓
Schema entities
  ↓
Engine + Award workflow

HD-39 (Multi-program)
  ↓ HARD (identity)
school + enrollment/program scope confirmation
  ↓
Schema PK/business identity
  ↓
Evaluation scope

HD-20 (Eligibility policy)
  ↓ HARD
HD-21 (Required units)
  ↓ HARD / CONDITIONAL
HD-22 (Min achievement/GPA)     ← also SOFT-blocked by HD-01/15 if GPA used
HD-23 (Failed units)
HD-27…30 (optional categories)
  ↓
RequirementDefinition values
  ↓
Evaluation engine

HD-05 ──SOFT──► HD-24 (incomplete/withdrawn/exempt in completion)
HD-06 ──SOFT──► HD-25 (retake in completion)
HD-07 ──SOFT──► HD-26 (transfer in completion)
HD-18 ──SOFT──► Completeness gates inside HD-20

HD-31 (Approval authority)
  ↓ HARD
Approval workflow / CQRS Approve*
  ↓
Who may transition PENDING→APPROVED

HD-32 (Award semantics)
  ↓ HARD
Award schema fields (honors slots etc. — values still HDR)
  ↓
HD-33 Graduation date semantics
HD-34 Completion date semantics
  ↓
Award issuance claims

HD-35 (Correction after graduation)
  ↓ HARD
Supersession / candidate re-eval semantics
  ↓
HD-36 (Revocation)
  ↓ HARD
Correction engine + audit
  ↓
Schema supersession + app correction handlers

HD-40 (vs year closure) ──INFORMATIONAL──► Calendar workflows (default independent)
HD-41 (vs promotion) ──HARD against conflation──► Separate promotion module
HD-38 (Publication) ──SOFT──► Publish commands (after award)
HD-37 (Retention) ──SOFT──► Ops/legal retention (not core calc)
HD-42 (Packaging) ──SOFT──► Delivery phase naming (not academic truth)
HD-16 (3C/3D transcript) ──INFORMATIONAL──► Transcript packaging after award display
```

---

## 4. Upstream Results HDs → Graduation

| SOURCE | DEPENDENCY | REASON | BLOCKING |
|--------|------------|--------|----------|
| HD-04 | Completion evidence eligibility | Which grade statuses count | HARD if grades used as evidence |
| HD-05 | HD-24 | Status treatment reuse | SOFT until HD-20 chooses categories |
| HD-06 | HD-25 | Retake reuse | SOFT |
| HD-07 | HD-26 | Transfer reuse | SOFT |
| HD-01/15/Rounding | HD-22 | Only if GPA-gated eligibility | HARD **if** HD-20 requires GPA |
| HD-08…10,14 | Graduation ranking coupling | Default none | INFORMATIONAL unless policy couples |
| HD-11 | Post-award transcript | Display/reissue | SOFT for graduation award itself |
| HD-18 | Dataset completeness for eval | Official eval readiness | HARD for official finalize |

---

## 5. DL → Schema → Engine → Workflow

```text
ACCEPT DL-017…022 (human)
  ↓
Identity + immutability + evidence fail-closed locked
  ↓
Logical schema design (still needs separate authorization)
  ↓
HD-19 + HD-39 closed
  ↓
Physical schema / migration (separate authorization)
  ↓
HD-20/21 (+ subset 22–30) closed
  ↓
Evaluation engine
  ↓
HD-31 closed
  ↓
Approval workflow
  ↓
HD-32/33/34 closed
  ↓
Award
  ↓
HD-35/36 closed
  ↓
Correction/revocation engine
  ↓
HD-38 closed
  ↓
Publication
```

---

## 6. Parallelizable After Conceptual P0

Once HD-19, HD-39, DL-017…022, HD-35/36 **shape** accepted:

```text
parallel:
  HD-20/21 content workshop
  HD-31 authority workshop
  HD-32–34 award workshop
  HD-40/41 confirm independent/separate
  HD-42 packaging (P2)
```

Threshold workshops must not invent blueprint values.
