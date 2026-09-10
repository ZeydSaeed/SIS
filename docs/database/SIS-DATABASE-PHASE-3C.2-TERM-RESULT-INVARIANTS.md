# SIS DATABASE — PHASE 3C.2  
# TERM RESULT INVARIANTS

**Document type:** FORMAL INVARIANT CATALOG  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

## Core Invariants

### TR-INV-001
Term Result is derived and never Grade SSOT.  
**Source:** DL-001

### TR-INV-002
Official historical Results are never silently mutated.  
**Source:** DL-007

### TR-INV-003
Same authoritative inputs + same policy versions + same calculation version + same eligibility boundary produce equivalent Results.  
**Source:** DL-016

### TR-INV-004
Outbox is not the authoritative rebuild source.  
**Source:** DL-009

### TR-INV-005
Cross-school Result access is prohibited (fail-closed).  
**Source:** DL-011

### TR-INV-006
Absent grade preserves `score IS NULL` semantics; absent ≠ zero.  
**Source:** Phase 3B/3B.1 LIVE

### TR-INV-007
Finalized/official Result cannot be hard-deleted.  
**Source:** DL-015

### TR-INV-008
Policy version(s) used for official calculation are identifiable.  
**Source:** DL-014 / DL-008

### TR-INV-009
Calculation version used for official calculation is identifiable.  
**Source:** DL-008

### TR-INV-010
Source fingerprint is reproducible from authoritative inputs (+ recorded policy/calc/eligibility).  
**Source:** DL-008 / DL-016

### TR-INV-011
Supersession preserves historical lineage (prior official retained).  
**Source:** DL-007

### TR-INV-012
Operational Result cannot masquerade as official Result.  
**Source:** DL-013

---

## Additional Term-Result Invariants

### TR-INV-013
Official Term Result versions retain a reconstructible contributing grade identity set.  
**Source:** Phase 3C.2 Architecture

### TR-INV-014
Business identity is distinct from version identity.  
**Source:** Phase 3C.2 Architecture

### TR-INV-015
HD-04 grade-status eligibility is stored/applied separately from HD-18 dataset completeness.  
**Source:** Phase 3C.0 / 3C.1

### TR-INV-016
Weight-sum=100 is not an implicit invariant unless HD-03 resolves it.  
**Source:** HD-03

### TR-INV-017
Annual Results must remain rebuildable without exclusive dependence on stored Term Results.  
**Source:** DL-003

### TR-INV-018
No `ON DELETE CASCADE` of official Term Result history without explicit future human justification.  
**Source:** Phase 3C.2 Data Model

### TR-INV-019
Idempotent reprocessing of identical official inputs must not create uncontrolled duplicate official currents.  
**Source:** Phase 3C.2 Architecture (idempotency)

### TR-INV-020
Ranking and GPA are not embedded as Term Result SSOT; they may consume Term Results later.  
**Source:** DL-004 / DL-005
