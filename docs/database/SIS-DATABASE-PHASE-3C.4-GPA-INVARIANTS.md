# SIS DATABASE — PHASE 3C.4  
# GPA INVARIANTS

**Document type:** FORMAL INVARIANT CATALOG  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

### GPA-INV-001
GPA is a derived academic calculation, never Grade SSOT.  
**Source:** DL-001 / DL-004

### GPA-INV-002
`exams.student_grades` remains the only authoritative score store.  
**Source:** DL-001

### GPA-INV-003
School isolation is mandatory (SchoolContext → Handler → relational boundary → RLS → FORCE RLS).  
**Source:** DL-011

### GPA-INV-004
GPA business identity includes scope; scope identity must be explicit.  
**Source:** Phase 3C.4 Architecture

### GPA-INV-005
At most one official current GPA per business identity.  
**Source:** Phase 3C.4 Lifecycle

### GPA-INV-006
Version numbers are monotonic per GPA business identity.  
**Source:** Phase 3C.4 Lifecycle

### GPA-INV-007
Official GPA is never silently mutated in place.  
**Source:** DL-007

### GPA-INV-008
Official GPA content is immutable; change requires superseding version.  
**Source:** DL-007 / DL-015

### GPA-INV-009
Supersession preserves lineage (predecessor/successor, reason, audit).  
**Source:** DL-007

### GPA-INV-010
Official GPA retains a reconstructible GPA Input Dataset (included + excluded).  
**Source:** Phase 3C.4 Data Model

### GPA-INV-011
Existence, validity, completeness, eligibility, and contribution are separate concepts.  
**Source:** Phase 3C.4 Architecture · HD-04/05/06/07/15/18

### GPA-INV-012
Credit values used in official GPA carry provenance (value, source, context).  
**Source:** HD-15 boundary

### GPA-INV-013
Grade-point values used in official GPA carry mapping-policy provenance.  
**Source:** HD-01 / HD-02 boundary

### GPA-INV-014
Official GPA identifies policy version pins used.  
**Source:** DL-014 / DL-008

### GPA-INV-015
Official GPA identifies `calculation_version`.  
**Source:** DL-008

### GPA-INV-016
Official GPA pins effective rounding policy when rounding is applied.  
**Source:** Rounding boundary

### GPA-INV-017
Official GPA carries a reproducible source fingerprint covering required inputs.  
**Source:** DL-008 / DL-016

### GPA-INV-018
Identical authoritative GPA inputs + policies + calculation version + rounding + eligibility yield equivalent GPA.  
**Source:** DL-016

### GPA-INV-019
GPA rebuild does not require a prior GPA result as academic input.  
**Source:** Phase 3C.4 Rebuild

### GPA-INV-020
GPA must not prevent Annual Results from being independently rebuildable from grades.  
**Source:** DL-003

### GPA-INV-021
Annual FINALIZED does not imply GPA FINALIZED; GPA FINALIZED does not mutate Annual Results.  
**Source:** Phase 3C.3 / 3C.4 boundaries

### GPA-INV-022
Grade and Annual Result corrections propagate via impact detection; outbox is trigger only.  
**Source:** DL-009

### GPA-INV-023
Concurrent rebuilds/finalizes must not create uncontrolled duplicate official currents.  
**Source:** Concurrency design

### GPA-INV-024
Duplicate outbox delivery must not create uncontrolled duplicate official currents.  
**Source:** Idempotency · DL-009

### GPA-INV-025
Missing/ambiguous school scope fails closed.  
**Source:** DL-011

### GPA-INV-026
No hard delete of finalized or superseded official GPA.  
**Source:** DL-015

### GPA-INV-027
GPA FINALIZED does not imply ranking finalized, transcript issued, graduation approved, or academic year closed.  
**Source:** Phase 3C.4 Lifecycle

### GPA-INV-028
Operational GPA must not masquerade as official GPA.  
**Source:** DL-013

### GPA-INV-029
GPA must not implement ranking.  
**Source:** DL-005

### GPA-INV-030
GPA must not issue transcripts.  
**Source:** DL-006

### GPA-INV-031
Provenance must answer “why this GPA?” including excluded items and reasons.  
**Source:** Phase 3C.4 Data Model

### GPA-INV-032
GPA formula, scale, credits, and rounding must not be invented by implementation when unresolved.  
**Source:** HD-01 / HD-15 / Rounding · Phase 3C.0
