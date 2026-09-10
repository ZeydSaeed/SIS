# SIS DATABASE — PHASE 3C.7  
# GRADUATION / COMPLETION INVARIANTS

**Document type:** FORMAL INVARIANT CATALOG  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

### GC-INV-001
Graduation/Completion is not Grade SSOT; grades remain SSOT.  

### GC-INV-002
Graduation/Completion is not Term/Annual Results SSOT.  

### GC-INV-003
Graduation/Completion is not GPA SSOT.  

### GC-INV-004
Graduation/Completion is not Ranking SSOT.  

### GC-INV-005
Graduation/Completion is not Transcript SSOT; transcript must not create graduation truth.  

### GC-INV-006
Completion and Graduation are distinct concepts.  

### GC-INV-007
Calculated ≠ Eligible ≠ Approved ≠ Awarded ≠ Published.  

### GC-INV-008
No hard delete of official graduation/completion records (default; HD-37).  

### GC-INV-009
No silent mutation of official outcomes; supersede only.  

### GC-INV-010
School isolation is fail-closed (SchoolContext → … → RLS → FORCE RLS).  

### GC-INV-011
Published requirement/completion policies are immutable in place.  

### GC-INV-012
Official outcomes pin calculation_version.  

### GC-INV-013
Official outcomes pin policy_version(s) used.  

### GC-INV-014
Official outcomes retain explicit SourceSet / EvidenceSet (not fingerprint alone).  

### GC-INV-015
Fingerprint detects equivalence/change; it does not replace provenance.  

### GC-INV-016
Deterministic rebuild under identical pinned inputs yields equivalent evaluation.  

### GC-INV-017
Eligibility is explicit and separately represented from raw scores.  

### GC-INV-018
Evidence is explicit; each evaluation item is explainable.  

### GC-INV-019
Missing mandatory evidence must not silently become SATISFIED/PASS (fail closed).  

### GC-INV-020
Approval requires authorized human/system actor per policy (HD-31).  

### GC-INV-021
Version conflict detection prevents stale approval/award over newer versions.  

### GC-INV-022
Supersession preserves predecessor/successor lineage and reason.  

### GC-INV-023
Grade/result corrections trigger impact analysis — not automatic silent revoke.  

### GC-INV-024
Transcript must not write back into graduation/completion.  

### GC-INV-025
Ranking is not a graduation prerequisite by default.  

### GC-INV-026
GPA is not a hidden graduation dependency; only if policy explicitly requires and pins a GPA version.  

### GC-INV-027
Promotion is not equivalent to Graduation.  

### GC-INV-028
No automatic graduation on enrollment completion, annual finalization, transcript issuance, ranking finalization, or year closure.  

### GC-INV-029
Historical official outcomes remain reproducible from pinned sources + policies.  

### GC-INV-030
Multi-enrollment/program contexts use enrollment/program-scoped identity, not student_id alone.  

### GC-INV-031
Concurrency control is independent of fingerprint.  

### GC-INV-032
Idempotency is independent of fingerprint (fingerprint is not an idempotency key).  

### GC-INV-033
Source versions in an official evaluation must be school-consistent and type-safe.  

### GC-INV-034
Publication is separate from award/approval.  

### GC-INV-035
Award is separate from eligibility calculation.  

### GC-INV-036
`StudentStatus::Graduated` is a projection, not graduation SSOT.  

### GC-INV-037
Blueprint `min_gpa` / credit sketches are not approved policy.  

### GC-INV-038
Optimization/Intelligence ranking must not drive academic graduation.  

### GC-INV-039
Academic year closure does not imply graduated; graduated does not imply year closed.  

### GC-INV-040
Completion date and graduation date are distinct concepts (semantics HDR).  

### GC-INV-041
Evidence references use typed source identity (no ambiguous bare polymorphic IDs).  

### GC-INV-042
Cross-school graduation access fails closed.  

### GC-INV-043
Client-provided school_id / URL / storage path / artifact id is not sole authorization.  

### GC-INV-044
Outbox is propagation only; rebuild uses governed sources + policies.  

### GC-INV-045
Exempt / N/A / pending / missing / not satisfied remain distinct statuses.  

### GC-INV-046
Official evaluation must not silently substitute different policy or source versions.  

### GC-INV-047
Certificates may reference awards; they do not author graduation truth.  

### GC-INV-048
Irreversible autonomous graduation without approved policy is forbidden.  

### GC-INV-049
Vocational/academic unit extensibility must not break subject-based current anchor without redesign approval.  

### GC-INV-050
Implementation must not invent HD-19…HD-42 academic thresholds or rules.  
