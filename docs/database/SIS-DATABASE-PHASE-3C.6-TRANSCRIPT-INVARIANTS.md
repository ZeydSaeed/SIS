# SIS DATABASE — PHASE 3C.6  
# TRANSCRIPT INVARIANTS

**Document type:** FORMAL INVARIANT CATALOG  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

### TR-INV-001
Transcript is not academic SSOT.  
**Source:** DL-006 / DL-001

### TR-INV-002
`exams.student_grades` remains the only Grade SSOT.  
**Source:** DL-001

### TR-INV-003
Live Transcript is reconstructible from governed sources + policies.  
**Source:** DL-006

### TR-INV-004
Issued Transcript academic content is immutable after issuance.  
**Source:** DL-006 / DL-007

### TR-INV-005
Historical official versions are superseded, never silently mutated in place.  
**Source:** DL-007 / DL-015

### TR-INV-006
Every official transcript has complete provenance (actor, times, correlation, pins, fingerprints).  
**Source:** DL-008

### TR-INV-007
Every academically meaningful transcript value is traceable to a governed source version.  
**Source:** Content Contract

### TR-INV-008
Transcript must not calculate GPA internally; it consumes pinned GPA versions only.  
**Source:** Phase 3C.4 boundary

### TR-INV-009
Transcript must not calculate Ranking internally; it consumes pinned Ranking versions only when included.  
**Source:** Phase 3C.5 boundary / DL-005

### TR-INV-010
Transcript must not mutate Annual Results.  
**Source:** Phase 3C.3

### TR-INV-011
Transcript must not mutate Term Results.  
**Source:** Phase 3C.2

### TR-INV-012
Transcript must not mutate GPA.  
**Source:** Phase 3C.4

### TR-INV-013
School isolation is fail-closed (SchoolContext → … → RLS → FORCE RLS).  
**Source:** DL-011

### TR-INV-014
Artifact retrieval requires authorization.  
**Source:** Security architecture

### TR-INV-015
Storage references are not authorization.  
**Source:** Artifact security

### TR-INV-016
ISSUED ≠ PUBLIC / PUBLISHED.  
**Source:** Lifecycle

### TR-INV-017
FINALIZED ≠ ISSUED.  
**Source:** Lifecycle

### TR-INV-018
CALCULATED ≠ Official / ISSUED.  
**Source:** Lifecycle / DL-006

### TR-INV-019
Presentation version cannot silently alter historical academic content of an issued version.  
**Source:** Content vs presentation

### TR-INV-020
Grade/source correction triggers explicit transcript impact analysis.  
**Source:** HD-11 architecture / DL-009

### TR-INV-021
Idempotency is independent of fingerprint equivalence.  
**Source:** Concurrency design

### TR-INV-022
Concurrency control is independent of fingerprint equivalence.  
**Source:** Concurrency design

### TR-INV-023
Historical artifacts remain auditable (default no hard delete of official history).  
**Source:** DL-015 / HD-12 boundary

### TR-INV-024
No partial official issuance.  
**Source:** Failure model

### TR-INV-025
No silent policy substitution on official transcripts.  
**Source:** Failure model

### TR-INV-026
No silent source-version substitution on official transcripts.  
**Source:** Failure model

### TR-INV-027
Live and Issued concepts must not be collapsed.  
**Source:** DL-006

### TR-INV-028
Version conflict detection and artifact identity are distinct from fingerprint equivalence.  
**Source:** Concurrency design

### TR-INV-029
Ranking is not a required transcript dependency by default.  
**Source:** Phase 3C.5 / HD-14 boundary

### TR-INV-030
HD-11 correction-after-issuance policy must not be invented by implementation.  
**Source:** HD-11

### TR-INV-031
HD-12 retention durations must not be invented by implementation.  
**Source:** HD-12

### TR-INV-032
Blueprint `results.transcripts` sketch is not Transcript SSOT.  
**Source:** Audit

### TR-INV-033
Cross-school transcript/source/artifact access fails closed.  
**Source:** DL-011

### TR-INV-034
Outbox is propagation only; reconstruction uses pinned sources, not processed outbox history as SSOT.  
**Source:** DL-009

### TR-INV-035
Content completeness layers must not be collapsed into a single opaque ready flag.  
**Source:** Content Contract

### TR-INV-036
Official historical explanation must not depend solely on current live database values.  
**Source:** Historical reproducibility

### TR-INV-037
Transcript must not write back into Ranking.  
**Source:** DL-005 / consumer rule

### TR-INV-038
Calculate, Finalize, Issue, and Publish are distinct operations.  
**Source:** Lifecycle
