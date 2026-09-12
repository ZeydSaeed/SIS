# MASTER PHASE 7 — PHASE 7.8
# DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: 02 APPLIED
```

## Scope

### In

```text
- Permission: portal.results.view (+ role portal_results_viewer)
- Ownership via security.scopes (student|guardian) + student_guardians
- HTTP GET /api/v1/portal/results/{term|annual|gpa|transcripts/issued}
- Reuse Application official/issued queries (7.6) — no new SSOT
- SchoolContext + FORCE RLS unchanged
- PG feature tests (student allow / deny; guardian allow / deny)
```

### Out

```text
- Ranking portal
- PDF / render
- Operational results
- Admin scope-link HTTP (deferred)
- students.user_id / guardians.user_id columns (deferred; ballot A)
- Phase 8
- Staff results.view as portal AuthZ
```

## Invariants

| ID | Rule |
|----|------|
| INV-78-01 | Grade SSOT remains `exams.student_grades` only |
| INV-78-02 | Portal reads never mutate |
| INV-78-03 | Official-current / issued only |
| INV-78-04 | Enrollment accessible only if student_id ∈ allowed set for user |
| INV-78-05 | Guardian requires scopes.guardian + student_guardians |
| INV-78-06 | `portal.results.view` required; staff `results.view` insufficient alone |
| INV-78-07 | No ranking / no peer roster on portal v1 |
| INV-78-08 | Blueprint object count unchanged (no new tables) |

## Unit plan

| Unit | Name | AuthZ |
|------|------|-------|
| 7.8-U01 | Portal ownership + official portal HTTP | CLOSED |
| 7.8-U02 | Guardian path + deny-matrix hardening | CLOSED |
| 7.8-U03 | Final Closure Gate | CLOSED |

```text
PHASE 7.8 DESIGN LOCK: LOCKED
Units U01–U03 CLOSED — see 09-PHASE-7.8-FINAL-CLOSURE-GATE.md
```
