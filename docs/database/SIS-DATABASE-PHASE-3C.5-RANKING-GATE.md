# SIS DATABASE — PHASE 3C.5 GATE REPORT  
# RANKING ARCHITECTURE — AUDIT + DESIGN LOCK

**Date:** 2026-09-10  
**Phase:** 3C.5 — Ranking Architecture  
**Authorization:** Audit + architecture + design locks + implementation plan only  

---

## Executive Summary

Phase 3C.5 designs Ranking as a **versioned, non-SSOT, school-isolated snapshot system** with typed scope anchors, explicit input datasets, layered eligibility, tie/privacy **policy slots** (unresolved), dual operational/official currents, fingerprints, deterministic rebuild, and correction propagation — **without inventing** scopes, ties, privacy rules, or ranking algorithms, and without any implementation.

---

## Scope

### Executed
Audit of ranking mentions; architecture; lifecycle; logical data model; snapshot contract; invariants; rebuild; traceability; risk register; future implementation plan; gate.

### Explicitly not executed
```text
No DDL, migrations, SQL, indexes, RLS, partitions
No ranking/GPA/grade/transcript engines
No models, repos, handlers, jobs, APIs, UI, routes
No database writes to sis or sis_test
No silent HD resolution
```

---

## Documents Created

| File | Action |
|------|--------|
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-ARCHITECTURE.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-LIFECYCLE.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-DATA-MODEL.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-SNAPSHOT-CONTRACT.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-INVARIANTS.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-REBUILD.md` | Created |
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-GATE.md` | Created (this report) |
| Application / migrations | None |

---

## Inspected Sources

Phase 3A–3B.1; 3C.0–3C.4 gates/artifacts; 3C.1 Policy Catalog §5.9; blueprint `rank_in_*` sketches; optimization/intelligence “ranking” (out of scope); ADR-021…028 remain DRAFT / unchanged.

---

## Authoritative Precedence

1. LIVE grades implementation (3B/3B.1)  
2. Migration/schema reality  
3. Phase 3C.0 DL locks  
4. Latest phase designs (3C.1–3C.4)  
5. Older docs only when non-conflicting  

Conflicts recorded — not silently reconciled; no DDL cleanup in this phase.

---

## Decision Locks Preserved

DL-001…DL-016 preserved. Especially DL-005 (ranking non-SSOT), DL-011 (school isolation), DL-013 (ranking eventual/snapshot), DL-016 (determinism).

---

## Human Decisions

| ID | Topic | Status in 3C.5 |
|----|-------|----------------|
| HD-08 | Ranking scope | **Unresolved** — extensible model only |
| HD-09 | Tie policy | **Unresolved** — representation only |
| HD-10 | Privacy | **Unresolved** — layered visibility only |
| HD-14 | Ranking fail vs Annual finalize | **Unresolved** — default non-blocking architecture |
| HD-01,02,04–07,15,18, Rounding | Metric/eligibility deps | **Unresolved dependencies** |
| HD-11,12,16 | Transcript | Boundary only |

**RESOLVED invented:** none.

---

## Ranking Architecture

Non-SSOT snapshot downstream of grades → Term/Annual → GPA/approved metric → Ranking. No backward truth edges.

---

## Ranking Identity

**PROPOSED:** school_id + ranking_scope + **typed** scope_anchor_type/id + period type/id + population_definition_id.

---

## Ranking Scope Model

Architecture-supported examples only (section/class/program/school/cohort/year/…). Official set = **HDR (HD-08)**.

---

## Ranking Input Contract

Explicit reconstructible dataset: participants, source versions, membership, eligibility layers, include/exclude reasons, metric, pins, fingerprints. No assumed 4.0/percentage/credits.

---

## Versioning / Operational vs Official / Lifecycle

Monotonic versions; dual currents; NOT_CALCULATED → CALCULATED → FINALIZED → SUPERSEDED; publication separate; FINALIZED ranking ≠ Annual/GPA/transcript/graduation/year-closed.

---

## Eligibility / Tie / Privacy / Provenance

Seven eligibility layers; tie groups + tie_policy_version; calc vs publish vs individual vs admin vs peer visibility; full provenance path.

---

## Deterministic Rebuild / Correction Propagation

Rebuild equation and outbox impact path documented; four mechanisms (idempotency, concurrency, version conflict, fingerprint) separated.

---

## School Isolation / CQRS / Concurrency / Performance / Failure

Fail-closed tenant path; conceptual commands/queries; no guessed indexes/partitions; fail closed on ambiguity; no partial official; no silent policy/GPA substitution.

---

## Annual Results / GPA / Transcript Boundaries

```text
Annual → GPA → Ranking
```
Ranking not required for Annual/GPA finalize by default (HD-14). Ranking consumes pinned GPA versions — does not recalculate or mutate GPA. Transcript separate (DL-006).

---

## Invariants

RK-INV-001 … RK-INV-040.

---

## Traceability Matrix

| Requirement | Source | Ranking Decision | Status |
|-------------|--------|------------------|--------|
| DL-005 | 3C.0 | Ranking snapshot non-SSOT | **Preserved** |
| DL-001 | 3C.0 | Grades remain SSOT | **Preserved** |
| DL-004 | 3C.0/3C.4 | GPA pinned input possible | **Dependency** |
| DL-006 | 3C.0 | Transcript separate | **Preserved** |
| DL-007…016 | 3C.0 | Versioning/provenance/isolation/determinism | **Preserved** |
| HD-08 | 3C.0 | Scope | **Unresolved** |
| HD-09 | 3C.0 | Tie policy | **Unresolved** |
| HD-10 | 3C.0 | Privacy | **Unresolved** |
| HD-14 | 3C.0 | Failure dependency | **Unresolved** (default independent) |
| HD-01 | 3C.0 | GPA dependency | **Unresolved** |
| HD-02 | 3C.0 | Grade interpretation | **Unresolved** |
| HD-04 | 3C.0 | Eligibility | **Unresolved** |
| HD-05 | 3C.0 | Special statuses | **Unresolved** |
| HD-06 | 3C.0 | Retake | **Unresolved** |
| HD-07 | 3C.0 | Transfer/incomplete | **Unresolved** |
| HD-15 | 3C.0 | Credits | **Unresolved** |
| HD-18 | 3C.0 | Dataset completeness | **Unresolved** |
| Rounding | 3C.1 | Metric comparison | **Unresolved** |
| Blueprint rank_in_* | Blueprint | Not Ranking SSOT | **Stale / superseded** |
| Optimization RecommendationRanker | App | Non-academic | **Out of scope** |

---

## Stale / Conflicting Documentation

```text
STALE: database-blueprint.md rank_in_section / rank_in_class on term_results / annual_results
  → embeds rank into results; superseded by separate Ranking Snapshot (DL-005 / 3C.5)

STALE RISK: treating blueprint ranks as authoritative Ranking SSOT
  → FORBIDDEN

KNOWN (carry-forward): Grade uniqueness doc conflict — LIVE 3B partial UNIQUE wins; cleanup FUTURE

UNRELATED: Intelligence/Optimization “ranking” / percentiles — not academic Results ranking
```

---

## Risk Register

| Risk ID | Severity | Description | Impact | Detection | Mitigation | Status |
|---------|----------|-------------|--------|-----------|------------|--------|
| RK-R01 | HIGH | Invented ranking scope | Wrong academic product | Design review / HD-08 open | HDR + RK-INV-038 | Open |
| RK-R02 | HIGH | Invented tie policy | Non-reproducible history | HD-09 open | Tie version pins + refuse official without policy | Open |
| RK-R03 | HIGH | Invented privacy policy | Peer leakage / PII | HD-10 open | Layered visibility; default deny peers (guidance only) | Open |
| RK-R04 | CRITICAL | Ranking becomes academic SSOT | Corrupts grades/GPA/Annual | Architecture review | RK-INV-001/002 | Mitigated in design |
| RK-R05 | HIGH | GPA scale assumptions in ranking | Invalid metrics | HD-01 open | Consume pinned GPA only; no internal calc | Open dep |
| RK-R06 | MEDIUM | Ranking from non-official GPA as “official rank” | Misleading official snapshot | Finalize gates | Official ranking should pin official metric versions when policy requires — **HDR** | Open |
| RK-R07 | HIGH | Annual↔Ranking hard coupling | Blocks academic finalize | HD-14 | Default non-blocking (RK-INV-028) | Open (HDR) |
| RK-R08 | CRITICAL | Cross-school leakage | Tenant breach | Security tests (future) | Fail closed + RLS/FORCE RLS design | Mitigated in design |
| RK-R09 | HIGH | Historical mutation | Audit failure | Lineage checks | Supersession only | Mitigated in design |
| RK-R10 | HIGH | Missing provenance | Non-rebuildable | Gate criteria | Pins + fingerprints required | Mitigated in design |
| RK-R11 | HIGH | Ambiguous scope anchors | Wrong population | Schema review (future) | Typed anchors mandatory | Mitigated in design |
| RK-R12 | HIGH | Non-deterministic ordering | Rebuild mismatch | Equivalence tests (future) | Forbid unstable sorts for official | Mitigated in design |
| RK-R13 | MEDIUM | Duplicate official versions | Lineage corruption | Idempotency + concurrency | Four mechanisms separated | Mitigated in design |
| RK-R14 | MEDIUM | Stale source versions | Wrong ranks | Version checks | Re-read / fail; no partial official | Mitigated in design |
| RK-R15 | MEDIUM | Incomplete population | Unfair ranks | HD-18 | Eligibility layers; HDR | Open dep |
| RK-R16 | HIGH | Privacy publication mismatch | Unauthorized exposure | Authz/privacy pins | Calc ≠ publish | Open (HD-10) |
| RK-R17 | LOW | Generic indexes without evidence | Waste / wrong plans | Perf governance | No indexes this phase | Mitigated |
| RK-R18 | LOW | Premature partitioning | Complexity | DL-010 | No partition initially | Mitigated |
| RK-R19 | HIGH | Ranking failure blocks academic finalization | Ops deadlock | HD-14 | Default independent | Open (HDR) |

---

## Future Implementation Plan

> **FUTURE — NOT AUTHORIZED IN PHASE 3C.5**

| Phase | Content | Authorization |
|-------|---------|---------------|
| **A** | Schema design (logical → physical mapping) | NOT AUTHORIZED |
| **B** | Migrations | NOT AUTHORIZED |
| **C** | Domain model | NOT AUTHORIZED |
| **D** | Ranking input projection | NOT AUTHORIZED |
| **E** | Calculation engine (`R`) | NOT AUTHORIZED — needs HD-08/09 + metric HDs |
| **F** | Snapshot/versioning | NOT AUTHORIZED |
| **G** | Publication/privacy | NOT AUTHORIZED — needs HD-10 |
| **H** | Rebuild/correction propagation | NOT AUTHORIZED |
| **I** | Security/RLS | NOT AUTHORIZED |
| **J** | Performance validation (EXPLAIN ANALYZE) | NOT AUTHORIZED |

Human approval required before any of A–J. Prefer resolving P0 HDs (08/09/10 + metric deps) before E/G.

---

## Gate Decision

| Criterion | Result |
|-----------|--------|
| 1 Architecture completeness | PASS |
| 2 SSOT correctness | PASS |
| 3 Ranking isolation | PASS |
| 4 Policy safety (no invention) | PASS |
| 5 Human-decision traceability | PASS |
| 6 Versioning | PASS |
| 7 Provenance | PASS |
| 8 Deterministic rebuild | PASS |
| 9 Correction propagation | PASS |
| 10 Security | PASS (design) |
| 11 Privacy architecture | PASS WITH CONDITIONS (HD-10 open) |
| 12 CQRS boundaries | PASS |
| 13 Concurrency/idempotency | PASS |
| 14 Performance architecture | PASS (blueprint only) |
| 15 Failure safety | PASS |
| 16 Historical integrity | PASS |
| 17 Annual boundary | PASS |
| 18 GPA boundary | PASS |
| 19 Transcript boundary | PASS |
| 20 Implementation readiness | **NOT READY** — HDs unresolved; implementation not authorized |

### Conditions
1. HD-08/09/10/14 remain unresolved.  
2. No implementation authorized.  
3. Official ranking engine blocked on P0 HDs + metric deps.  
4. Blueprint `rank_in_*` must not be treated as Ranking SSOT.  
5. Phase 3C.6 requires separate human approval.

```text
PHASE 3C.5 GATE: PASS WITH CONDITIONS

IMPLEMENTATION AUTHORIZATION: NOT GRANTED

DATABASE CHANGES: NONE

MIGRATIONS: NONE

API CHANGES: NONE

UI CHANGES: NONE

APPLICATION CODE CHANGES: NONE

DATABASE WRITES: NONE

NEXT PHASE: PHASE 3C.6 — TRANSCRIPT ARCHITECTURE

HUMAN APPROVAL REQUIRED BEFORE PHASE 3C.6

STOP
```
