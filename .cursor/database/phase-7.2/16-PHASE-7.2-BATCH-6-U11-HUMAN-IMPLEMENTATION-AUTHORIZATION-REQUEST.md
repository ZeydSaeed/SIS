# MASTER PHASE 7 — PHASE 7.2 — BATCH 6 — U11
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST → GRANTED

Unit:
U11 — Grade entry timing policy (Enter path enforcement)

Gate:
7.2-U11

Phase:
7.2

Batch:
6

Mode:
IMPLEMENTATION AUTHORIZED BY HUMAN (2026-09-12)

Implementation:
AUTHORIZED (see audit artifact 17)

U09 / U10:
LOCKED AFTER CLOSURE — UNCHANGED BY THIS REQUEST

U12–U15:
NOT AUTHORIZED
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Gate row** | `05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` → **7.2-U11** |
| **Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` §11 **HD-7.2-009** |
| **Current enter guard** | `StudentGradeWriteGuard::assertCanEnter` (Phase 7.1) — Cancelled blocked; **Scheduled not yet denied** |
| **This document** | Request only — ≠ authorization grant |

```text
Creating this request ≠ APPROVE U11
U10 closure ≠ U11 authorization
Phase / Batch authorization ≠ U11 unit authorization
```

---

## 1. Authorization State

```text
MASTER PHASE 7:
AUTHORIZED

PHASE 7.2:
AUTHORIZED / DESIGN LOCK

BATCH 6:
OPEN

U09:
IMPLEMENTED
AUDITED
PASS WITH CONDITIONS
COMPLETE

U10:
IMPLEMENTED
AUDITED
PASS WITH CONDITIONS
HUMAN REVIEW: ACCEPTED WITH CONDITIONS (this document §2)

U11:
IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
Human decision: APPROVE U11 IMPLEMENTATION
Audit: 17-PHASE-7.2-BATCH-6-U11-IMPLEMENTATION-AUDIT.md

U12–U15:
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 2. U10 Closure State

Reviewed artifact:

```text
.cursor/database/phase-7.2/15-PHASE-7.2-BATCH-6-U10-IMPLEMENTATION-AUDIT.md
```

Evidence consistency check:

| Claim | Supported by audit |
|-------|--------------------|
| CancelExam cascade coexistence | YES |
| HD-7.2-002D already-Cancelled session skip | YES |
| Remaining open-session cascade | YES |
| Already-Withdrawn skip | YES |
| Remaining active-seat withdrawal | YES |
| cause=`exam_cancel` | YES |
| U05/U08 cause preservation | YES |
| No grade mutation | YES |
| No attendance mutation | YES |
| No DB/RLS/HTTP/permission changes | YES |
| U09 regression safety | YES |
| Authorization / cross-school | YES |
| Idempotency conflict | YES |

Known conditions retained (not reopened as implementation):

```text
Parallel race verification: DESIGN-DEFERRED
C-001: Known PHPUnit suite-registration condition
```

```text
U10 HUMAN REVIEW:
ACCEPTED

U10:
CLOSED / ACCEPTED WITH CONDITIONS
```

```text
U10 remains LOCKED / UNCHANGED after this closure.
No U11 work may redesign U10.
```

---

## 3. U11 Scope

```text
REQUESTED AUTHORIZATION BOUNDARY (if later APPROVED):

MASTER PHASE 7
→ PHASE 7.2
→ BATCH 6
→ U11 — Grade entry timing policy
   EnterStudentGrade path enforcement of HD-7.2-009
```

### In scope

```text
Enforce session status gate on EnterStudentGrade (StudentGradeWriteGuard::assertCanEnter):

ALLOW enter when exam_session.status ∈ { InProgress, Completed }
DENY enter when exam_session.status ∈ { Scheduled, Cancelled }

Preserve existing Cancelled exam / Cancelled session deny
Preserve existing active-seat / academic-enrollment / current-grade / score semantics
Do NOT broaden to “all non-cancelled”
Do NOT require Present (HD-7.2-005 ≠ Grade Entry timing)
Do NOT implement CorrectStudentGrade Cancelled policy (that is U12 / HD-7.2-008)
```

### Explicitly out of scope

```text
U12 CorrectStudentGrade Cancelled fail-closed (HD-7.2-008)
U13 Room school isolation
U14 Event causation verification
U15 RLS writer-path verification
U09 Present redesign
U10 CancelExam redesign
New grade permissions
HTTP for EnterStudentGrade
Database / RLS / schema changes
Attendance mutation
Broadening enter to any status other than InProgress|Completed
```

---

## 4. Design Lock Status

| Artifact | Status for U11 |
|----------|----------------|
| Gate `7.2-U11` | Defined — **NOT AUTHORIZED** until human APPROVE |
| HD-7.2-009 | **LOCKED** — Enter ALLOW InProgress\|Completed; DENY Scheduled\|Cancelled |
| Forbidden: broaden to all non-cancelled | **LOCKED** |
| Present ≠ Grade Entry timing | **LOCKED** (HD-7.2-005 → HD-7.2-009 PASS) |
| Design Lock §24 “Modify StudentGradeWriteGuard” | Forbidden **without** unit AuthZ — **U11 is the unit that may authorize this specific enter-timing change** |
| Competing U11 design | **NONE** |

```text
U11 DESIGN:
LOCKED (HD-7.2-009)

Current implementation gap:
StudentGradeWriteGuard::assertCanEnter denies Cancelled session/exam
but does NOT yet deny Scheduled — U11 closes this gap if authorized
```

---

## 5. Business Objective

Align **EnterStudentGrade** with the locked Grade Entry timing policy so grades cannot be entered on **Scheduled** sessions, while remaining allowed on **InProgress** and **Completed**, and remaining denied on **Cancelled**.

---

## 6. State Transition

U11 is **not** an enrollment/session lifecycle transition.

It is a **write-guard precondition** on existing EnterStudentGrade:

| Session status | EnterStudentGrade (U11) |
|----------------|-------------------------|
| InProgress | **ALLOW** (other enter guards still apply) |
| Completed | **ALLOW** (other enter guards still apply) |
| Scheduled | **DENY** |
| Cancelled | **DENY** (already present; preserve) |

| Exam status Cancelled | Enter | **DENY** (already present; preserve) |

```text
No new exam_enrollments / exam_sessions status mutation.
Grade row creation remains the EnterStudentGrade success path only when allowed.
```

---

## 7. Preconditions

```text
Existing EnterStudentGrade preconditions remain:
  — school-scoped exam enrollment context found
  — grade partition exists
  — active exam seat
  — academic enrollment active
  — score / is_absent semantics
  — no CURRENT grade already exists
  — authorization for grades.create (existing)

U11 adds / tightens:
  — session status must be InProgress OR Completed
```

---

## 8. Postconditions

On ALLOW (success path unchanged except newly respecting timing):

```text
CURRENT grade inserted (existing Enter semantics)
StudentGradeEntered outbox (existing)
Idempotency store (existing when key provided)
```

On DENY (Scheduled / Cancelled / other existing denials):

```text
No grade insert
No StudentGradeEntered
No successful idempotency store for a failed attempt
Fail closed
```

---

## 9. Security / Permission

```text
Permission:
grades.create
(existing EnterStudentGrade — Gate: “existing grades.*”)

Authority:
grades_manager (has grades.create)
grades_teacher (has grades.create)
— do not invent a new role mapping under U11

School isolation:
existing EnterStudentGrade school-scoped context lookup — preserve fail-closed

Fail-closed requirements:
missing context / unauthorized / invalid session timing / cancelled → DENY

Cross-school behavior:
DENY / FAIL CLOSED (existing)

Authorization source:
Phase 7.1 EnterStudentGrade + config/security.php grades.*
Gate 7.2-U11
HD-7.2-009
```

```text
Do NOT create a new permission.
Do NOT register exam.* permissions for Enter.
```

---

## 10. School Isolation

```text
Touches:
  school_id (command + enrollment grade context)
  exam_enrollments / exam_sessions / exams (read for guard)
  student_grades (write only when enter ALLOWED)

same-school: ALLOW when authorized + guards pass
cross-school: DENY / FAIL CLOSED
missing school context: existing Enter fail-closed posture
```

No cross-school ambiguity in locked design → not blocked.

---

## 11. Fail-Closed Rules

| Condition | Behavior | Classification |
|-----------|----------|----------------|
| Session Scheduled | DENY enter | **LOCKED** (HD-7.2-009) |
| Session Cancelled | DENY enter | **LOCKED** (existing + HD-7.2-009) |
| Exam Cancelled | DENY enter | **LOCKED** (existing) |
| Session InProgress \| Completed | proceed to other guards | **LOCKED** |
| Inactive seat / inactive academic enrollment / CURRENT exists / bad score | DENY | **LOCKED** (existing Enter) |
| Enrollment context not found | DENY | **LOCKED** (existing) |
| Unauthorized grades.create | DENY | **LOCKED** (existing AuthZ) |

---

## 12. Grade Safety

```text
Does U11 read grades?
YES — existing CURRENT-grade existence check on enter

Does U11 mutate grades?
Only via existing EnterStudentGrade success path when timing ALLOWED
U11 itself is a guard policy — it does not invent a new write path

Can grades block the operation?
YES — existing CURRENT grade blocks second enter

Must ignore grades for timing?
Timing is session-status based (HD-7.2-009), not grade-history based

Must fail closed on grade lookup failure?
Preserve existing Enter fail-closed posture for context/CURRENT lookup
Do not treat lookup failure as “no CURRENT grade”
```

```text
GRADE MUTATION OUTSIDE EnterStudentGrade SUCCESS PATH:
FORBIDDEN

Auto-void / delete / rewrite grades as part of U11:
FORBIDDEN
```

---

## 13. Attendance Safety

```text
ATTENDANCE MUTATION:
FORBIDDEN
```

Enter may set `student_grades.is_absent` per existing Enter score semantics — that is **grade** absence, not attendance.mark SSOT. Do not mutate attendance records.

---

## 14. Event / Outbox

```text
Event:
StudentGradeEntered (existing EnterStudentGrade — unchanged packaging)

Cause:
N/A new cause — U11 does not introduce a new event class or cause string

Emission condition:
Real successful enter after all guards including HD-7.2-009 timing

No-event condition:
Scheduled deny
Cancelled deny
Any other assertCanEnter denial
AuthZ denial
Idempotency replay (existing replay behavior — no second enter event)

Event identity:
Existing StudentGradeEntered outbox identity

Idempotency identity:
Existing EnterStudentGrade idempotency key handling

Replay behavior:
Unchanged existing Enter semantics

Conflict behavior:
Unchanged existing Enter semantics (where applicable)
```

```text
event identity != idempotency identity — LOCKED (DR-006 / Phase 7 posture)
U11 does not merge them
```

---

## 15. Idempotency

```text
Reuse existing EnterStudentGrade idempotency
U11 does not introduce a new idempotency subsystem
Denied attempts must not record a successful enter outcome
```

---

## 16. CQRS Boundary

Intended surface **if** later authorized (do not create in this task):

```text
Existing: EnterStudentGradeCommand / EnterStudentGradeHandler / EnterStudentGradeResult
Modify: StudentGradeWriteGuard::assertCanEnter — session timing per HD-7.2-009
Possibly: ExamSessionUnavailableException (or equivalent) for Scheduled deny — reuse existing exception family if suitable
Repository: read-only context as today
Event/Outbox: existing StudentGradeEntered on success only
Tests: Enter timing matrix + regression
```

```text
Do NOT create a new Enter command.
Do NOT implement CorrectStudentGrade changes (U12).
Do NOT create files in this AuthZ-request task.
```

---

## 17. Domain Boundary

```text
Domain: Exams / Grades write guard
Bounded change: Enter timing policy only
Must not alter Present (U09), CancelExam cascade (U10), or Correct policy (U12)
```

---

## 18. Database Impact

```text
Migration: NO
Schema change: NO
RLS change: NO
Index change: NO
Constraint change: NO
Data migration: NO
```

Gate U11 DB impact = **None (guard)**.  
If implementation claims schema need → **STOP / BLOCKED**.

---

## 19. HTTP Impact

```text
HTTP implementation:
NOT PART OF U11 / NOT AUTHORIZED
```

---

## 20. Required Tests

If human later APPROVES U11, tests should include:

```text
happy path: Enter on InProgress → ALLOW
happy path: Enter on Completed → ALLOW
invalid: Enter on Scheduled → DENY (no grade / no event)
invalid: Enter on Cancelled → DENY (preserve)
permission denial (missing grades.create)
cross-school denial / missing context fail-closed
CURRENT grade still blocks second enter
no attendance.records mutation
StudentGradeEntered only on allowed success
idempotency replay unchanged for successful enter
regression: U09 Present still independent of enter timing
regression: U10 CancelExam coexistence unchanged
do NOT assert enter allowed on “any non-cancelled” (forbidden broaden)
```

```text
Parallel race verification:
NOT A REQUIRED U11 RELEASE GATE
DESIGN-DEFERRED (Phase 7.2 posture)
```

---

## 21. U09 Non-Regression

```text
U09:
LOCKED / UNCHANGED

Present does not redefine Grade Entry timing (HD-7.2-005 / HD-7.2-009).
U11 must not require Present for enter.
U11 must not modify PresentExamEnrollment*.
```

---

## 22. U10 Non-Regression

```text
U10:
LOCKED / UNCHANGED AFTER CLOSURE

No U11 authorization permits redesign of CancelExam cascade coexistence.
No deferred race verification may be silently expanded into U11 scope.
```

---

## 23. Dependencies

| Dependency | Role |
|------------|------|
| HD-7.2-009 | Authoritative enter timing set |
| EnterStudentGrade + StudentGradeWriteGuard | Enforcement surface |
| U12 / HD-7.2-008 | Separate Correct policy — do not merge |
| U09 | Non-regression; Present ≠ enter timing |
| U10 | Closed; non-regression |

---

## 24. Known Conditions

```text
U09/U10 PASS WITH CONDITIONS:
  Parallel race verification DESIGN-DEFERRED — do not expand into U11

C-001:
  PHPUnit suite-registration warning — non-blocking; do not remediate under U11 unless separately authorized

Implementation gap (evidence):
  assertCanEnter currently allows Scheduled sessions — contradicts HD-7.2-009
  U11 exists to close this gap after AuthZ
```

---

## 25. Human Decisions Required

| Topic | Status |
|-------|--------|
| HD-7.2-009 timing set | **LOCKED** — do not reopen |
| Exact exception type/message for Scheduled deny | **PROPOSED** reuse `ExamSessionUnavailableException` family or equivalent existing domain exception — **PENDING HUMAN** only if APPROVE requires amendment |
| Broaden to all non-cancelled | **REJECTED / FORBIDDEN** by lock |

```text
No new business timing options are open.
Human must still check APPROVE / REJECT / REQUEST AMENDMENT.
```

---

## 26. Implementation Readiness

```text
U11 DESIGN:
LOCKED

U11 IMPLEMENTATION READINESS:
READY

HUMAN IMPLEMENTATION AUTHORIZATION:
REQUIRED

IMPLEMENTATION:
NOT PERFORMED
```

Checklist:

| Category | Status |
|----------|--------|
| U11 identity | PASS |
| HD-7.2-009 | LOCKED |
| Permission grades.create | EXISTS |
| Guard surface identified | PASS |
| DB/RLS | NONE |
| HTTP | OUT OF SCOPE |
| U09/U10 non-regression | CLEAR |
| Conflicts | NONE blocking (gap = implementation debt, not design conflict) |
| U12 bleed | FORBIDDEN |

---

## 27. Human Implementation Authorization Request

```text
## HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

U11 implementation was authorized and executed under a separate implementation task.

Human decision recorded:

[x] APPROVE U11 IMPLEMENTATION
[ ] REJECT U11 IMPLEMENTATION
[ ] REQUEST AMENDMENT / CLARIFICATION

Decision recorded by: Human (explicit U11 implementation authorization prompt)
Date: 2026-09-12
```

---

## 28. Explicit Stop Condition

```text
STOP after this artifact.

Do NOT implement U11.
Do NOT implement U12–U15.
Do NOT modify U09 or U10.
Do NOT create migrations / RLS / permissions / routes / tests / code.

U10:
CLOSED / ACCEPTED WITH CONDITIONS

U11 AUTHORIZATION REQUEST:
CREATED

U11:
NOT IMPLEMENTED

U12–U15:
NOT AUTHORIZED / NOT IMPLEMENTED

BATCH 6:
OPEN

STOP — WAIT FOR HUMAN AUTHORIZATION
```
