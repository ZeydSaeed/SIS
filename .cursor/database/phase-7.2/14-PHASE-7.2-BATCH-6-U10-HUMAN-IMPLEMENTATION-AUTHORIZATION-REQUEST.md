# MASTER PHASE 7 — PHASE 7.2 — BATCH 6 — U10
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST → GRANTED

Unit:
U10 — CancelExam cascade coexistence

Phase:
7.2

Batch:
6

Mode:
IMPLEMENTATION AUTHORIZED BY HUMAN (2026-09-12)

Implementation:
AUTHORIZED (see audit artifact 15)

U09:
UNCHANGED / MUST REMAIN UNCHANGED

U11–U15:
NOT AUTHORIZED

Database Changes:
NONE REQUESTED BY THIS DOCUMENT

RLS Changes:
NONE

Permission Changes:
NONE

HTTP Changes:
NONE
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Gate row** | `05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` → **7.2-U10** |
| **Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` (§8 HD-7.2-002D; §19 causation; §24 boundary) |
| **Human decisions** | HD-7.2-002A / 002B / **002D** (D1); HD-7.2-011 / DD-017 |
| **U09 baseline** | Implemented + audited — PASS WITH CONDITIONS |
| **This document** | Request only — ≠ authorization grant |

```text
Creating this request ≠ APPROVE U10
U09 success ≠ U10 authorization
Phase / Batch authorization ≠ U10 unit authorization
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
IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
Human decision: APPROVE U10 IMPLEMENTATION
Audit: 15-PHASE-7.2-BATCH-6-U10-IMPLEMENTATION-AUDIT.md

U11–U15:
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 2. Scope

```text
REQUESTED AUTHORIZATION BOUNDARY (if later APPROVED):

MASTER PHASE 7
→ PHASE 7.2
→ BATCH 6
→ U10 — CancelExam cascade coexistence
```

### In scope (design)

```text
Coexistence of Phase 7.1 CancelExam with Phase 7.2 dedicated cancel writers:
  U05 CancelExamSession (cause exam_session_cancel)
  U08 CancelExamEnrollment (cause exam_enrollment_cancel)

Enforce / verify HD-7.2-002D (D1):
  CancelExam skips sessions already Cancelled
  CancelExam cascades remaining open sessions (Scheduled|InProgress → Cancelled)
  Withdraw remaining active seats under the exam
  No double-cancel / duplicate cascade semantics for already-terminal cascade targets

Preserve cascade child event cause:
  exam_cancel

Preserve Phase 7.1 CancelExam authority:
  Permission exam.cancel → grades_manager
  No redesign of CancelExam business model
  No grade mutation
  No attendance mutation
```

### Explicitly out of scope

```text
U09 PresentExamEnrollment changes
U11 Grade entry timing enforcement
U12 Grade correction policy enforcement
U13 Room school isolation (unless already covered elsewhere)
U14 Event causation verification as a separate unit (may consume U10 evidence; must not absorb U14)
U15 RLS writer-path verification
U16 Permission registration
Redesign CancelExam / new CancelExam command family
exam.session.cancel permission
HTTP for CancelExam or Phase 7.2 writers
Database / RLS / schema / permission catalog mutation
Batch 6 closure
```

---

## 3. Design Lock Status

| Artifact | Status for U10 |
|----------|----------------|
| Gate `7.2-U10` | Defined — **NOT AUTHORIZED** until human APPROVE |
| HD-7.2-002D = D1 skip already Cancelled | **LOCKED** |
| CancelExam remains authoritative (Phase 7.1) | **LOCKED** |
| No grade mutation on cancel | **LOCKED** |
| Cause `exam_cancel` for CancelExam cascade child events | **LOCKED** (HD-7.2-011 / §19) |
| Distinguish from `exam_session_cancel` / `exam_enrollment_cancel` | **LOCKED** |
| Design Lock §24 “Modify CancelExam” | Forbidden **without** unit AuthZ — **U10 is the unit that may authorize limited coexistence work only** |
| Competing U10 design | **NONE found** |

```text
U10 DESIGN:
LOCKED (coexistence semantics)

Unresolved packaging inventing new business rules:
NONE identified for core D1 / cause / no-double-semantics
```

---

## 4. U10 Business Objective

Ensure Phase 7.1 **CancelExam** continues to work correctly **after** Phase 7.2 dedicated session/enrollment cancel paths exist:

```text
Operators may cancel individual sessions (U05) or seats (U08),
then later cancel the parent Exam (CancelExam).

CancelExam must:
  — skip sessions already Cancelled (HD-7.2-002D)
  — cancel remaining open sessions
  — withdraw remaining active seats
  — emit cascade events with cause=exam_cancel
  — avoid re-cancelling / re-emitting for already-handled cascade targets
  — never mutate grades or attendance
```

---

## 5. Exact State Transition

U10 is **not** a new enrollment/session status transition unit.

It is a **parent-exam cancel coexistence** unit over existing transitions:

| Object | Transition under CancelExam (when permitted) | U10 coexistence rule |
|--------|-----------------------------------------------|----------------------|
| Exam | → Cancelled | Unchanged Phase 7.1 |
| Session Scheduled \| InProgress | → Cancelled | Apply |
| Session **already Cancelled** | — | **SKIP** (HD-7.2-002D) — no second cancel mutation / no duplicate session-cancel cascade event for that session |
| Session Completed | — | Remains Completed (not “open”) — existing cascade query intent |
| Seat Registered \| Confirmed \| Present | → Withdrawn | Apply for remaining actives |
| Seat already Withdrawn / Absent | — | Not selected by active-seat withdraw (no re-withdraw) |

---

## 6. Preconditions

```text
Actor authorized with exam.cancel in school scope
SchoolContext present and matching
Exam exists in school
ExamCancelGuard allows cancel (Phase 7.1 rules — CURRENT grade / completed-session guards unchanged)
Idempotency key present
```

Coexistence preconditions (scenarios U10 must cover):

```text
Zero prior U05/U08 — full cascade as Phase 7.1
Prior U05 on some sessions — those sessions Cancelled; remaining open sessions cascade
Prior U08 on some seats — those seats Withdrawn; remaining actives withdraw
Mix of Cancelled + open sessions under one exam
```

---

## 7. Postconditions

```text
Exam status = Cancelled (when cancel succeeds)
Previously Cancelled sessions remain Cancelled (no reopen; no second cancel event)
Newly cascade-cancelled sessions = Cancelled with cause=exam_cancel events
Remaining active seats withdrawn with cause=exam_cancel enrollment-cancel events
Already Withdrawn / Absent seats unchanged by withdraw selection
No grade rows created/updated/deleted
No attendance mutation
Idempotent replay of same CancelExam key returns cached result without duplicate success events
```

---

## 8. Security / Permission

```text
Permission:
exam.cancel

Authority:
grades_manager

Decision source:
Phase 7.1 HD-001;
.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md §5.4;
Gate row 7.2-U10;
config/security.php (existing)
```

```text
Do NOT create a new permission.
Do NOT use exam.session.update / exam.enrollment.cancel for CancelExam.
Do NOT introduce exam.session.cancel.
```

Unauthorized / wrong-school / missing SchoolContext → **FAIL CLOSED** (existing CancelExam authority pattern).

---

## 9. School Isolation

U10 touches (read/mutate via existing CancelExam path):

```text
school_id (command + SchoolContext)
exams.exams
exams.exam_sessions
exams.exam_enrollments
(grades: read-only CURRENT-grade guard via existing ExamCancelGuard — no mutation)
attendance: none
```

```text
same-school: ALLOW when authorized
cross-school: DENY / FAIL CLOSED
missing school context: FAIL CLOSED
```

No cross-school ambiguity in the locked design → **not blocked** on isolation.

---

## 10. Fail-Closed Rules

| Condition | Behavior | Status |
|-----------|----------|--------|
| Missing idempotency key | DENY | LOCKED (Phase 7.1/7.2 writer pattern) |
| Missing / mismatched SchoolContext | DENY | LOCKED |
| Missing exam.cancel / unauthorized role | DENY | LOCKED |
| Exam not found in school | DENY | LOCKED |
| ExamCancelGuard blocks (e.g. CURRENT grade / completed-session rules) | DENY — no partial cascade | LOCKED (Phase 7.1) |
| Idempotency key + different payload | CONFLICT | LOCKED |
| Lookup/persistence failure mid-transaction | ROLLBACK | LOCKED pattern |

---

## 11. Grade Safety

```text
Does U10 mutate grades?
NO — LOCKED (CancelExam / cascade: no grade mutation)

Does it only read grades?
YES — via existing CancelExam CURRENT-grade / related guards

Can CURRENT grade block CancelExam?
YES — existing Phase 7.1 ExamCancelGuard (unchanged by U10 redesign)

Can historical grade block CancelExam?
Per Phase 7.1 CancelExam rules (do not invent new U10 grade policy)

What if grade lookup fails?
FAIL CLOSED — preserve existing CancelExam fail-closed posture; do not treat failure as “no grade”
```

```text
U10 must NOT auto-void, delete, correct, or rewrite grades.
```

---

## 12. Attendance Safety

```text
No attendance mutation — LOCKED
Do not introduce is_absent flips
Do not create/update/delete attendance records
```

---

## 13. Event / Outbox

```text
Event (session child):
ExamSessionCancelled

Event (enrollment child):
ExamEnrollmentCancelled

Event (exam):
ExamCancelled

Cause (cascade children from CancelExam):
exam_cancel

When emitted:
On real CancelExam success for each session/seat actually mutated in this cancel
Plus ExamCancelled for the exam

When NOT emitted:
Already Cancelled sessions skipped (no second ExamSessionCancelled for that session from this cascade)
Seats not withdrawn (already inactive) — no enrollment-cancel event
AuthZ / guard DENY
Idempotency replay
Idempotency conflict
```

```text
Payload identity: domain event outbox identity
Idempotency identity: CancelExam idempotency key + fingerprint (exam_id / school)
event identity != idempotency identity (DR-006) — LOCKED
```

```text
Replay behavior: return cached CancelExam result; no duplicate success outbox
Conflict behavior: same key + different fingerprint → conflict / fail closed
```

Distinguish from:

```text
U05: cause = exam_session_cancel (session + seat events)
U08: cause = exam_enrollment_cancel
U10/CancelExam cascade: cause = exam_cancel
```

---

## 14. Idempotency

```text
Command name (existing):
Exams.CancelExam

same key + same payload → REPLAY
same key + different payload → CONFLICT

Business coexistence:
Skipping already-Cancelled sessions is NOT a second cancel
Already-Withdrawn seats are not re-withdrawn
```

---

## 15. CQRS Boundary

Expected surface **if** later authorized (create/modify only as needed — do not invent parallel architecture):

```text
Existing: CancelExamCommand / CancelExamHandler / CancelExamResult
Existing: ExamCancelGuard
Existing: ExamRepositoryInterface::cancelOpenSessionsForExam
Existing: ExamRepositoryInterface::withdrawActiveEnrollmentsForExam
Existing: Outbox ExamSessionCancelled / ExamEnrollmentCancelled / ExamCancelled
Possible minimal adjustments (ONLY if gaps vs locked D1/cause/no-double-semantics):
  — explicit cause='exam_cancel' on staged cascade children (defaults already exam_cancel)
  — coexistence-focused Feature tests
  — narrow mutation extraction ONLY if required for architecture fitness (not a redesign)

Do NOT create Present/U09-style new command for CancelExam.
Do NOT create files in this AuthZ-request task.
```

---

## 16. Domain Boundary

```text
Domain: Exams
Parent aggregate path: Exam cancel cascade
Must not redesign Phase 7.1 CancelExam lifecycle
Must not alter U05/U08 locked matrices
Must not open Present / grade-entry / room-isolation units (U09/U11/U13)
```

---

## 17. Database Impact

```text
New migration: NO
Schema change: NO
RLS change: NO
Index change: NO
Constraint change: NO
Data migration: NO
```

Evidence: Gate U10 DB impact = behavior on existing `exam_sessions` / `exam_enrollments` / `exams` only.  
If implementation later claims a schema need → **STOP / BLOCKED** (contradicts lock).

---

## 18. HTTP Impact

```text
HTTP implementation:
NOT PART OF U10 / NOT AUTHORIZED
```

Phase 7.2 writers remain HTTP-deferred; CancelExam HTTP is out of U10 scope.

---

## 19. Required Tests (design only — DO NOT CREATE NOW)

If human later APPROVES U10, tests should include:

```text
happy path: CancelExam with all open sessions → cascade cancel + withdraw + cause=exam_cancel
U05 then CancelExam: already Cancelled session skipped; remaining open sessions cancelled
U08 then CancelExam: withdrawn seat not re-emitted; remaining actives withdrawn
mixed sessions under one exam: Cancelled + Scheduled/InProgress
Completed session not treated as open cascade target
permission denial (missing exam.cancel / wrong role)
cross-school denial
missing SchoolContext fail-closed
CURRENT-grade CancelExam guard still fail-closed (no mutation)
no attendance mutation
event cause=exam_cancel on cascade children
no second cascade event for skipped Cancelled session
idempotent replay
idempotency conflict
transaction rollback on mid-failure (where established pattern allows)
U05/U08 regression unchanged
U09 regression unchanged (PresentExamEnrollment)
```

```text
Parallel race verification:
NOT A REQUIRED U10 RELEASE GATE
DESIGN-DEFERRED (same Phase 7.2 posture as U08/U09)
Sequential coexistence coverage: REQUIRED if authorized
```

---

## 20. U09 Non-Regression Contract

```text
U09 must remain unchanged unless a formally approved dependency
requires a change.

No U09 regression is authorized by this document.

U09's deferred parallel-race verification must not be silently
converted into an implementation requirement for U10.
```

```text
DEPENDENCY BLOCKER on U09: NONE identified
U10 does not require PresentExamEnrollment changes
```

---

## 21. Dependencies

| Dependency | Role |
|------------|------|
| Phase 7.1 CancelExam | Authoritative cascade writer — coexistence target |
| U05 CancelExamSession | Prior dedicated session cancel |
| U08 CancelExamEnrollment | Prior dedicated seat cancel |
| HD-7.2-002D | Skip already Cancelled |
| HD-7.2-011 / DR-006 | Cause + event≠idemp identity |
| U09 | Non-regression only |
| U14 | Separate causation verification unit — do not merge into U10 |

---

## 22. Known Conditions

```text
U09 PASS WITH CONDITIONS:
  Parallel race verification NOT FULLY VERIFIED (design-deferred)
  Must not drive U10 scope expansion

CancelExam cascade skip behavior:
  cancelOpenSessionsForExam already selects only Scheduled|InProgress
  → D1 skip of Cancelled sessions appears present in current code
  U10 AuthZ would still authorize verification + any minimal gap closure

ExamSessionCancelled / ExamEnrollmentCancelled:
  default cause already = exam_cancel
  CancelExamHandler currently relies on defaults (no explicit cause argument)
  U05/U08 pass explicit distinct causes

PHPUnit suite-registration warnings (C-001):
  Non-blocking historical condition — do not remediate under U10 unless separately authorized
```

---

## 23. Human Decisions Required

| ID | Question | Options | Status |
|----|----------|---------|--------|
| — | Core D1 / cause / no grade mutation | Already LOCKED | No reopen |
| HD-U10-SCOPE | If APPROVED, may U10 make **minimal** CancelExam code adjustments (e.g. explicit `cause: exam_cancel`) when tests prove a gap, or **tests-only** verification? | A Tests-only B Minimal coexistence fixes allowed C Amendment | **PENDING HUMAN** (answered by APPROVE/REJECT/AMEND below) |

```text
No new business transition decisions are required for D1 itself.
Human must still check APPROVE / REJECT / REQUEST AMENDMENT.
```

---

## 24. Implementation Readiness

```text
U10 DESIGN:
LOCKED

U10 IMPLEMENTATION READINESS:
READY

HUMAN IMPLEMENTATION AUTHORIZATION:
REQUIRED

IMPLEMENTATION:
NOT PERFORMED
```

Checklist:

| Category | Status |
|----------|--------|
| U10 identity | PASS |
| HD-7.2-002D | LOCKED |
| Permission exam.cancel | EXISTS |
| Grade mutation forbidden | LOCKED |
| Attendance mutation forbidden | LOCKED |
| Event cause exam_cancel | LOCKED |
| DB/RLS change | NONE |
| HTTP | OUT OF SCOPE |
| U09 non-regression | CLEAR |
| Conflicts | NONE blocking |
| Redesign CancelExam | FORBIDDEN |

---

## 25. Human Authorization Request

```text
## HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

U10 implementation was authorized and executed under a separate implementation task.

Human decision recorded:

[x] APPROVE U10 IMPLEMENTATION
[ ] REJECT U10 IMPLEMENTATION
[ ] REQUEST AMENDMENT / CLARIFICATION

Decision recorded by: Human (explicit U10 implementation authorization prompt)
Date: 2026-09-12
```

---

## 26. Explicit Stop Condition

```text
STOP after this artifact.

Do NOT implement U10.
Do NOT modify U09.
Do NOT implement U11–U15.
Do NOT create migrations / RLS / permissions / routes / tests / code.

U10 AUTHORIZATION REQUEST: CREATED
Implementation: NOT PERFORMED
U09: UNCHANGED
U11–U15: NOT AUTHORIZED / NOT IMPLEMENTED
BATCH 6: OPEN

STOP — WAIT FOR HUMAN AUTHORIZATION
```
