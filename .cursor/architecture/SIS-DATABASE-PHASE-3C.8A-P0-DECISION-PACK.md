# SIS DATABASE — PHASE 3C.8A  
# P0 DECISION PACK

**Document type:** DECISION PREPARATION ONLY  
**Date:** 2026-09-10  

```text
All Decision Status = UNRESOLVED
Architectural Recommendation ≠ Approval
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

## P0 Block Surface Summary

| HD | Logical Schema | Physical Schema | Evaluation Engine | Approval | Publication | Historical Integrity |
|----|----------------|-----------------|-------------------|----------|-------------|----------------------|
| HD-19 | YES | YES | YES | CONDITIONAL | NO | CONDITIONAL |
| HD-20 | CONDITIONAL | CONDITIONAL | YES | NO | NO | CONDITIONAL |
| HD-21 | CONDITIONAL | CONDITIONAL | YES | NO | NO | CONDITIONAL |
| HD-22* | CONDITIONAL | CONDITIONAL | YES if used | NO | NO | CONDITIONAL |
| HD-31 | CONDITIONAL | CONDITIONAL | NO | YES | CONDITIONAL | NO |
| HD-32 | CONDITIONAL | CONDITIONAL | NO | CONDITIONAL | CONDITIONAL | CONDITIONAL |
| HD-35 | YES | YES | YES | YES | CONDITIONAL | YES |
| HD-36 | YES | YES | YES | YES | CONDITIONAL | YES |
| HD-39 | YES | YES | YES | CONDITIONAL | NO | CONDITIONAL |

\*HD-22 is **P0 only if** HD-20 adopts a GPA gate; otherwise P1 / may remain open longer.

---

# HD-19 — Completion vs Graduation Semantics

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** DOMAIN, IDENTITY, LOGICAL_SCHEMA, PHYSICAL_SCHEMA, AWARD, TRANSCRIPT, STUDENT_STATUS, AUDIT, PROVENANCE  

**Exact Decision Question:**  
Must the institution maintain **Completion** and **Graduation** as distinct official concepts (separate outcomes/records), rather than a single “graduated” flag?

**Why This Decision Exists:** Collapsing them into `is_graduated` / StudentStatus alone destroys eligibility vs award provenance and multi-step workflows.

**Authoritative Evidence:** Phase 3C.7 Architecture/Lifecycle; DL-017 recommendation; GC-INV-006/007; LIVE `StudentStatus::Graduated` is projection-only candidate (DL-022).

**Existing Architectural Constraints:** Grades remain SSOT; outcomes are derived; StudentStatus must not become SSOT.

**Dependencies:** Informs DL-017, HD-32–34.  
**Blocks:** Identity model, logical/physical schema grain, award vs completion engines, UX labels.

| Consequence type | If distinct (recommended) | If collapsed |
|------------------|---------------------------|--------------|
| Schema | Separate CompletionOutcome / GraduationAward | Single flag/row risk |
| Engine | Eval → complete → approve → award | One-shot boolean |
| Application | Multi-step UI/API | Oversimplified status |
| Security | Separate approve/award authz | Coarse permission |
| Historical | Version each concept | Irreversible loss of nuance |
| Audit | Clear provenance per stage | Opaque |

**Option A:** Distinct Completion and Graduation official concepts.  
Advantages: Matches 3C.7; supports pending approval; audit clarity.  
Risks: More entities/workflows.  
Architectural consequences: Aligns DL-017/022.

**Option B:** Single official “graduated” concept only.  
Advantages: Simpler.  
Risks: Loses completion-without-award; conflicts GC-INV-006; StudentStatus becomes de-facto SSOT.  
Architectural consequences: Requires rewriting 3C.7 model; **high risk**.

**Architectural Recommendation:** Option A.  
**Human Policy Decision Required:** YES (legal/operational naming).  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-19
Question: Maintain Completion ≠ Graduation as distinct official concepts?
Architectural Recommendation: Option A (distinct)
Human Decision: [UNRESOLVED]
If APPROVED (A): Proceed with dual-outcome identity in future schema auth.
If REJECTED (choose B): Reopen 3C.7 model — BLOCK dual-entity schema.
If DEFERRED: Logical schema for graduation/completion remains BLOCKED.
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-20 — Graduation Eligibility Policy Framework

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** DOMAIN, POLICY, EVALUATION_ENGINE, LOGICAL_SCHEMA, PROVENANCE, HISTORICAL_INTEGRITY  

**Exact Decision Question:**  
What **policy framework** defines when an enrollment/program is academically eligible/complete (requirement categories and rules)?

**Why:** Engine cannot compute official eligibility without a versioned policy; blueprint thresholds are not policy.

**Evidence:** 3C.7 requirement model; HD register; blueprint `min_gpa` = STALE.

**Constraints:** DL-020 evidence fail-closed; DL-021 no hidden GPA; no invented thresholds.

**Dependencies:** Unlocks HD-21…30 shape; may unlock HD-22.  
**Blocks:** Evaluation engine (hard); schema only for which requirement types exist (conditional extensible slots OK).

**Option A:** Human publishes a versioned eligibility policy catalog (institution-defined rules).  
Advantages: Reproducible, pinned.  
Risks: Requires governance process.  
Consequences: RequirementDefinition versions drive engine.

**Option B:** Defer all eligibility content indefinitely.  
Advantages: No wrong rules.  
Risks: Engine blocked forever.  
Consequences: No official completion evaluation.

**Do not offer “use blueprint min_gpa”** — that invents policy from stale docs.

**Architectural Recommendation:** Option A process; **content values = HUMAN**.  
**Human Policy Decision Required:** YES.  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-20
Question: Adopt versioned eligibility policy framework (content to be supplied by institution)?
Architectural Recommendation: Extensible evidence-based framework; reject blueprint defaults
Human Decision: [UNRESOLVED]
If APPROVED: Enable engine design against published policy versions
If REJECTED/no framework: Evaluation engine remains BLOCKED
If DEFERRED: Engine BLOCKED; extensible schema slots may still be designed later under separate auth
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-21 — Required Academic Units

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** DOMAIN, POLICY, EVALUATION_ENGINE, IDENTITY, LOGICAL_SCHEMA  

**Exact Decision Question:**  
Which academic unit types/sets are **required** for completion under the HD-20 framework (subjects/courses/modules/competencies/…)?

**Why:** Evaluation evidence targets must be known.

**Evidence:** 3C.7 extensibility; LIVE subject-based anchor.

**Constraints:** Do not invent unit lists; preserve extensibility; typed evidence refs.

**Dependencies:** HD-20.  
**Blocks:** Engine; conditional schema for requirement rows.

**Option A:** Institution-defined required unit set (versioned).  
**Option B:** Leave undefined → engine blocked for unit-based rules.

**Architectural Recommendation:** Option A after HD-20; subject-based v1 possible **only if human chooses**.  
**Human Policy Decision Required:** YES.  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-21
Question: Define required academic units for completion under HD-20?
Architectural Recommendation: Versioned institution-defined set; extensible types
Human Decision: [UNRESOLVED]
If APPROVED: Engine can evaluate unit evidence
If REJECTED/empty: Unit-based completion BLOCKED
If DEFERRED: Engine BLOCKED for unit requirements
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-22 — Minimum Achievement / GPA (CONDITIONAL P0)

**Current Status:** UNRESOLVED  
**Priority:** P0 **only if** HD-20 requires GPA/min achievement; else P1  

**Impact Surface:** POLICY, EVALUATION_ENGINE, (GPA), PROVENANCE  

**Exact Decision Question:**  
Does completion/graduation eligibility require a minimum achievement metric (e.g. GPA), and if so what rule?

**Why:** Prevents hidden GPA dependency (DL-021); blueprint `min_gpa` is not approved.

**Dependencies:** HD-20; if YES → HD-01, HD-15, Rounding.

**Option A:** No GPA/min-achievement gate for graduation eligibility.  
**Option B:** Explicit GPA (or other metric) gate — **values HUMAN**; pin GPA version.  
**Option C:** Not offered from blueprint defaults.

**Architectural Recommendation:** Do not assume GPA required; if chosen, explicit pin only.  
**Human Policy Decision Required:** YES.  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-22
Question: Is a minimum achievement/GPA required for eligibility? If yes, under what approved rule?
Architectural Recommendation: Do not invent; default architecture allows either with explicit pin
Human Decision: [UNRESOLVED]
If APPROVED A: No GPA gate in engine
If APPROVED B: Engine blocked until HD-01/15/Rounding closed
If DEFERRED: Treat as BLOCKED_BY_DEPENDENCY on HD-20 until framework known
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-31 — Graduation Approval Authority

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** APPROVAL, CQRS, SECURITY, AUDIT, DOMAIN  

**Exact Decision Question:**  
Who (roles/bodies/systems) may **request**, **approve**, **reject**, and (if allowed) **override** graduation approval transitions?

**Why:** Official award without defined authority is a security/governance failure.

**Evidence:** 3C.7 human workflow; no authoritative role matrix found for graduation.

**Constraints:** **Do not invent** role names, committees, or permissions. Machine evaluation ≠ approval.

**Dependencies:** After evaluation exists.  
**Blocks:** Approval CQRS/workflow; security authz; not evaluation engine.

**Option A:** Human-only approval (machine evaluates only).  
**Option B:** Institution-defined hybrid (human + limited system) — **details HUMAN**.  
**Option C:** Fully automatic approval — **conflicts** default GC-INV-048 unless human explicitly accepts risk.

**Architectural Recommendation:** Option A as default architecture; Option B only with explicit policy. Avoid C.  
**Human Policy Decision Required:** YES.  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-31
Question: Define approval authority model for graduation transitions (without inventing roles here — supply institution roles).
Architectural Recommendation: Prefer human approval after machine evaluation
Human Decision: [UNRESOLVED]
If APPROVED: Implement Approve* under named authorities later
If REJECTED/unset: Approval workflow BLOCKED
If DEFERRED: Approval BLOCKED; eval schema may proceed under other closures
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-32 — Graduation Award Semantics

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** AWARD, DOMAIN, LOGICAL_SCHEMA, TRANSCRIPT, STUDENT_STATUS, AUDIT  

**Exact Decision Question:**  
What constitutes an official **Graduation Award** (identifiers, honors/classification slots, relationship to Completion)?

**Why:** Award ≠ eligibility ≠ approval.

**Dependencies:** HD-19.  
**Blocks:** Award schema/fields; certificate linkage later; not eval engine.

**Option A:** Award is a separate official record after approval, distinct from Completion.  
**Option B:** Award conflated with approval only (no separate award entity) — weaker provenance.  
**Option C:** Award = StudentStatus flip only — **rejected by architecture** (DL-022).

**Architectural Recommendation:** Option A.  
**Human Policy Decision Required:** YES (honors/numbering meaning).  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-32
Question: Confirm Award as separate official recognition after approval? Define award semantics.
Architectural Recommendation: Option A — separate Award
Human Decision: [UNRESOLVED]
If APPROVED A: Award entity in future schema
If REJECTED toward C: Conflicts DL-022 — BLOCK
If DEFERRED: Award issuance BLOCKED
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-35 — Correction After Graduation

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** CORRECTION, HISTORICAL_INTEGRITY, LOGICAL_SCHEMA, EVALUATION_ENGINE, CQRS, AUDIT, PROVENANCE, TRANSCRIPT, STUDENT_STATUS  

**Exact Decision Question:**  
When upstream academic evidence changes after an official graduation/completion outcome, what must happen?

**Why:** Prevents silent rewrite/revoke; blocks implementation until answered (3C.8).

**Test against official outcome:** grade · Term/Annual · GPA · requirement · policy · evidence corrections.

**Constraints:** No in-place mutate of official rows (DL-019); no assumed auto-revoke; outbox impact analysis required.

**Dependencies:** DL-019; pairs with HD-36.  
**Blocks:** Schema supersession semantics, correction engine, approval/award correction paths, historical integrity — **YES**.

**Option A:** Keep prior official outcome; create new evaluation candidate; reissue/supersede only with explicit human action.  
**Option B:** Automatic supersession of official outcome on material impact (still no in-place mutate).  
**Option C:** Automatic revoke on any related grade correction — **high legal risk**; only if human explicitly chooses.

**Architectural Recommendation:** Option A (aligns fail-closed governance).  
**Human Policy Decision Required:** YES.  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-35
Question: After official graduation/completion, how do upstream corrections affect the outcome?
Architectural Recommendation: Option A — impact + candidate + human-controlled supersession
Human Decision: [UNRESOLVED]
If APPROVED: Implement correction path under chosen option
If REJECTED without alternative: Correction engine BLOCKED
If DEFERRED: Official outcome implementation BLOCKED (historical integrity)
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-36 — Revocation Policy

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** CORRECTION, HISTORICAL_INTEGRITY, APPROVAL, AWARD, AUDIT, SECURITY, STUDENT_STATUS  

**Exact Decision Question:**  
When and how may an official graduation/completion award be **revoked** or marked invalid (authority, reasons, lineage)?

**Why:** Distinct from correction supersession; silent revoke forbidden.

**Dependencies:** HD-35.  
**Blocks:** Same as HD-35 for revoke paths.

**Option A:** Revocation allowed only via authorized human action + SUPERSEDED/REVOKED lineage.  
**Option B:** Revocation never allowed (corrections via superseding awards only).  
**Option C:** System auto-revoke on rules — only if human defines rules (not invented here).

**Architectural Recommendation:** Option A or B; avoid silent/auto without policy.  
**Human Policy Decision Required:** YES.  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-36
Question: Is revocation allowed, under what authority, and how is lineage preserved?
Architectural Recommendation: Authorized human revoke with lineage OR supersession-only (A/B)
Human Decision: [UNRESOLVED]
If APPROVED: Encode revoke/supersede transitions
If DEFERRED: Historical integrity path BLOCKED
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```

---

# HD-39 — Multi-Program Graduation

**Current Status:** UNRESOLVED  
**Priority:** P0  
**Impact Surface:** IDENTITY, DOMAIN, LOGICAL_SCHEMA, PHYSICAL_SCHEMA, EVALUATION_ENGINE, SECURITY, RLS  

**Exact Decision Question:**  
May a student have **multiple independent** completion/graduation outcomes (per enrollment/program/school context), and what is the authoritative scope key?

**Why:** student_id-only identity fails multi-enrollment SIS.

**Evidence:** 3C.7 identity axes; enrollment architecture; GC-INV-030.

**Constraints:** Include `school_id`; prefer enrollment/program scope; fail-closed tenancy.

**Dependencies:** Identity model.  
**Blocks:** Logical/physical schema identity — YES.

**Option A:** Outcomes scoped by school + enrollment (+ program context as needed); multiple allowed.  
**Option B:** One graduation per student lifetime globally — conflicts multi-program SIS needs.  
**Option C:** One per school only (ignore enrollment) — weak for concurrent programs.

**Architectural Recommendation:** Option A.  
**Human Policy Decision Required:** YES (especially concurrent programs).  
**Decision Status:** UNRESOLVED  

### Human Decision Form
```text
HD-ID: HD-39
Question: Confirm multi-enrollment/program scoped outcomes (school + enrollment/program)?
Architectural Recommendation: Option A
Human Decision: [UNRESOLVED]
If APPROVED A: Lock business identity for schema
If APPROVED B/C: Redesign identity — high risk
If DEFERRED: Schema identity BLOCKED
Approver: NOT SPECIFIED — HUMAN INPUT REQUIRED
```
