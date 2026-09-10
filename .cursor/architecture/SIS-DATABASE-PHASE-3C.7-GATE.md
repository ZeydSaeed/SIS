# SIS DATABASE — PHASE 3C.7 GATE REPORT  
# GRADUATION / COMPLETION ARCHITECTURE

**Date:** 2026-09-10  
**Phase:** 3C.7 — Graduation / Completion Architecture  
**Mode:** AUDIT + DESIGN ONLY  

```text
HUMAN APPROVAL REQUIRED
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## Executive Summary

Phase 3C.7 designs Graduation/Completion as a **versioned, evidence-based, school-isolated derived academic outcome**, explicitly separating Completion from Graduation, rejecting blueprint threshold sketches as policy, preserving HD-01…18, introducing HD-19…42 as unresolved, and proposing DL-017…022 — **without implementation or invented academic rules**.

---

## Scope

### In scope
Audit, architecture, lifecycle, logical data model, invariants, rebuild/provenance, policy dependencies, human-decision register, gate.

### Out of scope
Migrations, DDL, RLS SQL, app code, engines, seeding thresholds, DB writes to `sis` / `sis_test`.

---

## Files Created

| File |
|------|
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-GRADUATION-COMPLETION-ARCHITECTURE.md` |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-GRADUATION-COMPLETION-LIFECYCLE.md` |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-GRADUATION-COMPLETION-DATA-MODEL.md` |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-GRADUATION-COMPLETION-INVARIANTS.md` |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-GRADUATION-COMPLETION-REBUILD.md` |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-GRADUATION-COMPLETION-POLICY-DEPENDENCIES.md` |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-HUMAN-DECISION-CLOSURE.md` |
| `.cursor/architecture/SIS-DATABASE-PHASE-3C.7-GATE.md` |

---

## Files Inspected

Phase 3C.0–3C.6 artifacts; `database-blueprint.md` graduation/promotion/certificates; `StudentStatus`; PHASE-D lifecycle; SchemaHelper schemas; master audit empty schemas; 3C results audit promotion notes.

---

## Database Changes

```text
NONE
```

## Application Changes

```text
NONE
```

---

## Architecture Decisions

1. Completion ≠ Graduation.  
2. Evidence-based requirement evaluation; missing ≠ satisfied.  
3. GPA/Ranking optional non-hidden deps only if policy pins them.  
4. Promotion separate (HD-41).  
5. Transcript consumer only.  
6. Enrollment/program-scoped identity + school_id.  
7. Same monolith; lifecycle module consumes Results (no microservice).  
8. `StudentStatus::Graduated` = projection only.  
9. Blueprint thresholds = stale, not approved.  
10. Machine eval → human approval path supported; auto-graduation not assumed.

---

## Preserved Previous Decision Locks

DL-001…DL-016 preserved in full.

---

## New Decision Locks (PROPOSED — await human acceptance)

| ID | Statement |
|----|-----------|
| DL-017 | Completion ≠ Graduation; both versioned derived outcomes |
| DL-018 | Never SSOT for grades/results/GPA/ranking/transcript |
| DL-019 | Official outcomes immutable in place; supersede only |
| DL-020 | Explicit evidence; missing ≠ satisfied |
| DL-021 | GPA/Ranking/Promotion/Year-closure not hidden/automatic deps |
| DL-022 | StudentStatus::Graduated is projection only |

---

## Human Decisions Required

See `SIS-DATABASE-PHASE-3C.7-HUMAN-DECISION-CLOSURE.md`.

- Prior: HD-01…18, Rounding — unresolved/deferred as before  
- New: **HD-19…HD-42** — all **UNRESOLVED**  
- None marked approved  

---

## Data Model

Logical entities: CompletionOutcome/Version, RequirementDefinition/Version, RequirementEvaluation, EvidenceSet/Item (typed sources), EligibilityEvaluation, GraduationApproval, GraduationAward, SourceSet, Provenance, Supersession, Publication, StudentStatus projection.

---

## Lifecycle

NOT_EVALUATED → EVALUATED → ELIGIBLE/COMPLETED → PENDING_APPROVAL → APPROVED → AWARDED (+ SUPERSEDED/REVOKED per policy). Calculated ≠ approval ≠ award ≠ publication.

---

## Invariants

GC-INV-001 … GC-INV-050.

---

## Rebuild Strategy

Pinned sources + program/curriculum + policies + calc version + explicit evidence → deterministic evaluation; fingerprint complements provenance; correction impacts create candidates + human HD-35/36 path.

---

## Provenance

Full answerability for enrollment/school/program/sources/policies/calc/evidence/approval/supersession; fingerprint ≠ sole audit.

---

## Security

SchoolContext fail-closed path; approval/award/correction authz slots; no URL/storage/client school_id as sole authz; RLS/FORCE RLS at future implementation.

---

## CQRS

Conceptual Evaluate/Recalculate/RequestApproval/Approve/Supersede/Publish commands; status/history/provenance/pending queries — not implemented.

---

## Event / Outbox Flow

Upstream grade/result/GPA events → completion impact → completion/graduation events → transcript impact; align naming with existing outbox conventions at implementation.

---

## Performance

Query/access patterns documented; no indexes/partitions; future EXPLAIN ANALYZE required; 20-year cardinality acknowledged.

---

## Stale Documentation Findings

| Finding | Class |
|---------|-------|
| Blueprint `graduation.eligibility_rules` min_gpa/credits/subjects | **STALE / CONFLICTING** vs policy neutrality |
| Blueprint `graduation.records` flat award sketch | **STALE** vs versioned model |
| Blueprint `promotion.rules.min_gpa` | **STALE** if read as graduation; **CURRENT sketch** for promotion only |
| LIVE graduation tables | **Absent** |
| `StudentStatus::Graduated` | **CURRENT BUT NON-AUTHORITATIVE** as graduation SSOT |
| Certificates `graduation_id` | **STALE sketch** consumer link |
| PHASE-D graduation tables plan | **CURRENT BUT NON-AUTHORITATIVE** |
| Hard-coded graduation thresholds in code | **Not found** as approved policy |

---

## Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Treat blueprint min_gpa as law | HIGH | HD-22 + GC-INV-037 |
| Collapse to is_graduated | HIGH | GC-INV-006/007/036 |
| Auto-grad on annual/transcript | HIGH | GC-INV-028 |
| Silent revoke after grade fix | CRITICAL | GC-INV-023 / HD-35 |
| GPA hidden dependency | HIGH | GC-INV-026 |
| Promotion≡Graduation | HIGH | GC-INV-027 / HD-41 |
| Cross-school leakage | CRITICAL | GC-INV-010/042 |
| student_id-only multi-program | HIGH | GC-INV-030 |
| Fingerprint-as-idempotency | MEDIUM | GC-INV-032 |
| Invent thresholds in impl | CRITICAL | GC-INV-050 |

---

## Open Questions

All HD-19…42; acceptance of proposed DL-017…022; exact module packaging (HD-42); whether StudentStatus sync is mandatory on award.

---

## Gate Criteria Score /100

| Area | Max | Score | Notes |
|------|-----|-------|-------|
| A Domain correctness | 6 | 6 | |
| B Completion vs Graduation | 6 | 6 | |
| C Policy neutrality | 8 | 8 | No invented thresholds |
| D Versioning | 5 | 5 | |
| E Provenance | 5 | 5 | |
| F Rebuildability | 5 | 5 | |
| G Correction propagation | 5 | 5 | |
| H Multi-enrollment | 4 | 4 | |
| I Security / RLS design | 6 | 5 | Design only; RLS not implemented (expected) |
| J CQRS | 4 | 4 | |
| K Outbox | 4 | 4 | |
| L Idempotency | 4 | 4 | |
| M Concurrency | 4 | 4 | |
| N Performance blueprint | 4 | 4 | |
| O Transcript boundary | 4 | 4 | |
| P GPA boundary | 4 | 4 | |
| Q Ranking boundary | 3 | 3 | |
| R Promotion boundary | 3 | 3 | |
| S Human approval boundary | 5 | 5 | |
| T Historical reproducibility | 5 | 5 | |
| **Total** | **100** | **93** | |

Deductions: −1 security (design-only RLS), −2 human-policy open set blocking implementation readiness (expected for design phase), −4 not applied beyond that — architecture complete with conditions.

---

## Gate Status

```text
PASS WITH CONDITIONS
```

Score **93/100** (band 90–94).

---

## Required Remediation

```text
NONE (design-phase)

CONDITIONS (not remediation defects):
1. HD-19…HD-42 remain unresolved — do not implement engines until approved.
2. Proposed DL-017…DL-022 require human acceptance.
3. Blueprint graduation/promotion threshold sketches must not be treated as policy.
4. No implementation authorized by this gate.
```

---

## Next Recommended Phase

Options (human chooses):

1. **Human Decision Workshop** — close P0 HD-19/20/21/31/32/35 (+ deps)  
2. **Phase 3C consolidation / ADR acceptance** — lock DL-017…022 with DL-001…016  
3. **Future implementation phase** (only after explicit approval) — schema for graduation/completion  
4. **Not** automatic start of code/migrations  

```text
NEXT: HUMAN APPROVAL REQUIRED BEFORE ANY GRADUATION/COMPLETION IMPLEMENTATION
```

---

## Final Principle Checklist

| Principle | Met |
|-----------|-----|
| Grades remain evidence SSOT | YES |
| Results governed derived | YES |
| GPA independent governed metric | YES |
| Ranking separate snapshot | YES |
| Transcript consumer + issued artifact | YES |
| Graduation/Completion governed outcome | YES |
| No academic policy invented | YES |
| No silent historical mutation design | YES |
| No silent correction rewrite | YES |
| Reproducible official outcomes | YES |
| Provenance required | YES |
| School boundary enforced in design | YES |
| Human authority explicit | YES |
| Implementation performed | **NO** |

```text
HUMAN APPROVAL REQUIRED

DATABASE CHANGES: NONE
APPLICATION CHANGES: NONE
MIGRATIONS: NONE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED

PHASE 3C.7 GATE: PASS WITH CONDITIONS

STOP
```
