# SIS DATABASE — PHASE 3C.1  
# HUMAN DECISION CLOSURE MATRIX

**Document type:** GOVERNANCE  
**Date:** 2026-09-10  
**Source register:** Phase 3C.0 Decision Gate  

```text
No HD marked RESOLVED without explicit human approval in this phase.
```

---

## Status Legend

| Status | Meaning |
|--------|---------|
| **RESOLVED** | Human approved a concrete rule (none in 3C.1) |
| **UNRESOLVED** | Still requires human decision |
| **DEFERRED** | Explicitly postponed to a later phase/packaging |
| **BLOCKED** | Cannot progress a dependent implement phase |

---

## Closure Matrix HD-01…HD-18

| ID | Topic | 3C.0 | 3C.1 Catalog Representation | Status | Blocks |
|----|-------|------|------------------------------|--------|--------|
| HD-01 | GPA scale/formula | HDR | GPA Policy Contract slots | **UNRESOLVED** | 3C.4 GPA engine; GV numeric GPA |
| HD-02 | Letter bands | HDR | Letter Policy band schema (empty values) | **UNRESOLVED** | Letter outputs; GV-10 |
| HD-03 | Weight normalization | HDR | Weighting Policy (sum rule open) | **UNRESOLVED** | Term aggregation official; GV-04/05 |
| HD-04 | Grade-status eligibility | HDR | Eligibility set over LIVE statuses | **UNRESOLVED** | Official calc; distinct from HD-18 |
| HD-05 | Absent/exempt/incomplete calc | HDR | Separated concepts; LIVE absent kept | **UNRESOLVED** | GV-02 treatment |
| HD-06 | Retake selection | HDR | Selection strategy enum slot | **UNRESOLVED** | GV-06 |
| HD-07 | Missing term / incomplete year | HDR | Outcome enum slot | **UNRESOLVED** | Annual finalize; GV-03/08/09 |
| HD-08 | Ranking scopes | HDR | Scope catalog examples only | **UNRESOLVED** | Ranking snapshots |
| HD-09 | Ranking ties | HDR | Tie catalog examples only | **UNRESOLVED** | Ranking |
| HD-10 | Ranking privacy | HDR | Privacy catalog + `results.view_ranking` design | **UNRESOLVED** | Ranking API authz |
| HD-11 | Issued transcript after correction | HDR | A vs B options retained | **UNRESOLVED** | 3D legal behavior; GV-14 |
| HD-12 | Retention / supersession | HDR | Retention **reference** field only | **UNRESOLVED** | 3D ops |
| HD-13 | Operational SLO | HDR | Freshness policy fields (no 1–5 lock) | **UNRESOLVED** | SLO publication |
| HD-14 | Ranking fail vs annual finalize | HDR | Dependency modes in catalog | **UNRESOLVED** | Annual finalize gating |
| HD-15 | Credit hours for GPA v1 | HDR | `credit_hours_required` flag vs schema nullability | **UNRESOLVED** | With HD-01 |
| HD-16 | 3C/3D transcript boundary | Confirm | Catalog preserves 3C design / 3D issue | **DEFERRED** (packaging) — **await CONFIRM** | 3D start; not 3C.2 term schema |
| HD-17 | Term closed semantics | HDR | Future-compatible state names only | **UNRESOLVED** | Finalize automation |
| HD-18 | Dataset eligibility boundary | HDR | Separate Official Result Eligibility contract | **UNRESOLVED** | Official finalize |

---

## Architectural Locks (not HDs)

DL-001…DL-016 remain **PROPOSED / carried as governing direction** from 3C.0 human authorization of 3C.1 work. They are **not** academic formulas. Formal “lock accepted” checkboxes still belong to human sign-off in 3C.0/gate reviews.

---

## Minimum decisions to unlock Phase 3C.2 (Term Results Architecture — design/implement later)

Phase 3C.2 can be **designed** with open HDs, but **implementation** of official term calculation should not proceed without at least:

| Priority | Decisions |
|----------|-----------|
| P0 | HD-04, HD-03, HD-05, HD-06, HD-18 |
| P1 | HD-07, HD-17, Rounding policy |
| Not required for term totals | HD-01/02/08…12 (GPA/letters/ranking/transcript) |

**3C.1 itself does not implement 3C.2.**

---

## Explicit non-resolutions

```text
GPA FORMULA: UNRESOLVED — HUMAN DECISION REQUIRED
LETTER BANDS: UNRESOLVED — HUMAN DECISION REQUIRED
WEIGHT SUM POLICY: UNRESOLVED — HUMAN DECISION REQUIRED
GRADE STATUS ELIGIBILITY SET: UNRESOLVED — HUMAN DECISION REQUIRED
DATASET ELIGIBILITY BOUNDARY: UNRESOLVED — HUMAN DECISION REQUIRED
RETAKE STRATEGY: UNRESOLVED — HUMAN DECISION REQUIRED
RANKING SCOPE/TIES/PRIVACY: UNRESOLVED — HUMAN DECISION REQUIRED
ISSUED TRANSCRIPT AFTER CORRECTION: UNRESOLVED — HUMAN DECISION REQUIRED
```
