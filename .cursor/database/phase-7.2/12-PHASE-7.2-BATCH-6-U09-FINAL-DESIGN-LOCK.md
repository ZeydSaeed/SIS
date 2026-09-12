# MASTER PHASE 7
# PHASE 7.2
# BATCH 6
# U09 — FINAL DESIGN LOCK

---

```text
Document Type:
FINAL DESIGN LOCK

Unit:
U09 — PresentExamEnrollment

Business operation:
Confirmed → Present

Domain:
Exams / Exam Enrollment lifecycle

Aggregate / lifecycle object:
exams.exam_enrollments

Command:
PresentExamEnrollmentCommand

Permission:
exam.enrollment.present

Authority:
grades_manager

Design Status:
FINAL LOCKED

Business Semantics:
LOCKED

Packaging:
LOCKED

Security:
LOCKED

Lifecycle Matrix:
LOCKED

Grade Safety:
LOCKED

Event / Outbox:
LOCKED

Idempotency:
LOCKED

Concurrency:
DESIGN LOCKED; parallel proof deferred

Database:
NO CHANGE REQUIRED

RLS:
NO CHANGE REQUIRED

HTTP:
DEFERRED

Human Decisions:
5/5 APPROVED

Unresolved Decisions:
0

Implementation:
NOT AUTHORIZED BY THIS DOCUMENT
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Mode** | FINAL DESIGN LOCK + AuthZ request companion only — NO CODE |
| **Gate** | `10-PHASE-7.2-BATCH-6-U09-DESIGN-AUTHORIZATION-GATE.md` |
| **HD Resolution** | `11-PHASE-7.2-BATCH-6-U09-HUMAN-DECISION-RESOLUTION.md` |
| **Phase 7.2 Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` (§7, §10 HD-7.2-005) |
| **AuthZ Request** | `13-PHASE-7.2-BATCH-6-U09-HUMAN-IMPLEMENTATION-AUTHORIZATION-REQUEST.md` |

```text
Final Design Lock ≠ Implementation Authorization
Do not implement U09 from this document alone.
```

---

## 1. Authorization State (at lock time)

```text
MASTER PHASE 7:
AUTHORIZED

PHASE 7.2:
AUTHORIZED / DESIGN LOCK

BATCH 6:
OPEN

U08:
IMPLEMENTED + AUDITED
PASS WITH CONDITIONS

U09:
DESIGN DECISIONS RESOLVED → FINAL DESIGN LOCKED (this document)
IMPLEMENTATION NOT AUTHORIZED

U10–U15:
NOT AUTHORIZED
```

---

## 2. Human Decision Verification

| Decision ID | Topic | Locked value | Status |
|-------------|-------|--------------|--------|
| HD-U09-001 | Command | `PresentExamEnrollmentCommand` | **HUMAN APPROVED** |
| HD-U09-002 | Event | `ExamEnrollmentUpdated` | **HUMAN APPROVED** |
| HD-U09-003 | Cause | `exam_enrollment_present` | **HUMAN APPROVED** |
| HD-U09-004 | Present → Present | IDEMPOTENT NO-OP (no mutation / event / outbox) | **HUMAN APPROVED** |
| HD-U09-005 | CURRENT grade | DOES NOT BLOCK; grade mutation NONE; lookup failure FAIL CLOSED | **HUMAN APPROVED** |

```text
5/5 APPROVED
0 UNRESOLVED
Conflicts requiring STOP: NONE
```

Do not reopen these decisions unless a genuine authoritative contradiction is discovered.

---

## 3. Final Identity

```text
Unit:
U09 — PresentExamEnrollment

Business operation:
Confirmed → Present

Domain:
Exams / Exam Enrollment lifecycle

Aggregate:
exams.exam_enrollments

Command:
PresentExamEnrollmentCommand

Permission:
exam.enrollment.present

Authority:
grades_manager

Action (design):
PresentEnrollment
(expected enum value at implementation: present_enrollment — not implemented yet)
```

U09 MUST NOT become a generic enrollment update.  
Superseded path **U07 Update → Present** remains **FORBIDDEN**.  
U07 must continue to reject `Confirmed → Present`.

---

## 4. Final Transition Matrix

| Enrollment State | Session State | U09 |
| ---------------- | ------------- | --- |
| Confirmed | InProgress | **ALLOW → Present** |
| Confirmed | Scheduled | **DENY** |
| Confirmed | Completed | **DENY** |
| Confirmed | Cancelled | **DENY** |
| Registered | Any | **DENY** |
| Absent | Any | **DENY** |
| Withdrawn | Any | **DENY** |
| Present | InProgress | **IDEMPOTENT NO-OP** |
| Present | Scheduled | **DENY** / not a valid U09 transition |
| Present | Completed | **DENY** / not a valid U09 transition |
| Present | Cancelled | **DENY** / not a valid U09 transition |

### Cancelled-session distinction (LOCKED)

```text
U08:
Cancelled Session + Active enrollment may be allowed

U09:
Cancelled Session → Present is DENIED
```

Do not copy U08 HD-U08-003 onto U09.

---

## 5. Session State Rule (LOCKED)

```text
Required: Session = InProgress

Scheduled → DENY
Completed → DENY
Cancelled → DENY
InProgress → eligible (when enrollment Confirmed, or Present no-op)
```

Do not broaden this rule.

---

## 6. Grade Safety (LOCKED)

```text
CURRENT grade:     DOES NOT BLOCK Present
Historical grade:  DOES NOT BLOCK Present
No grade:          DOES NOT BLOCK Present
(when all U09 lifecycle/security guards pass)

Grade lookup/state failure: FAIL CLOSED
(do not interpret lookup failure as “no grade exists”)
```

U09 MUST NOT:

```text
create / update / delete / alter / derive grades
alter grade status
```

Present is strictly an enrollment lifecycle mutation.  
DR-002 (CURRENT grade blocks **CancelExamEnrollment**) is **not** extended to U09.

---

## 7. Attendance Safety (LOCKED)

```text
Present ≠ is_absent
U09 MUST NOT create / update / delete / toggle attendance
No attendance side effect is authorized
```

---

## 8. Security (LOCKED)

```text
Action:     PresentEnrollment (design; case absent until AuthZ + impl)
Permission: exam.enrollment.present  (EXISTS — U16; do not recreate)
Authority:  grades_manager

same-school:           ALLOW when all other guards pass
cross-school:          DENY / FAIL CLOSED
missing school context: FAIL CLOSED
unauthorized caller:   FAIL CLOSED
```

```text
No new permission required.
Do not modify PermissionCatalogExamAuthority / config/security.php in this lock task.
```

---

## 9. Idempotency (LOCKED)

Phase 7.2 writer pattern:

```text
same key + same payload → REPLAY
same key + different payload → CONFLICT

Confirmed → Present → real mutation + event
Present → Present → business NO-OP; no duplicate event

event identity ≠ idempotency identity
```

---

## 10. Event / Outbox (LOCKED)

```text
Event: ExamEnrollmentUpdated
Cause: exam_enrollment_present
```

Emit **ONLY** on real `Confirmed → Present`.

MUST NOT emit on:

```text
Present → Present
DENY
authorization failure
cross-school rejection
idempotency conflict
replay
lookup/state fail-closed paths
```

Same-COMMIT outbox + idempotency pattern (design requirement only).

Do not introduce `ExamEnrollmentPresented`.

---

## 11. U05 / U06 / U07 / U08 Boundary (LOCKED)

| Unit | Boundary |
|------|----------|
| **U05** | Owns session cancellation. Cancelled session → U09 Present **DENY**. |
| **U06** | Creates Registered. Does not own Present. |
| **U07** | Owns Confirm / Absent. Continues to reject Confirmed → Present. |
| **U08** | Owns Present → Withdrawn. U09 does not withdraw. |

No existing unit may be redesigned as part of U09 AuthZ / implementation scope.

---

## 12. Database / RLS / HTTP (LOCKED)

```text
DATABASE CHANGE REQUIRED: NO
New table / column / index / FK / constraint / enum / trigger: NO
RLS modification: NO
HTTP: DEFERRED / OUT OF SCOPE FOR PHASE 7.2 WRITERS
```

Operate on existing `exam_enrollments.status` only.

---

## 13. Architecture (design description only)

```text
PresentExamEnrollmentCommand
        ↓
PresentExamEnrollmentHandler
        ↓
PresentExamEnrollmentMutationService
        ↓
PresentExamEnrollmentGuard
        ↓
Repository / existing persistence boundary
        ↓
same-COMMIT outbox + idempotency
```

Supporting:

```text
PresentExamEnrollmentResult
ExamAdministrationAction::PresentEnrollment
PermissionCatalogExamAuthority mapping → exam.enrollment.present
```

```text
DO NOT create these classes from this Design Lock alone.
```

---

## 14. Concurrency (LOCKED design; proof deferred)

```text
U09-first: Confirmed → Present
U09-second: Present → Present = NO-OP

Parallel race proof: NOT A REQUIRED DESIGN-LOCK GATE
Production-load race verification: DEFERRED TO POST-IMPLEMENTATION VERIFICATION
```

Consistent with U08 known limitation. Do not invent an unapproved concurrency gate.

---

## 15. Legacy / Alternate Write Path (LOCKED)

Verified at final audit:

```text
No PresentExamEnrollment handler exists
No dangerous alternate Present writer found
U07 correctly rejects Confirmed → Present
```

Invariant:

```text
No generic Update path may be used to bypass the dedicated U09 Present operation.
```

---

## 16. Final Design Invariants

```text
1. U09 is the sole authorized Present lifecycle operation.
2. Confirmed → Present requires InProgress session.
3. Scheduled → Present is forbidden.
4. Completed → Present is forbidden.
5. Cancelled → Present is forbidden.
6. Registered → Present is forbidden.
7. Absent → Present is forbidden.
8. Withdrawn → Present is forbidden.
9. Present → Present is an idempotent business no-op.
10. CURRENT grade does not block Present.
11. Grade lookup failure is fail-closed.
12. U09 never mutates grades.
13. U09 never mutates attendance.
14. Present does not imply is_absent=false mutation.
15. U09 uses exam.enrollment.present.
16. Cross-school access fails closed.
17. Event = ExamEnrollmentUpdated.
18. Cause = exam_enrollment_present.
19. No event is emitted for a business no-op.
20. Event identity is distinct from idempotency identity.
21. No database schema change is required.
22. No RLS change is required.
23. HTTP is deferred.
24. U07 must not be used to reach Present.
25. U08 remains responsible for Present → Withdrawn.
```

---

## 17. Final Design Lock Checklist

| Category | Status |
| -------- | ------ |
| U09 identity | **PASS** |
| Business transition | **PASS** |
| Human decisions | **PASS** |
| Command naming | **PASS** |
| Event packaging | **PASS** |
| Event cause | **PASS** |
| Idempotency | **PASS** |
| CURRENT grade rule | **PASS** |
| Session-state rules | **PASS** |
| School isolation | **PASS** |
| Permission | **PASS** |
| U05 interaction | **PASS** |
| U06 interaction | **PASS** |
| U07 interaction | **PASS** |
| U08 interaction | **PASS** |
| Database impact | **PASS** |
| RLS impact | **PASS** |
| HTTP scope | **PASS** |
| Architecture | **PASS** |
| Legacy-path analysis | **PASS** |
| Conflicts | **NONE** |
| Unresolved decisions | **NONE** |

```text
DESIGN LOCK READINESS:
PASS
```

---

## 18. Next Gate

```text
NEXT:
13 — HUMAN IMPLEMENTATION AUTHORIZATION REQUEST
(human must explicitly APPROVE before any U09 code)

U09 IMPLEMENTATION AUTHORIZATION:
NOT GRANTED by this Final Design Lock
```
