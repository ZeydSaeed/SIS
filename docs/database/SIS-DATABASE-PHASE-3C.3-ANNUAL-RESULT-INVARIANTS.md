# SIS DATABASE — PHASE 3C.3  
# ANNUAL RESULT INVARIANTS

**Document type:** FORMAL INVARIANT CATALOG  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

### AR-INV-001
`exams.student_grades` remains the only Grade SSOT; Annual Results are derived only.  
**Source:** DL-001

### AR-INV-002
School isolation is mandatory (Policy → SchoolContext → Handler → Composite FK → RLS → FORCE RLS).  
**Source:** DL-011

### AR-INV-003
Business identity is distinct from version identity and storage identity.  
**Source:** Phase 3C.3 Architecture

### AR-INV-004
At most one official current Annual Result per business identity.  
**Source:** Phase 3C.3 Lifecycle

### AR-INV-005
Version numbers are monotonic per business identity.  
**Source:** Phase 3C.3 Lifecycle

### AR-INV-006
Official Annual Results are never silently mutated in place.  
**Source:** DL-007

### AR-INV-007
Official content is immutable; change requires superseding version.  
**Source:** DL-007 / DL-015

### AR-INV-008
Supersession preserves lineage (predecessor/successor, reason, audit).  
**Source:** DL-007

### AR-INV-009
Official versions retain a reconstructible contributing grade source set.  
**Source:** Phase 3C.3 Architecture

### AR-INV-010
Official versions identify policy version pins used.  
**Source:** DL-014 / DL-008

### AR-INV-011
Official versions identify calculation_version.  
**Source:** DL-008

### AR-INV-012
Official versions carry a reproducible source fingerprint covering required inputs.  
**Source:** DL-008 / DL-016

### AR-INV-013
Identical authoritative inputs + policies + calculation version + eligibility inputs (+ rounding policy when approved) yield equivalent Annual Results.  
**Source:** DL-016

### AR-INV-014
Annual Results are independently rebuildable from grades SSOT without requiring stored Term Results.  
**Source:** DL-003

### AR-INV-015
Term Results are never the sole academic rebuild source for Annual Results.  
**Source:** DL-003 / Phase 3C.3 Architecture

### AR-INV-016
Academic-year completeness, dataset completeness, eligibility, and grade status are separate concepts.  
**Source:** Phase 3C.0 HD-04/07/18 · Phase 3C.3 Architecture

### AR-INV-017
Concurrent rebuilds/finalizes must not create uncontrolled duplicate official currents (idempotency/serialization).  
**Source:** Phase 3C.3 Architecture

### AR-INV-018
Duplicate outbox delivery must not create uncontrolled duplicate official currents.  
**Source:** DL-009 + idempotency

### AR-INV-019
Missing/ambiguous school scope fails closed.  
**Source:** DL-011

### AR-INV-020
No hard delete of finalized/superseded official Annual Results (or issued transcript artifacts — out of annual scope but preserved globally).  
**Source:** DL-015

### AR-INV-021
Annual FINALIZED does not imply GPA approved, ranking complete, transcript issued, or academic year closed.  
**Source:** Phase 3C.3 Lifecycle

### AR-INV-022
Operational Annual Result must not masquerade as official.  
**Source:** DL-013

### AR-INV-023
Absent grade semantics remain `is_absent ⇒ score NULL`; absent ≠ zero.  
**Source:** Phase 3B/3B.1

### AR-INV-024
Outbox is propagation only; grades remain rebuild SSOT.  
**Source:** DL-009

### AR-INV-025
Provenance (actor, timestamps, correlation, pins, fingerprint, lineage) is required for official versions.  
**Source:** Phase 3C.3 Data Model
