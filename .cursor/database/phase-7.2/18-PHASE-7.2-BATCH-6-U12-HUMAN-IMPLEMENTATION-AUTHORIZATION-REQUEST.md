# MASTER PHASE 7 — PHASE 7.2 — BATCH 6 — U12
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST → GRANTED

Unit:
U12 — Grade correction policy (Correct path enforcement)

Gate:
7.2-U12

Phase:
7.2

Batch:
6

Mode:
IMPLEMENTATION AUTHORIZED BY HUMAN (2026-09-12)

Implementation:
AUTHORIZED (see audit artifact 19)

U09 / U10 / U11:
LOCKED AFTER CLOSURE — UNCHANGED BY THIS REQUEST

U13–U15:
NOT AUTHORIZED
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Gate row** | `05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` → **7.2-U12** |
| **Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` §12 **HD-7.2-008** |
| **Current correct guard** | `StudentGradeWriteGuard::assertCanCorrect` — **no Cancelled session/exam check yet** |
| **This document** | Request only — ≠ authorization grant |

```text
Creating this request ≠ APPROVE U12
U11 closure ≠ U12 authorization
Phase / Batch authorization ≠ U12 unit authorization
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
CLOSED / ACCEPTED WITH CONDITIONS

U11:
IMPLEMENTED
AUDITED
PASS WITH CONDITIONS
HUMAN REVIEW: ACCEPTED WITH CONDITIONS (this document §2)

U12:
IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
Human decision: APPROVE U12 IMPLEMENTATION
Audit: 19-PHASE-7.2-BATCH-6-U12-IMPLEMENTATION-AUDIT.md

U13–U15:
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 2. U11 Closure State

Reviewed artifact:

```text
.cursor/database/phase-7.2/17-PHASE-7.2-BATCH-6-U11-IMPLEMENTATION-AUDIT.md
```

Evidence consistency vs HD-7.2-009:

| Claim | Supported |
|-------|-----------|
| InProgress → ALLOW | YES |
| Completed → ALLOW | YES |
| Scheduled → DENY | YES |
| Cancelled → DENY | YES |
| Enforced in `StudentGradeWriteGuard::assertCanEnter` | YES |
| No grade / no `StudentGradeEntered` on deny | YES |
| Fail-closed | YES |
| U09 / U10 sources untouched | YES |

Known conditions retained (not reopened):

```text
Parallel race verification: DESIGN-DEFERRED
C-001: Known PHPUnit suite-registration condition
```

```text
U11 HUMAN REVIEW:
ACCEPTED

U11:
CLOSED / ACCEPTED WITH CONDITIONS
```

```text
U11 remains LOCKED / UNCHANGED after this closure.
No U12 work may redesign U11 Enter timing (HD-7.2-009).
```

---

## 3. U12 Scope

```text
REQUESTED AUTHORIZATION BOUNDARY (if later APPROVED):

MASTER PHASE 7
→ PHASE 7.2
→ BATCH 6
→ U12 — Grade correction policy
   CorrectStudentGrade path enforcement of HD-7.2-008
```

### In scope

```text
Enforce on CorrectStudentGrade (StudentGradeWriteGuard::assertCanCorrect + handler context):

FAIL CLOSED if exam_session.status = Cancelled
FAIL CLOSED if exam.status = Cancelled

Preserve existing Correct semantics when not Cancelled:
  — CURRENT grade required
  — void previous + insert replacement (existing Correct write path)
  — StudentGradeCorrected outbox (existing)
  — grades.correct authorization (existing)

Do NOT silently change Correct without this unit AuthZ (Gate forbidden behavior)
Do NOT merge U11 Enter timing into Correct
Do NOT broaden Correct deny beyond Cancelled session/exam unless locked (it is NOT)
```

### Explicitly out of scope

```text
U11 Enter timing redesign
U13 Room school isolation
U14 Event causation verification
U15 RLS writer-path verification
U09 Present / U10 CancelExam redesign
New permissions
HTTP for Correct
Database / RLS / schema changes
Attendance mutation
Denying Correct solely because session is Scheduled (NOT in HD-7.2-008)
Auto-void without Correct command
```

---

## 4. Design Lock Status

| Artifact | Status for U12 |
|----------|----------------|
| Gate `7.2-U12` | Defined — **NOT AUTHORIZED** until human APPROVE |
| HD-7.2-008 | **LOCKED** — Correct FAIL CLOSED if session OR exam Cancelled |
| Design Lock note | Policy ≠ AuthZ to modify guard without separate authorization — **U12 is that authorization** |
| Forbidden: silent guard change without AuthZ | **LOCKED** |
| Competing U12 design | **NONE** |

```text
U12 DESIGN:
LOCKED (HD-7.2-008)

Current implementation gap:
assertCanCorrect does not receive/check session or exam Cancelled status
CorrectStudentGradeHandler does not load enrollment/session/exam context for that check
→ U12 closes this gap if authorized
```

---

## 5. Business Objective

Prevent **CorrectStudentGrade** from succeeding when the related **exam session** or **parent exam** is **Cancelled**, preserving academic integrity after cancellation while leaving existing Correct behavior intact for non-cancelled contexts.

---

## 6. State Transition

U12 is **not** an enrollment/session lifecycle transition.

It is a **write-guard precondition** on existing CorrectStudentGrade:

| Condition | CorrectStudentGrade (U12) |
|-----------|---------------------------|
| Session Cancelled | **DENY / FAIL CLOSED** |
| Exam Cancelled | **DENY / FAIL CLOSED** |
| Session/Exam not Cancelled | Proceed to existing Correct guards / mutation |

```text
No new exam_sessions / exams status mutation by U12.
When ALLOWED, existing Correct still: void prior CURRENT + insert new CURRENT correction row.
When DENIED: no void, no insert, no StudentGradeCorrected.
```

---

## 7. Preconditions

```text
Existing CorrectStudentGrade preconditions remain:
  — non-empty correction reason
  — grade partition exists
  — grade found in school + academic year
  — CURRENT grade only
  — void eligibility / score semantics / correction-chain integrity
  — grades.correct authorization (existing)

U12 adds:
  — related exam_session.status ≠ Cancelled
  — related exam.status ≠ Cancelled
  — fail closed if those statuses cannot be reliably determined (lookup failure)
```

---

## 8. Postconditions

On ALLOW (success path unchanged except newly respecting Cancelled deny):

```text
Previous CURRENT voided (existing)
New CURRENT correction grade inserted (existing)
StudentGradeCorrected outbox (existing)
Idempotency store when key provided (existing)
```

On DENY (Cancelled session/exam or other existing denials):

```text
No void
No new grade insert
No StudentGradeCorrected
No successful Correct outcome recorded for the failed attempt
Fail closed
```

---

## 9. Security / Permission

```text
Permission:
grades.correct

Authority:
grades_manager (has grades.correct)
— grades_teacher does NOT have grades.correct in config/security.php (preserve)

School isolation:
existing Correct school-scoped lockByIdentity — preserve fail-closed

Cross-school behavior:
DENY / FAIL CLOSED (existing)

Fail-closed requirements:
Cancelled session/exam → DENY
missing/unauthorized → DENY
status lookup failure → DENY (do not treat as “not cancelled”)

Authorization source:
Phase 7.1 CorrectStudentGrade + config/security.php grades.correct
Gate 7.2-U12
HD-7.2-008
```

```text
Do NOT create a new permission.
Do NOT grant grades.correct to new roles under U12.
```

---

## 10. School Isolation

```text
Touches:
  school_id (command + grade lock)
  student_grades (void + insert when ALLOWED)
  exam_enrollments / exam_sessions / exams (read for Cancelled status)

same-school: ALLOW when authorized + guards pass
cross-school: DENY / FAIL CLOSED
```

No cross-school ambiguity in locked design → not blocked.

---

## 11. Fail-Closed Rules

| Condition | Behavior | Classification |
|-----------|----------|----------------|
| Session Cancelled | DENY Correct | **LOCKED** (HD-7.2-008) |
| Exam Cancelled | DENY Correct | **LOCKED** (HD-7.2-008) |
| Session/exam status lookup failure | DENY | **LOCKED** posture (fail closed; do not assume not Cancelled) |
| Non-current grade / bad chain / empty reason / etc. | DENY | **LOCKED** (existing Correct) |
| Session Scheduled (only) | Not a HD-7.2-008 deny | **LOCKED by omission** — do not invent Scheduled deny for Correct |

---

## 12. Grade Safety

```text
Does U12 read grades?
YES — existing CURRENT grade lock + chain checks

Does U12 mutate grades?
U12 itself is a deny/allow policy.
When Correct is ALLOWED, existing CorrectStudentGrade mutates (void + insert).
When Cancelled deny applies, Correct mutation MUST NOT occur.

Depends on current grade?
YES — existing Correct requires CURRENT

Depends on historical grade?
Chain integrity only (existing) — historical does not independently authorize Correct after Cancelled

Must fail closed on grade/session/exam lookup failure?
YES
```

```text
GRADE MUTATION WHEN SESSION OR EXAM IS CANCELLED:
FORBIDDEN

New automatic grade void/delete outside Correct command:
FORBIDDEN
```

---

## 13. Attendance Safety

```text
ATTENDANCE MUTATION:
FORBIDDEN
```

Correct may set `student_grades.is_absent` on the replacement row per existing Correct score semantics — that is grade absence, not attendance.mark SSOT.

---

## 14. Event / Outbox

```text
Event:
StudentGradeCorrected (existing CorrectStudentGrade — unchanged packaging)

Cause:
N/A new cause — U12 does not introduce a new event class/cause

Emission condition:
Real successful Correct after all guards including HD-7.2-008

No-event condition:
Cancelled session deny
Cancelled exam deny
Any other assertCanCorrect / handler denial
AuthZ denial
Idempotency replay (existing — no second correction event)

Event identity:
Existing StudentGradeCorrected outbox identity

Idempotency identity:
Existing CorrectStudentGrade idempotency key handling

Replay / conflict:
Unchanged existing Correct semantics
```

```text
event identity != idempotency identity — LOCKED
```

---

## 15. Idempotency

```text
Reuse existing CorrectStudentGrade idempotency
U12 does not introduce a new idempotency subsystem
Denied Cancelled attempts must not store a successful Correct outcome
```

---

## 16. CQRS Boundary

Intended surface **if** later authorized (do not create in this task):

```text
Existing: CorrectStudentGradeCommand / Handler / Result
Modify: StudentGradeWriteGuard::assertCanCorrect — add Cancelled session/exam fail-closed
Likely: CorrectStudentGradeHandler loads exam enrollment grade context (or session/exam status) before assertCanCorrect
Reuse: ExamSessionUnavailableException::cancelled (or equivalent domain exception)
Event/Outbox: existing StudentGradeCorrected on success only
Tests: Correct Cancelled matrix + regression
```

```text
Do NOT create a new Correct command.
Do NOT modify Enter timing (U11).
Do NOT create files in this AuthZ-request task.
```

---

## 17. Domain Boundary

```text
Domain: Exams / Grades write guard (Correct path)
Bounded change: Cancelled session/exam deny on Correct only
Must not alter Present (U09), CancelExam cascade (U10), or Enter timing (U11)
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

Gate U12 DB impact = **None (guard)**.  
If implementation claims schema need → **STOP / BLOCKED**.

---

## 19. HTTP Impact

```text
HTTP implementation:
NOT PART OF U12 / NOT AUTHORIZED
```

Existing `/api/v1/grades/{id}/correct` (if present) would inherit guard behavior only after AuthZ + implementation — U12 does not authorize new HTTP.

---

## 20. Required Tests

If human later APPROVES U12, tests should include:

```text
happy path: Correct when session/exam not Cancelled → ALLOW (existing semantics)
invalid: Correct when session Cancelled → DENY (no void / no insert / no event)
invalid: Correct when exam Cancelled → DENY
permission denial (missing grades.correct)
cross-school denial / missing context fail-closed
status lookup failure → fail closed
no attendance.records mutation
StudentGradeCorrected only on allowed success
idempotency replay unchanged for successful Correct
regression: U09 Present unchanged
regression: U10 CancelExam coexistence unchanged
regression: U11 Enter timing unchanged (Scheduled still denied on Enter; Correct not given Enter timing rules)
do NOT assert Correct denied solely for Scheduled (not in HD-7.2-008)
```

```text
Parallel race verification:
NOT A REQUIRED U12 RELEASE GATE
DESIGN-DEFERRED (Phase 7.2 posture)
```

---

## 21. U09 Non-Regression

```text
U09:
LOCKED / UNCHANGED

U12 must not modify PresentExamEnrollment*.
```

---

## 22. U10 Non-Regression

```text
U10:
LOCKED / UNCHANGED

U12 must not redesign CancelExam cascade coexistence.
```

---

## 23. U11 Non-Regression

```text
U11:
LOCKED / UNCHANGED AFTER CLOSURE

U12 must not alter assertCanEnter / HD-7.2-009 Enter timing.
Correct Cancelled policy ≠ Enter InProgress|Completed policy.
```

---

## 24. Dependencies

| Dependency | Role |
|------------|------|
| HD-7.2-008 | Authoritative Correct Cancelled fail-closed |
| CorrectStudentGrade + StudentGradeWriteGuard::assertCanCorrect | Enforcement surface |
| U11 / HD-7.2-009 | Separate Enter policy — do not merge |
| U09 / U10 | Non-regression only |

---

## 25. Known Conditions

```text
U09/U10/U11 PASS WITH CONDITIONS:
  Parallel race verification DESIGN-DEFERRED — do not expand into U12

C-001:
  PHPUnit suite-registration warning — non-blocking

Implementation gap (evidence):
  assertCanCorrect lacks Cancelled session/exam checks
  Handler does not currently supply session/exam status to the guard
  U12 exists to close this gap after AuthZ
```

---

## 26. Human Decisions Required

| Topic | Status |
|-------|--------|
| HD-7.2-008 Cancelled fail-closed | **LOCKED** — do not reopen |
| Deny Correct on Scheduled-only | **NOT in lock** — do not invent |
| Exception type for Cancelled Correct | **PROPOSED** reuse `ExamSessionUnavailableException::cancelled` — amendment only if human rejects reuse |

```text
No new business Correct options are open beyond HD-7.2-008.
Human must still check APPROVE / REJECT / REQUEST AMENDMENT.
```

---

## 27. Implementation Readiness

```text
U12 DESIGN:
LOCKED

U12 IMPLEMENTATION READINESS:
READY

HUMAN IMPLEMENTATION AUTHORIZATION:
REQUIRED

IMPLEMENTATION:
NOT PERFORMED
```

Checklist:

| Category | Status |
|----------|--------|
| U12 identity | PASS |
| HD-7.2-008 | LOCKED |
| Permission grades.correct | EXISTS |
| Guard/handler surface identified | PASS |
| DB/RLS | NONE |
| HTTP | OUT OF SCOPE |
| U09/U10/U11 non-regression | CLEAR |
| Conflicts | NONE blocking (gap = implementation debt) |
| Silent guard change without AuthZ | FORBIDDEN until APPROVE |

---

## 28. Human Implementation Authorization Request

```text
## HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

U12 implementation was authorized and executed under a separate implementation task.

Human decision recorded:

[x] APPROVE U12 IMPLEMENTATION
[ ] REJECT U12 IMPLEMENTATION
[ ] REQUEST AMENDMENT / CLARIFICATION

Decision recorded by: Human (explicit U12 implementation authorization prompt)
Date: 2026-09-12
```

---

## 29. Explicit Stop Condition

```text
STOP after this artifact.

Do NOT implement U12.
Do NOT implement U13–U15.
Do NOT modify U09, U10, or U11.
Do NOT create migrations / RLS / permissions / routes / tests / code.

U11:
CLOSED / ACCEPTED WITH CONDITIONS

U12 AUTHORIZATION REQUEST:
CREATED

U12:
NOT IMPLEMENTED

U13–U15:
NOT AUTHORIZED / NOT IMPLEMENTED

BATCH 6:
OPEN

STOP — WAIT FOR HUMAN AUTHORIZATION
```
