# Phase 3C.15 — HD-31 Authorization Decision

## Locked (model)

| Item | Classification | Evidence |
|------|----------------|----------|
| Human approval required; no silent ELIGIBLE→APPROVED/GRADUATED | **LOCKED** | `.cursor/architecture/SIS-DATABASE-PHASE-3C.8B-DECISION-CLOSURE-REGISTER.md` HD-31 Option A |
| Machine may evaluate / prepare candidate / request / record | **LOCKED** | same |
| School scope / fail-closed tenancy | **LOCKED** (DERIVED from HD-39 + RLS) | HD-39; FORCE RLS on graduation tables |
| Approver recorded as opaque actor id when present | **LOCKED** (structure) | D-3C10-006 opaque BIGINT |

## Open (must not invent)

| Question | Answer in repo | Classification |
|----------|----------------|----------------|
| Who may approve completion? | Not defined | **OPEN** |
| Who may approve graduation? | Not defined | **OPEN** |
| Role-based vs permission-based catalog | No Graduation permissions; Enrollment/Grades only in `Permission.php` | **OPEN** |
| Multiple approval levels | `attempt_no` exists structurally; workflow depth not approved | **OPEN** |
| Delegation | Not found | **OPEN** |
| Self-approval prohibited? | Not found | **OPEN** |
| Evaluator ≠ approver (SoD)? | Not found | **OPEN** |
| Approval revoke/supersede authority | Tied to HD-35/36; roles open | **OPEN** |

```text
HD-31-ROLES = POLICY NOT LOCKED
```

**Forbidden this phase:** inventing `graduation.approve`, `graduation.revoke`, `graduation.publish`, or mapping to “admin/principal/supervisor”.

## Repo inspection

| Artifact | Finding |
|----------|---------|
| `app/Security/Authorization/Permission.php` | ENROLLMENT_* / GRADES_* only — **no graduation** |
| Graduation Policy classes | **Absent** |
| Gates for graduation | **Absent** |

## Implementation impact

```text
DecideGraduationApproval / IssueGraduationAward / Revoke* with bound Policy
= IMPLEMENTATION BLOCKED until HD-31-ROLES (+ related) approved
```

Structural “no auto-approve” invariant is already LOCKED and must be preserved when coding is later authorized.
