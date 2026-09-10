# Phase 3C.14 — Implementation Scope (Candidates Only)

**No code in this phase.** Candidates for a future authorized Phase 3C.15+ only.

## Multi-enrollment semantics (HD-39 LOCKED for Graduation SSOT)

Graduation identity remains:

```text
school_id + enrollment_id
```

Each enrollment may independently hold CompletionOutcome / Approval / Award (UNIQUE per school+enrollment).

### Scenario A — same school, two enrollments

```text
Student X · School A · Enrollment 1 · Enrollment 2
```

| Expected (LOCKED by HD-39) |
|----------------------------|
| Two independent outcome/award lineages allowed |
| No forced single school-lifetime graduation row |

### Scenario B — two schools

```text
Student X · School A Enr1 · School B Enr2
```

| Expected |
|----------|
| Strict school isolation (RLS + composite FK) |
| No cross-school outcome |

### Scenario C — completed vs active enrollments

```text
Enrollment 1 completed · Enrollment 2 active
```

| Expected |
|----------|
| Enrollment 1 may hold official completion/award |
| Enrollment 2 independent |
| Student-level `Graduated` flip | **POLICY NOT LOCKED** (SS-MULTI) |

### Scenario D — multiple programs

```text
Multiple programs via enrollment/program context
```

| Expected (HD-39) |
|------------------|
| Scoped per enrollment/program context — not student_id-only |

**StudentStatus does not redefine Graduation identity.** It may later project from awards under a separate human rule.

---

## Phase 3C.15 candidate work packages

| Package | Includes | Gate |
|---------|----------|------|
| A — Scaffold | `sis:make-feature Graduation`; Domain ports; Infra repos; DI | Needs impl auth; no policy content |
| B — Idempotency/UoW | In-txn store + fingerprint (3C.13); do **not** copy Enrollment post-commit | SAFE pattern |
| C — Policy admin cmds | Draft/publish policy versions with empty/institution JSONB later | Framework OK; content later |
| D — EvaluateCompletion | Engine + evidence | **BLOCKED** until HD-20/21 content |
| E — Approval/Award/Revoke | Commands + Policies + Permissions | **BLOCKED** until HD-31 roles (+ HD-36 reasons) |
| F — Queries | Current official / history from SSOT | Conditionally SAFE |
| G — HTTP | Controllers/routes/FormRequests | After E authz exists |
| H — Outbox events | Domain events + ProcessOutboxJob arms | Event names OPTIONAL governance |
| I — StudentStatus sync | Students consumer | **BLOCKED** until SS-MULTI / SS-REVOKE-CLEAR |
| J — Tests | Unit + PG integration + concurrency from 3C.12B/13 | With each package |

## Explicit non-goals for first implementation wave

- Inventing `graduation.*` permission strings without human catalog  
- Encoding GPA/credit/attendance thresholds  
- Auto-approve  
- Treating `StudentStatus::Graduated` as SSOT  
- Schema changes to identity grain  

## Enrollment comparison (infra only)

| Concern | Enrollment today | Graduation required |
|---------|------------------|---------------------|
| Idempotency timing | After commit (debt) | **Inside** UoW |
| Fingerprint conflict | Not asserted | **Required** (Guard) |
| Outbox | In txn | In txn (same) |
| Business semantics | Enrollment domain | **Do not copy** |

## Verdict

```text
IMPLEMENTATION SCOPE: PARTIAL / BLOCKED for official write path
```
