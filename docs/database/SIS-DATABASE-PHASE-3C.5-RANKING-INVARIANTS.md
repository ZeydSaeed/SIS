# SIS DATABASE — PHASE 3C.5  
# RANKING INVARIANTS

**Document type:** FORMAL INVARIANT CATALOG  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

### RK-INV-001
Ranking is a derived snapshot/projection and is never academic Grade SSOT.  
**Source:** DL-005 / DL-001

### RK-INV-002
Ranking must not feed backward into grades, Term/Annual Results, GPA, or academic eligibility truth.  
**Source:** DL-005

### RK-INV-003
Ranking identity is unambiguous and includes school_id.  
**Source:** Phase 3C.5 Architecture

### RK-INV-004
Scope anchors are typed (`scope_anchor_type` + id or equivalent); untyped polymorphic anchors are forbidden.  
**Source:** Phase 3C.5 Architecture

### RK-INV-005
School isolation is mandatory (SchoolContext → Handler → composite boundary → RLS → FORCE RLS).  
**Source:** DL-011

### RK-INV-006
Missing/ambiguous school scope fails closed.  
**Source:** DL-011

### RK-INV-007
At most one official current RankingVersion per Ranking Identity.  
**Source:** Lifecycle

### RK-INV-008
Version numbers are monotonic per Ranking Identity.  
**Source:** Lifecycle

### RK-INV-009
Official ranking content is never silently mutated in place.  
**Source:** DL-007

### RK-INV-010
Official ranking changes via supersession only; no hard delete of official/superseded history.  
**Source:** DL-007 / DL-015

### RK-INV-011
Supersession preserves lineage (predecessor/successor, reason, audit).  
**Source:** DL-007

### RK-INV-012
Official ranking identifies source metric/result/GPA versions consumed.  
**Source:** DL-008

### RK-INV-013
Official ranking identifies policy version pins used (including tie and privacy as applicable).  
**Source:** DL-014

### RK-INV-014
Official ranking identifies `calculation_version`.  
**Source:** DL-008

### RK-INV-015
Official ranking carries source, policy, and calculation fingerprints covering required inputs.  
**Source:** DL-008 / DL-016

### RK-INV-016
Population definition for an official ranking is explicit and versioned.  
**Source:** HD-08 boundary

### RK-INV-017
Population existence, scope membership, validity, completeness, academic eligibility, ranking eligibility, and metric contribution are separate concepts.  
**Source:** Phase 3C.5 Architecture

### RK-INV-018
Each exclusion has a deterministic reason.  
**Source:** Phase 3C.5 Architecture

### RK-INV-019
Tie handling is governed by an explicit tie_policy_version; tie policy is never implicit.  
**Source:** HD-09

### RK-INV-020
Publication/access is governed by explicit privacy/publication policy; calculation ≠ automatic peer exposure.  
**Source:** HD-10

### RK-INV-021
Identical approved inputs + pins produce equivalent ranking snapshots.  
**Source:** DL-016

### RK-INV-022
Ordering must be deterministic under the pinned tie/calculation policies; unstable ordering is forbidden for official snapshots.  
**Source:** Snapshot Contract

### RK-INV-023
Ranking rebuild does not require the prior ranking snapshot as academic input.  
**Source:** Rebuild design

### RK-INV-024
Source corrections produce a new affected ranking version when material; never silent overwrite.  
**Source:** DL-009 / DL-007

### RK-INV-025
Outbox is propagation only; ranking rebuild SSOT is reconstructible inputs + pins.  
**Source:** DL-009

### RK-INV-026
Ranking must not recalculate GPA internally; it consumes pinned source versions only.  
**Source:** Phase 3C.4 / 3C.5 boundary

### RK-INV-027
Ranking FINALIZED does not imply Annual FINALIZED, GPA FINALIZED, transcript issued, graduation, or year closed.  
**Source:** Lifecycle

### RK-INV-028
Ranking failure does not invalidate Annual Results, GPA, or academic year closure by default.  
**Source:** HD-14 (default architecture; coupling remains HDR)

### RK-INV-029
Operational ranking must not masquerade as official ranking.  
**Source:** DL-013

### RK-INV-030
Idempotency, concurrency control, version conflict detection, and fingerprint equivalence are distinct mechanisms.  
**Source:** Phase 3C.5 Architecture §13

### RK-INV-031
Duplicate execution must not create uncontrolled duplicate official snapshots.  
**Source:** Idempotency

### RK-INV-032
Concurrent calculations must not corrupt version lineage.  
**Source:** Concurrency

### RK-INV-033
Ambiguous policy/source/security state blocks official finalize/publication (fail closed).  
**Source:** Failure model

### RK-INV-034
No silent fallback to older policy or substituted GPA/calculation version.  
**Source:** Failure model

### RK-INV-035
No partial official ranking snapshot.  
**Source:** Failure model

### RK-INV-036
Cross-school ranking is forbidden unless explicit future approved policy and security model permit it.  
**Source:** DL-011

### RK-INV-037
Blueprint `rank_in_*` sketch columns are not Ranking SSOT.  
**Source:** Audit

### RK-INV-038
Scope, tie, and privacy policies must not be invented by implementation when unresolved.  
**Source:** HD-08 / HD-09 / HD-10

### RK-INV-039
Transcript must not require ranking unless a future approved transcript policy says so.  
**Source:** DL-006 boundary

### RK-INV-040
Provenance must explain participant rank, exclusions, source versions, and policy pins.  
**Source:** Data Model
