# MASTER PHASE 7
# PHASE 7.2
# BATCH 6
# U09 — HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

Unit:
U09 — PresentExamEnrollment

This document is NOT authorization.
It is a formal request asking the human to authorize implementation.

Until explicit human approval is recorded:

U09 IMPLEMENTATION = NOT AUTHORIZED
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Final Design Lock** | `12-PHASE-7.2-BATCH-6-U09-FINAL-DESIGN-LOCK.md` |
| **HD Resolution** | `11-PHASE-7.2-BATCH-6-U09-HUMAN-DECISION-RESOLUTION.md` |
| **Design Gate** | `10-PHASE-7.2-BATCH-6-U09-DESIGN-AUTHORIZATION-GATE.md` |
| **Phase 7.2 Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` (HD-7.2-005) |

```text
Creating this request ≠ APPROVE
Human Decision Resolution ≠ Implementation Authorization
Final Design Lock ≠ Implementation Authorization
```

---

## Authorization State

```text
Phase 7:
AUTHORIZED

Phase 7.2:
AUTHORIZED

Batch 6:
OPEN

U08:
PASS WITH CONDITIONS

U09:
FINAL DESIGN LOCKED

U09 IMPLEMENTATION:
NOT YET AUTHORIZED

U10–U15:
NOT AUTHORIZED
```

---

## Design Prerequisites (verified)

```text
Human decisions: 5/5 APPROVED (HD-U09-001..005)
Unresolved: 0
Conflicts: 0
Final Design Lock: PASS / FINAL LOCKED
Design Lock readiness: PASS
```

---

## Requested Authorization Scope

If the human later grants approval, authorization would cover **ONLY**:

```text
U09 — PresentExamEnrollment
```

Potential implementation surface (only as required by the locked design):

```text
PresentExamEnrollmentCommand
PresentExamEnrollmentHandler
PresentExamEnrollmentResult
PresentExamEnrollmentGuard
PresentExamEnrollmentMutationService
Repository / persistence integration (existing schema)
ExamAdministrationAction::PresentEnrollment + PermissionCatalogExamAuthority mapping
  (permission already exists; mapping only — no new permission string)
Outbox / ExamEnrollmentUpdated with cause=exam_enrollment_present
Idempotency integration (Phase 7.2 writer pattern)
U09-focused tests
```

Do NOT expand scope to U10–U15.  
Do NOT modify the permission model unless implementation reveals a contradiction requiring a new AuthZ gate.

---

## Explicit Non-Scope

This request, even if later approved, does **NOT** authorize:

```text
U10–U15
Batch 6 closure
HTTP implementation (routes / controllers / requests / responses)
Database schema changes
RLS changes
Unrelated refactoring
U05 / U06 / U07 / U08 redesign
Grade subsystem changes
Attendance subsystem changes
exam.session.cancel permission
Dedicated ExamEnrollmentPresented event class
Present via U07 UpdateExamEnrollment
```

---

## Implementation Safety Conditions

If approved later, implementation **MUST** preserve:

```text
Confirmed → Present only
InProgress session required
Cancelled / Completed / Scheduled denied
Registered / Absent / Withdrawn denied
Present → Present = no-op (no event / no duplicate outbox)
CURRENT grade does not block
Grade lookup failure = fail closed
No grade mutation
No attendance mutation
exam.enrollment.present
same-school only
cross-school fail closed
ExamEnrollmentUpdated
cause = exam_enrollment_present
no event on business no-op
same-COMMIT outbox / idempotency pattern
event identity ≠ idempotency identity
no DB schema change
no RLS change
HTTP deferred
U07 continues to reject Confirmed → Present
U08 remains owner of Present → Withdrawn
```

---

## Locked Summary (reference)

| Item | Value |
|------|-------|
| Command | `PresentExamEnrollmentCommand` |
| Transition | Confirmed → Present |
| Session | InProgress required |
| Permission | `exam.enrollment.present` → `grades_manager` |
| Event | `ExamEnrollmentUpdated` |
| Cause | `exam_enrollment_present` |
| Database | NO CHANGE |
| HTTP | DEFERRED |

Full invariants: see Final Design Lock §16.

---

## Post-Authorization Boundary

If the human approves U09 implementation:

```text
A SEPARATE implementation task must be used.
Do not auto-start implementation from this document.
Do not auto-generate an implementation prompt from this gate alone.
Do not execute tests that require newly created U09 code until AuthZ is recorded.
```

---

## Required Human Action

```text
HUMAN IMPLEMENTATION DECISION:

[x] APPROVE U09 IMPLEMENTATION
[ ] REJECT U09 IMPLEMENTATION
[ ] REQUEST AMENDMENT

Decision recorded by: Human (explicit implementation authorization prompt)
Date: 2026-09-12
Notes: MASTER PHASE 7 → PHASE 7.2 → BATCH 6 → U09 IMPLEMENTATION AUTHORIZED.
       U10–U15 remain NOT AUTHORIZED. Batch 6 remains OPEN.
```

```text
U09 IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
(Authorization recorded after Final Design Lock + request artifacts.)
```

---

## Final Status (this request)

```text
U09 DESIGN:
FINAL LOCKED

U09 HUMAN IMPLEMENTATION AUTHORIZATION REQUEST:
READY FOR HUMAN DECISION

U09 IMPLEMENTATION:
NOT AUTHORIZED

U10–U15:
NOT AUTHORIZED

BATCH 6:
OPEN
```
