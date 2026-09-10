# Phase 3C.15A — Human Policy Decision Register

**Mode:** HUMAN POLICY DECISION CLOSURE ONLY  
**Date:** 2026-09-11  
**Rule:** `Human Decision = NOT PROVIDED` unless an authoritative human approval exists in-repo.

## Predecessor reconciliation

| Location | Finding |
|----------|---------|
| `.cursor/database/phase-3c-10` … `15` | Present |
| `.cursor/database/phase-3c-7/8/8B/9` | Missing as folders |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.8B-*` | Authoritative structural closures |
| `Permission.php` | No graduation permissions |
| New human closure forms for OPEN items since 3C.15 | **None found** |

## Decision entries

### D-3C15A-001 — HD-31 model (no silent auto-approve)

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-001 |
| Policy Domain | HD-31 Authorization |
| Question | Must graduation approval be human-controlled (no silent auto-approve)? |
| Current Evidence | 3C.8B Closure Register HD-31 Option A |
| Current Classification | **LOCKED** |
| Human Decision | APPROVED — OPTION A (prior 3C.8B) |
| Decision Rationale | Official award without human gate forbidden |
| Effective Scope | All Graduation approval transitions |
| Implementation Consequence | Handlers must not auto-approve |
| Test Consequence | Negative test: machine-only approve fails |
| DB Consequence | None |
| Status | **LOCKED** |

### D-3C15A-002 — HD-31-A Who may approve Completion?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-002 |
| Policy Domain | HD-31 |
| Question | Who may approve Completion? |
| Current Evidence | None institutional; roles “not invented” (3C.8B) |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Decision Rationale | — |
| Effective Scope | ApproveCompletion / PublishOfficialCompletion authz |
| Implementation Consequence | **BLOCKED** |
| Test Consequence | Authz matrix incomplete |
| DB Consequence | None |
| Status | **OPEN** |

### D-3C15A-003 — HD-31-B Who may approve Graduation?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-003 |
| Policy Domain | HD-31 |
| Question | Who may approve Graduation? |
| Current Evidence | None |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |
| Implementation Consequence | DecideGraduationApproval **BLOCKED** |

### D-3C15A-004 — HD-31-C Who may issue Award?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-004 |
| Policy Domain | HD-31 / HD-32 |
| Question | Who may issue a Graduation Award? |
| Current Evidence | Award entity LOCKED; issuer roles open |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-005 — HD-31-D Who may revoke Graduation approval?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-005 |
| Policy Domain | HD-31 / HD-36 |
| Question | Who may revoke Graduation approval? |
| Current Evidence | None |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-006 — HD-31-E Who may revoke Award?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-006 |
| Policy Domain | HD-36 |
| Question | Who may revoke a Graduation Award? |
| Current Evidence | Mechanism LOCKED; roles open |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-007 — HD-31-F Role vs permission model

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-007 |
| Policy Domain | HD-31 |
| Question | Role-based, permission-based, or both? |
| Current Evidence | App uses permission strings for Enrollment/Grades; Graduation none |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-008 — HD-31-G Exact permission identifiers

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-008 |
| Policy Domain | HD-31 |
| Question | What exact permission identifiers are institutionally approved? |
| Current Evidence | `Permission.php` has no graduation.* — inventing forbidden |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-009 — HD-31-H Multiple approval required?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-009 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |
| Note | `attempt_no` structural only — not policy |

### D-3C15A-010 — HD-31-I Evaluator ≠ approver?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-010 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-011 — HD-31-J Self-approval prohibited?

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-011 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-012 — HD-31-K/L Delegation

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-012 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-013 — HD-31-M Approval levels

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-013 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-014 — HD-20 framework

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-014 |
| Policy Domain | HD-20 |
| Question | Versioned institution-defined eligibility framework? |
| Current Evidence | 3C.8B HD-20 Option A |
| Current Classification | **LOCKED** |
| Human Decision | APPROVED — OPTION A (prior) |
| Status | **LOCKED** |

### D-3C15A-015 — HD-20/21 evaluation content (all catalogs)

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-015 |
| Policy Domain | HD-20/21 Content |
| Question | Actual courses/units/credits/thresholds/exceptions/evidence catalogs? |
| Current Evidence | Explicitly “content = POLICY INPUT REQUIRED”; blueprint NON-AUTHORITATIVE; HD-22 no default GPA |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |
| Implementation Consequence | EvaluateCompletion **BLOCKED** |

### D-3C15A-016 — HD-22 no default GPA

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-016 |
| Current Classification | **LOCKED** |
| Human Decision | APPROVED — OPTION A (prior) |
| Status | **LOCKED** |

### D-3C15A-017 — HD-39 Graduation SSOT identity

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-017 |
| Question | school_id + enrollment_id identity? |
| Current Classification | **LOCKED** |
| Human Decision | APPROVED — OPTION A (prior) |
| Status | **LOCKED** |

### D-3C15A-018 — SS-MULTI-01..04 StudentStatus projection

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-018 |
| Policy Domain | Multi-enrollment projection |
| Question | students.status when A Graduated & B Active; controlling enrollments; multi Graduated projection |
| Current Evidence | 3C.11A HUMAN DECISION REQUIRED; DL-022 projection-only |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-019 — SS-REVOKE-CLEAR

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-019 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-020 — HD-36 mechanism

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-020 |
| Current Classification | **LOCKED** |
| Human Decision | APPROVED — OPTION A (prior) |
| Status | **LOCKED** |

### D-3C15A-021 — HD-36-A/B/C/D roles & reasons

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-021 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |

### D-3C15A-022 — HD-36-E/F/G independence / re-issue

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-022 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |
| Note | Schema can support lineage; policy for independent revoke/re-issue not supplied |

### D-3C15A-023 — HD-38 Publication

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-023 |
| Policy Domain | HD-38 |
| Question | Publication core vs deferred vs not required? |
| Current Evidence | Unresolved in 3C.8B; 3C.15 interim “DEFERRED FEATURE” stance was **recommendation**, not new human form |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |
| Note | Cannot mark DEFERRED as LOCKED deferral without explicit human approval of deferral |

### D-3C15A-024 — Award attributes mandatory catalog

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-024 |
| Current Classification | **OPEN** |
| Human Decision | **NOT PROVIDED** |
| Status | **OPEN** |
| Note | Column existence ≠ required policy |

### D-3C15A-025 — DL-022 StudentStatus not SSOT

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-025 |
| Current Classification | **LOCKED** |
| Human Decision | ACCEPTED (DL-022) |
| Status | **LOCKED** |

### D-3C15A-026 — Idempotency in-txn + fingerprint

| Field | Value |
|-------|-------|
| Decision ID | D-3C15A-026 |
| Current Classification | **LOCKED** (architecture 3C.13) |
| Human Decision | Prior phase architecture lock |
| Status | **LOCKED** |

```text
CONFLICT: NONE
```

```text
PHASE 3C.15A = POLICY CLOSURE INCOMPLETE
Mandatory OPEN IDs: D-3C15A-002…013, 015, 018, 019, 021, 022, 023, 024
```
