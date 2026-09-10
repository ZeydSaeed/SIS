# Phase 3C.14 — Graduation Authorization Matrix

**Rule:** Only cite roles/permissions evidenced in repo/governance.  
Graduation-specific permissions: **none exist** in `app/Security/Authorization/Permission.php` (Enrollment/Grades only).

## HD-31 findings (authoritative)

| Question | Locked answer |
|----------|---------------|
| May system silently auto-approve / auto-graduate? | **NO** (HD-31 Option A) |
| May system evaluate / prepare candidate / request approval / record decision? | **YES** (as capabilities — not as silent approval) |
| Exact role names / committees / org hierarchy | **POLICY NOT LOCKED** |
| Separation of duties required? | **POLICY NOT LOCKED** |
| One-step vs multi-step | **POLICY NOT LOCKED** (`attempt_no` supports multiples structurally) |
| School scope | **YES** — school isolation retained (HD-39 + RLS); cross-school FORBIDDEN |

## Operation matrix

| Operation | Actor | School Scope | Required Permission | Policy | Result |
|-----------|-------|--------------|---------------------|--------|--------|
| Upsert draft eligibility policy | UNKNOWN | school_id | UNKNOWN / POLICY NOT LOCKED | HD-20 framework | Draft rows only |
| Publish policy version | UNKNOWN | school_id | UNKNOWN / POLICY NOT LOCKED | HD-20 | Current-effective pin |
| Evaluate completion | System and/or registrar UNKNOWN | school_id | UNKNOWN / POLICY NOT LOCKED | HD-20/21 content open | Candidate version — **not** approval |
| Publish official completion | UNKNOWN | school_id | UNKNOWN / POLICY NOT LOCKED | HD-35 human gate for official | Official version |
| Request graduation approval | UNKNOWN | school_id | UNKNOWN / POLICY NOT LOCKED | HD-31 | Approval attempt row |
| Decide graduation approval | **Human** (model locked) | school_id | UNKNOWN / POLICY NOT LOCKED | HD-31 roles open | Approved/rejected |
| Issue graduation award | UNKNOWN (after approved) | school_id | UNKNOWN / POLICY NOT LOCKED | HD-32 | Award version |
| Supersede completion/award | Human elevated UNKNOWN | school_id | UNKNOWN / POLICY NOT LOCKED | HD-35 | New version + edge |
| Revoke award | Human authority UNKNOWN | school_id | UNKNOWN / POLICY NOT LOCKED | HD-36 roles/reasons open | Revocation record |
| Rebuild StudentStatus | Ops elevated UNKNOWN | school_id / student | UNKNOWN / POLICY NOT LOCKED | SS-MULTI open | Projection update |
| Cross-school any write | — | — | **DENIED** | HD-39 + RLS | Fail closed |

## Existing reusable authz infrastructure (not Graduation permissions)

| Asset | Status |
|-------|--------|
| `EnrollmentPolicy` / `GradePolicy` | Pattern only — **NOT** Graduation authority |
| `Permission::*` | No graduation constants |
| SchoolContext middleware | SAFE for tenant scope |

## Ambiguity

```text
AMBIGUITY FOUND: HD-31 role matrix / permission catalog
↓
DOCUMENT: APPROVED_WITH_CONDITION — human approval required; roles unspecified
↓
IMPACT: DecideGraduationApproval / IssueAward / Revoke cannot bind Policy classes
↓
HUMAN DECISION REQUIRED: supply institution roles + Permission constants (+ SoD if any)
```

Do **not** invent `graduation.approve` names in this phase.

## Verdict

```text
AUTHORIZATION: PARTIAL
HD-31 = PARTIAL (model LOCKED; roles NOT LOCKED)
```
