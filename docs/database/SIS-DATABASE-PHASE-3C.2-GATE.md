# SIS DATABASE — PHASE 3C.2 GATE REPORT  
# TERM RESULTS ARCHITECTURE

**Date:** 2026-09-10  
**Phase:** 3C.2 — Term Results Architecture  
**Authorization:** Design/architecture only (human-approved phase scope)

---

## A. Execution Scope

```text
Documentation only
No DDL
No migrations
No DB writes
No application code
No APIs
No UI
```

Confirmed: no calculators, ranking/transcript engines, jobs, RLS SQL, indexes, or schema changes. `sis` / `sis_test` untouched.

---

## B. Documents

| File | Action |
|------|--------|
| `docs/database/SIS-DATABASE-PHASE-3C.2-TERM-RESULTS-ARCHITECTURE.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.2-TERM-RESULT-LIFECYCLE.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.2-TERM-RESULT-DATA-MODEL.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.2-TERM-RESULT-INVARIANTS.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.2-TERM-RESULT-REBUILD.md` | **Created** |
| `docs/database/SIS-DATABASE-PHASE-3C.2-GATE.md` | **Created** (this report) |
| ADR-021…028 | **Unchanged** (remain DRAFT) |
| Application / migrations | **None** |

---

## C. Architecture Decision Summary

| Topic | Decision |
|-------|----------|
| Identity | Business: school + enrollment + year + term + subject; version separate |
| Grain | Subject-term under enrollment (existing academic structure) |
| Versioning | Monotonic versions; operational current ≠ official current |
| Lifecycle | NOT_CALCULATED → CALCULATED → FINALIZED → SUPERSEDED |
| Operational vs official | Explicit; ops may lag; ops must not masquerade |
| Source set | Explicit grade composite identities + snapshots |
| Policy / calc binding | Separate version pins; no silent historical rewrite |
| Eligibility | HD-04 ≠ HD-18; both slots |
| Fingerprint | Logical coverage defined; hash algo FUTURE |
| Rebuild | Grades + structure + policies + calc + eligibility |
| Supersession / audit | Lineage + actor/correlation |
| School isolation | school_id + composite FK path + future RLS/FORCE |
| Performance | Index blueprint only; **no partition initially** |
| CQRS | Calculate / Finalize / Supersede / Rebuild + read queries (contracts only) |

---

## D. Invariants

TR-INV-001 … TR-INV-020 documented in `SIS-DATABASE-PHASE-3C.2-TERM-RESULT-INVARIANTS.md`.

---

## E. Human Decisions

| Status | IDs |
|--------|-----|
| **RESOLVED** | *(none invented in 3C.2)* |
| **UNRESOLVED** | HD-01…HD-15, HD-17, HD-18, Rounding |
| **DEFERRED** | HD-16 (3C/3D packaging — await confirm) |

### What HDs block

| Layer | Blocking HDs |
|-------|----------------|
| Architecture only | None blocking *this design* |
| Physical schema implement | Prefer HD-04/03/18 clarity for constraints/check docs |
| Calculation implementation | HD-03, HD-04, HD-05, HD-06, Rounding, HD-18 |
| Official finalization product rules | HD-17, HD-18, role matrix; HD-07 for incomplete |

---

## F. Conflicts

```text
DOCUMENTATION CONFLICT
Topic: Grade uniqueness
AUTHORITATIVE: Phase 3B LIVE partial UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current
NON-AUTHORITATIVE: data-quality-rules.md UNIQUE (exam_session_id, student_id)
PROPOSED RESOLUTION: Term Results source set uses exam_enrollment / is_current semantics
HUMAN DECISION REQUIRED: docs cleanup (not performed in 3C.2)
```

Blueprint `results.term_results` sketch lacks versioning/school_id/policy pins — treated as **stale sketch**, superseded by 3C.2 logical model.

---

## G. Security

No design bypass of:

```text
Policy → SchoolContext → Handler → Composite FK → RLS → FORCE RLS
```

Fail-closed for missing school context and cross-school references. Ranking privacy remains HD-10 (out of Term Result SSOT).

---

## H. Historical Truth

| Control | Status |
|---------|--------|
| No silent mutation | Affirmed |
| No hard delete of official history | Affirmed |
| Version/supersession | Affirmed |
| Auditability | Affirmed |
| Grade correction path | Affirmed |

---

## I. Deterministic Rebuild

Confirmed sufficient architectural inputs:

```text
Grades + structure + policy + calculation version + eligibility
```

Outbox is trigger-only.

---

## J. Performance

Index/partition strategy: **blueprint only**; **NO PARTITIONING INITIALLY** (DL-010). Evidence-driven later.

---

## K. Implementation Boundary

```text
NO IMPLEMENTATION AUTHORIZED
```

---

## L. Next Phase

```text
PHASE 3C.3 — ANNUAL RESULTS ARCHITECTURE
```

**Do not begin 3C.3** until explicit human approval.

---

## Architectural Q&A Gate (all YES)

1–15 answered YES in Architecture doc §21 → design integrity holds; open HDs condition implementation, not this architecture gate.

---

```text
PHASE 3C.2 GATE: PASS WITH CONDITIONS

IMPLEMENTATION AUTHORIZATION: NOT GRANTED

DATABASE CHANGES: NONE
MIGRATIONS: NONE
API CHANGES: NONE
UI CHANGES: NONE
APPLICATION CODE CHANGES: NONE

NEXT PHASE: PHASE 3C.3 — ANNUAL RESULTS ARCHITECTURE

HUMAN APPROVAL REQUIRED BEFORE PHASE 3C.3
```

### Conditions

1. HD-01…HD-18 remain unresolved/deferred as listed — not silently closed.  
2. Official Term Result **calculation implementation** remains blocked on P0 HDs (HD-03, HD-04, HD-05, HD-06, HD-18, Rounding).  
3. No schema/API/code authorized by this gate.  
4. Phase 3C.3 requires separate explicit human approval.  
5. Stale data-quality uniqueness docs remain a known conflict (documented, not “fixed” by inventing DDL).
