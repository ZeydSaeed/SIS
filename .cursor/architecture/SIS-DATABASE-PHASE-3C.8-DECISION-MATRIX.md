# SIS DATABASE — PHASE 3C.8  
# HD-19 … HD-42 DECISION MATRIX

**Document type:** GOVERNANCE  
**Date:** 2026-09-10  

```text
Every HD appears exactly once.
No invented academic answers.
Status vocabulary: APPROVED | APPROVED_WITH_CONDITION | REJECTED | DEFERRED | SUPERSEDED | UNRESOLVED | BLOCKED_BY_DEPENDENCY
```

---

## Matrix

| HD | Topic | Current Status | Priority | Dependency | Blocks Schema? | Blocks Engine? | Blocks App? | Recommendation | Reason |
|----|-------|----------------|----------|------------|----------------|----------------|-------------|----------------|--------|
| HD-19 | Graduation vs Completion semantics | UNRESOLVED | **P0** | Informs DL-017 | **YES** (concept grain) | YES | YES (UX/labels) | Confirm arch distinction; legal naming HUMAN | Wrong collapse → irreversible model |
| HD-20 | Graduation eligibility policy | UNRESOLVED | **P0** | Feeds 21–30 | CONDITIONAL (extensible slots OK) | **YES** | YES | Workshop content; no blueprint defaults | Wrong outcomes |
| HD-21 | Required academic units | UNRESOLVED | **P0** | HD-20 | CONDITIONAL | **YES** | YES | Define unit set under HD-20 | Engine correctness |
| HD-22 | Min achievement/GPA if any | UNRESOLVED | **P0** if GPA-gated else **P1** | HD-20; if GPA: HD-01/15/Rounding | CONDITIONAL | YES if used | YES if used | Only if HD-20 requires GPA | Hidden GPA forbidden (DL-021) |
| HD-23 | Failed-unit policy | UNRESOLVED | **P1** | HD-20/21 | NO (slot) | YES | YES | After HD-20/21 | Eval semantics |
| HD-24 | Incomplete/withdrawn/exempt | UNRESOLVED | **P1** | HD-20; **HD-05** | NO | YES | YES | Align with HD-05; don’t invent | Evidence treatment |
| HD-25 | Repeat/retake | UNRESOLVED | **P1** | HD-20; **HD-06** | NO | YES | YES | Align with HD-06 | Selection semantics |
| HD-26 | Transfer-credit | UNRESOLVED | **P1** | HD-20; **HD-07** | NO | YES | YES | Align with HD-07 | Transfer evidence |
| HD-27 | Practical/internship | UNRESOLVED | **P1** | HD-20 | NO | YES if category used | YES | Optional category | Extension point |
| HD-28 | Capstone/project | UNRESOLVED | **P1** | HD-20 | NO | YES if used | YES | Optional category | Extension point |
| HD-29 | Attendance if any | UNRESOLVED | **P1** | HD-20 | NO | YES if used | YES | Optional; no invented threshold | Extension point |
| HD-30 | Administrative clearance | UNRESOLVED | **P1** | HD-20 | NO | YES if used | YES | Optional | Extension point |
| HD-31 | Approval authority | UNRESOLVED | **P0** | After eval exists | CONDITIONAL (actor slots) | NO (eval) | **YES** (approve) | Define roles — do not invent here | Wrong authority model |
| HD-32 | Award semantics | UNRESOLVED | **P0** | HD-19 | CONDITIONAL | NO (eval) | **YES** (award) | Define award meaning | Award engine |
| HD-33 | Graduation date semantics | UNRESOLVED | **P1** | HD-32 | CONDITIONAL | NO | YES | After award semantics | Historical date meaning |
| HD-34 | Completion date semantics | UNRESOLVED | **P1** | HD-19/32 | CONDITIONAL | NO | YES | Distinct from HD-33 | Date confusion risk |
| HD-35 | Correction after graduation | UNRESOLVED | **P0** | DL-019 | **YES** (supersession) | **YES** | **YES** | **BLOCK impl** until closed | Silent revoke/rewrite risk |
| HD-36 | Revocation policy | UNRESOLVED | **P0** | HD-35 | **YES** | **YES** | **YES** | **BLOCK impl** until closed | Historical integrity |
| HD-37 | Historical retention | UNRESOLVED | **P2** | DL-015 default no hard delete | NO | NO | CONDITIONAL (ops) | Defer years; keep no-hard-delete | Legal duration not structural |
| HD-38 | Publication policy | UNRESOLVED | **P1** | After award | NO | NO | **YES** (publish) | Close before publish workflow | Privacy/visibility |
| HD-39 | Multi-program graduation | UNRESOLVED | **P0** | Identity model | **YES** | YES | YES | Confirm enrollment/program scope | Multi-enrollment integrity |
| HD-40 | vs academic-year closure | UNRESOLVED | **P1** | Calendar | NO | NO | CONDITIONAL | Arch: independent; confirm | Auto-grad on year close |
| HD-41 | vs promotion | UNRESOLVED | **P1** | Promotion module | NO | NO | CONDITIONAL | Arch: separate; confirm | Conflation with promotion.min_gpa |
| HD-42 | 3C/lifecycle packaging | UNRESOLVED | **P2** | Delivery | NO | NO | CONDITIONAL | Defer packaging; monolith OK | Not academic truth |

---

## Priority Rollup

### P0
HD-19, HD-20, HD-21, HD-31, HD-32, HD-35, HD-36, HD-39  
(+ HD-22 when eligibility is GPA-gated)

### P1
HD-23…30, HD-33, HD-34, HD-38, HD-40, HD-41  
(and HD-22 when not GPA-gated / still open)

### P2
HD-37, HD-42  

---

## Prior HDs affecting graduation (not renumbered)

| HD | Effect on graduation path | Priority for grad impl |
|----|---------------------------|------------------------|
| HD-01,15,Rounding | Only if GPA evidence required | P0 **conditional** |
| HD-04…07,18 | Evidence/eligibility inputs | P0 for official eval |
| HD-08…10,14 | Only if ranking coupled | P2 default |
| HD-11,12 | Transcript after award | P1 for transcript ops |
| HD-16 | Transcript packaging | P2 / already DEFERRED |

---

## Independently approvable (no threshold invention)

| HD | Note |
|----|------|
| HD-19 | Confirm conceptual split |
| HD-40 | Confirm independence from year closure |
| HD-41 | Confirm ≠ promotion |
| HD-42 | Packaging naming |
| HD-37 | Can defer duration; not values for calc |

Content pack HD-20…30 should be decided as a **set** after HD-20 framework.
