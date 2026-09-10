# SIS DATABASE — PHASE 3C.8A  
# HUMAN DECISION WORKSHOP

**Document type:** DECISION PREPARATION ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.8 Gate (`PASS WITH CONDITIONS`)  

```text
ARCHITECTURAL RECOMMENDATION ≠ HUMAN POLICY DECISION ≠ IMPLEMENTATION AUTHORIZATION

HUMAN APPROVAL REQUIRED
NO IMPLEMENTATION AUTHORIZATION
NO DATABASE / MIGRATION / APP / SQL / RLS / SEEDING
NO ACADEMIC POLICY INVENTION
NO AUTOMATIC DECISION CLOSURE
```

---

## 1. Purpose

Transform unresolved HD-19…42 (and DL-017…022 recommendations) into **precise, reviewable decision packs** for explicit human approval.

| This phase does | This phase does NOT |
|-----------------|---------------------|
| Prepare questions, options, consequences | Approve decisions |
| Map impact surfaces & blockers | Implement schema/engines |
| Preserve policy neutrality | Invent thresholds/roles |
| Distinguish workshop complete vs approved | Start Phase 3C.9 |

---

## 2. Status Vocabulary (unchanged)

```text
UNRESOLVED | APPROVED | APPROVED_WITH_CONDITION | REJECTED | DEFERRED | SUPERSEDED | BLOCKED_BY_DEPENDENCY
```

All workshop HDs remain **UNRESOLVED**.  
DL-017…022 remain **UNDECIDED** (recommendation ≠ approval).

---

## 3. Pack Overview

| Pack | Contents | Focus |
|------|----------|-------|
| **P0** | HD-19,20,21,31,32,35,36,39 (+ HD-22 conditional) | Must close before safe implementation path |
| **A** | HD-19,20,21 | Identity & academic scope |
| **B** | HD-31,32,39 | Authority & official outcome |
| **C** | HD-35,36,37 | Correction & historical integrity |
| **D** | HD-22…30,33,34,38,40,41,42 | Remaining / conditional |

Detail: `SIS-DATABASE-PHASE-3C.8A-P0-DECISION-PACK.md`

---

## 4. DL-017…022 Human Approval Sheet

| DL | Current Recommendation | Human Action | Dependencies | Consequence |
|----|------------------------|--------------|--------------|-------------|
| DL-017 | ACCEPT — Completion ≠ Graduation; versioned derived | **UNDECIDED** | HD-19 naming | Separate outcome entities |
| DL-018 | ACCEPT — Never Grade/Results/GPA/Ranking/Transcript SSOT | **UNDECIDED** | DL-001…006 | Consumer-only reads |
| DL-019 | ACCEPT — Official immutable; supersede only | **UNDECIDED** | HD-35/36 *when* | Version lineage |
| DL-020 | ACCEPT — Explicit evidence; missing ≠ satisfied | **UNDECIDED** | HD-20…30 content | EvidenceSet required |
| DL-021 | ACCEPT — GPA/Rank/Promo/Year-close not hidden deps | **UNDECIDED** | HD-22/40/41 | Explicit pins only |
| DL-022 | ACCEPT — StudentStatus::Graduated = projection | **UNDECIDED** | HD-32/35 timing | Award→status, not reverse |

### DL checks

| Check | Result |
|-------|--------|
| Conflict with DL-001…016? | **No** (reinforces) |
| Conflict with GC-INV-001…050? | **No** |
| Creates implementation obligation? | **Yes** — after human ACCEPT + separate impl auth |
| Requires HD first? | DL-019 needs HD-35/36 for *policy when*; DL can still be accepted as immutability lock |

---

## 5. Pack A — Identity & Academic Scope (summary)

**Preserve:** `school_id`, `student_id` (denorm), `enrollment`, `program`, academic context — **never student_id alone**.

| Identity | Affected by |
|----------|-------------|
| Completion identity | HD-19, HD-39 |
| Graduation identity | HD-19, HD-32, HD-39 |
| Requirement identity | HD-20, HD-21 |
| Evaluation identity | HD-20, HD-39 |
| Outcome identity | HD-19, HD-35 (versions) |

**HD-19 Exact question:** Does the institution legally/operationally treat *Completion* and *Graduation* as distinct outcomes requiring separate official records?  

**Architectural recommendation:** YES — keep distinct (DL-017).  
**Human policy decision required:** YES (naming/legal use).  
**Status:** UNRESOLVED  

**HD-20 Exact question:** What framework defines eligibility/completion requirements for a program/enrollment (categories and rules — not invented here)?  

**Architectural recommendation:** Extensible requirement/evidence model; no blueprint `min_gpa`.  
**Human policy decision required:** YES.  
**Status:** UNRESOLVED  

**HD-21 Exact question:** Which academic units are required for completion under the chosen framework?  

**Architectural recommendation:** Typed extensible units (subject/course/module/…).  
**Human policy decision required:** YES — do not invent lists.  
**Status:** UNRESOLVED  

---

## 6. Pack B — Authority & Official Outcome (summary)

Separate always: **evaluation → eligibility → approval → award → publication**.

| HD | Authority question (no invented roles) |
|----|----------------------------------------|
| HD-31 | Who/what may request, approve, reject graduation transitions? |
| HD-32 | What constitutes an official Award (fields/meaning)? |
| HD-39 | May one student hold multiple independent completion/graduation outcomes across enrollments/programs? |

**Architectural recommendation:** Machine may evaluate; humans approve/award unless future policy says otherwise; multi-enrollment scoped identity.  
**Human policy decision required:** YES for roles and award semantics.  
**Do not invent:** role names, committees, permission strings.  
**Status:** all UNRESOLVED  

---

## 7. Pack C — Correction & Historical Integrity (summary)

| HD | Priority | Notes |
|----|----------|-------|
| HD-35 | **P0** | Correction after graduation |
| HD-36 | **P0** | Revocation |
| HD-37 | **P2** | Retention years (duration) |

**Never assume:** correction → automatic revoke.

Test surfaces against existing official outcome:

grade correction · result correction · GPA correction · requirement correction · policy correction · evidence correction  

**Architectural recommendation:** impact analysis → candidate re-eval → supersession lineage; no in-place mutate (DL-019).  
**Human policy decision required:** YES — choose institutional path (leave / supersede / revoke / manual-only).  
**Status:** UNRESOLVED  

---

## 8. Pack D — Remaining (classification check)

| HD | Priority (3C.8A) | Justification |
|----|------------------|---------------|
| HD-22 | **P0 CONDITIONAL** / else P1 | P0 only if HD-20 requires GPA gate |
| HD-23…30 | P1 | Engine semantics after HD-20/21 |
| HD-33,34 | P1 | Date meaning after HD-32 |
| HD-38 | P1 | Blocks publication workflow |
| HD-40 | P1 | Confirm ≠ year closure auto-grad |
| HD-41 | P1 | Confirm Promotion ≠ Graduation |
| HD-42 | **P2** | Packaging; does not block logical schema/engine |

**HD-40/41:** Completion, Graduation, Promotion, Ranking, GPA, Transcript remain distinct unless human changes.  
**HD-42:** Does **not** block logical schema, physical schema, engine, CQRS, or approval — packaging only.

---

## 9. Policy Invention Defense

| Pattern found | Classification |
|---------------|----------------|
| Blueprint `min_gpa` / credits / subjects | **STALE DOCUMENTATION** |
| GC-INV / DL “must not” / fail-closed | **ARCHITECTURAL INVARIANT** |
| “eligible if GPA ≥ …” in sketches | **STALE / UNRESOLVED** — not policy |
| 3C.7 “no automatic graduation” | **ARCHITECTURAL INVARIANT** (default) |
| Workshop option texts | **EXAMPLE options for human** — not approved |

Nothing non-authoritative may become implementation behavior.

---

## 10. Stale Documentation (recommend markers — do not edit)

```text
database-blueprint.md graduation.eligibility_rules / graduation.records
promotion.rules.min_gpa (as graduation)
PHASE-D graduation tables
certificates.graduation_id
→ STALE / NON-AUTHORITATIVE / SUPERSEDED SKETCH
```

---

## 11. Companion Artifacts

| File | Role |
|------|------|
| `…-P0-DECISION-PACK.md` | Full P0 forms + options |
| `…-DECISION-IMPACT-MATRIX.md` | Impact surfaces |
| `…-DECISION-DEPENDENCY-GRAPH.md` | Justified dependencies |
| `…-IMPLEMENTATION-READINESS.md` | Per-area readiness |
| `…-GATE.md` | Workshop gate |

---

## 12. Quality Self-Check (pre-gate)

| Test | Result |
|------|--------|
| 1 P0 understandable standalone | YES (P0 pack) |
| 2 Consequences of choices visible | YES |
| 3 Schema vs engine vs workflow blockers proven | YES (impact + readiness) |
| 4 Proceed without inventing policy | YES (slots only) |
| 5 Historical reproducibility after corrections | YES if HD-35/36 closed + DL-019 |
| 6 Multi-enrollment scope | YES if HD-39 closed |
| 7 School isolation fail-closed | YES (design; RLS later) |
| 8 StudentStatus projection | YES if DL-022 accepted |

```text
WORKSHOP COMPLETE ≠ HUMAN DECISIONS APPROVED
```
