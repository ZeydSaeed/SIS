# SIS DATABASE — PHASE 3C.1  
# POLICY DEPENDENCY GRAPH

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

## 1. Primary Graph

```text
Academic Structure
(year, term, exam graph, enrollment, school)
       │
       ▼
Grade Eligibility (HD-04)
  + Dataset Completeness (HD-18)
       │
       ▼
Retake Selection (HD-06)
       │
       ▼
Absence / Missing / Incomplete Treatment (HD-05, HD-07)
       │
       ▼
Assessment Weighting (HD-03) + Rounding Policy
       │
       ▼
Term Result (versioned hybrid) ← DL-002
       │
       ├──────────────────► Annual Result ← DL-003
       │                         │
       │                         ├─► GPA (HD-01, HD-15) ← DL-004
       │                         │
       │                         └─► Ranking Snapshot (HD-08…10, HD-14) ← DL-005
       │                                   ▲
       └───────────────────────────────────┘
                 (may also rank from term totals)

Term / Annual Official Versions
       │
       ▼
Transcript Live Projection ← DL-006
       │
       ▼
[Phase 3D] Issued Immutable Artifact ← HD-16
```

**Async trigger (not SSOT):**

```text
Grade Domain Events (Outbox)
       │
       ▼
Invalidate / Recalculate Operational Results (DL-009)
```

**Rebuild path:**

```text
student_grades + Academic Structure + Published Policies + Calculation Version
       │
       ▼
Deterministic Rebuild (DL-016)
```

---

## 2. Human-Decision Dependency Map

| Node | Blocking HDs |
|------|----------------|
| Grade eligibility filter | HD-04 |
| Official finalize allowed | HD-18, HD-17 |
| Weight application | HD-03 |
| Absent/missing treatment | HD-05 |
| Retake canonicalization | HD-06 |
| Incomplete year/term | HD-07 |
| GPA node | HD-01, HD-15 |
| Letter node | HD-02 |
| Ranking node | HD-08, HD-09, HD-10, HD-14 |
| Transcript issued behavior | HD-11, HD-12 |
| Packaging 3D | HD-16 |
| Operational freshness | HD-13 |
| Rounding (cross-cutting) | Implicit with HD-01/03 — **HDR** |

---

## 3. Lock Overlay

| Edge | Lock |
|------|------|
| Grades → Results (direction) | DL-001 one-way |
| Official history | DL-007 supersession |
| Policy publish | DL-014 immutable |
| Ranking authority | DL-005 non-SSOT |
| Transcript hybrid | DL-006 |
| Consistency modes | DL-013 |
| Tenant isolation on all school nodes | DL-011 |

---

## 4. Conflict Callout (non-silent)

```text
CONFLICT: grade uniqueness documentation
AUTHORITATIVE SOURCE: Phase 3B LIVE partial UNIQUE (exam_enrollment_id, academic_year_id) WHERE is_current
NON-AUTHORITATIVE SOURCE: data-quality-rules.md UNIQUE (exam_session_id, student_id)
PROPOSED RESOLUTION: Results dependency graph uses exam_enrollment / is_current semantics
HUMAN DECISION REQUIRED: update data-quality doc in a later docs task (not 3C.1 DDL)
```
