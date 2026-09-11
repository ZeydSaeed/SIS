# MASTER PHASE 7 — PHASE 7.2 — DESIGN LOCK

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle — Design Lock |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Document Type** | DESIGN LOCK / DESIGN SPECIFICATION LOCK |
| **Date** | 2026-09-12 |
| **Status** | **APPROVED / AMENDED / LOCKED** |
| **Current revision** | **DL-7.2-U08-001** (Batch 6 U08 CancelExamEnrollment decisions) |
| **Implementation** | **NOT AUTHORIZED** |

```text
THIS DOCUMENT = frozen design specification
≠ Implementation Authorization
≠ Batch 6 authorization
≠ U08 implementation authorization
≠ permission catalog mutation
≠ migration / RLS / HTTP / application change
≠ Implementation Authorization Gate
```

### Revision History

| Revision | Date | Summary | Approval evidence |
|----------|------|---------|-------------------|
| **DL-7.2-BASE** | 2026-09-12 | Initial Phase 7.2 Design Lock (HD-7.2-001…014 package) | `04A` / `04B` |
| **DL-7.2-U08-001** | 2026-09-12 | U08 CancelExamEnrollment: HD-U08-001=A, HD-U08-002=A, HD-U08-003=B; explicit U08 matrix; Cancelled-session rule; U05/U08 concurrency | `07` resolution + `08` approval record |

```text
Historical decisions are preserved.
DL-7.2-U08-001 amends CancelExamEnrollment semantics only.
It does NOT authorize Batch 6 or U08 implementation.
```

---

## 2. Authorization / Scope

### What this lock authorizes

```text
DESIGN SPECIFICATION LOCK ONLY
The Phase 7.2 design is frozen as specified below.
```

### What this lock does NOT authorize

```text
Implementation = NOT AUTHORIZED
Database changes = NOT AUTHORIZED
Application changes = NOT AUTHORIZED
Permission registration = NOT AUTHORIZED
Role changes = NOT AUTHORIZED
RLS changes = NOT AUTHORIZED
Migrations = NOT AUTHORIZED
HTTP changes = NOT AUTHORIZED
Grade handler changes = NOT AUTHORIZED
StudentGradeWriteGuard changes = NOT AUTHORIZED
CancelExam changes = NOT AUTHORIZED
CancelExamSession changes = NOT AUTHORIZED
```

```text
The next phase requires a separate HUMAN IMPLEMENTATION AUTHORIZATION GATE.
This Design Lock does not authorize implementation.
```

### In-scope design surface (writers — design only)

```text
CreateExamSession
UpdateExamSession
OpenExamSession
CloseExamSession
CancelExamSession

CreateExamEnrollment
UpdateExamEnrollment
CancelExamEnrollment

Confirmed → Present (via exam.enrollment.present)
```

---

## 3. Authoritative Sources

| Artifact | Role |
|----------|------|
| `.cursor/database/phase-7.2/01-PHASE-7.2-READINESS-DISCOVERY-DESIGN-AUDIT.md` | Discovery baseline |
| `.cursor/database/phase-7.2/02-PHASE-7.2-DESIGN-DECISION-REGISTER.md` | Decision register |
| `.cursor/database/phase-7.2/03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md` | Human decision framing |
| `.cursor/database/phase-7.2/04A-PHASE-7.2-HUMAN-APPROVAL-BALLOT.md` | Ballot with HUMAN APPROVED selections |
| `.cursor/database/phase-7.2/04B-PHASE-7.2-HUMAN-DECISION-APPROVAL-RECORD.md` | Approval reconciliation |
| `.cursor/database/phase-7.2/07-PHASE-7.2-BATCH-6-U08-CANCEL-EXAM-ENROLLMENT-HUMAN-DECISION-RESOLUTION.md` | U08 blocking-decision ballot |
| `.cursor/database/phase-7.2/08-PHASE-7.2-BATCH-6-U08-HUMAN-DECISION-APPROVAL-RECORD.md` | U08 HD-U08-001/002/003 approval (DL-7.2-U08-001) |
| `.cursor/database/phase-7.1/18-PHASE-7.1-EXAM-ADMINISTRATION-FINAL-CLOSURE-GATE.md` | Phase 7.1 CLOSED |
| `.cursor/database/phase-7.1/14-…HD-001-SECURITY-DECISION-AMENDMENT.md` | exam.create/update/cancel → grades_manager |
| `.cursor/database/phase-7.1/10A-…HUMAN-DECISION-RESOLUTION.md` | HD-004 / HD-005 / HD-006 |
| `.cursor/database/phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md` | Master Phase 7 vocabulary / DRs |

---

## 4. Human Decision Evidence

### Precondition verification (from 04B)

| Metric | Required | Evidenced |
|--------|----------|-----------|
| Human decision groups | 13 | **13** |
| Human decision items | 15 | **15** |
| Human approvals evidenced | 15 | **15** |
| Critical unresolved | 0 | **0** |
| High unresolved | 0 | **0** |
| Medium unresolved | 0 | **0** |
| Conflicts | 0 | **0** |
| Partial decisions | 0 | **0** |
| Unauthorized inference | 0 | **0** |
| Dependency conflicts | 0 | **0** |
| Phase 7.1 conflicts | 0 | **0** |

```text
PRECONDITION = PASS
Counting rule: HD-7.2-002 = three items (002A, 002B, 002D)
→ groups 13 / items 15
```

### Locked human selections (summary)

| HD | Locked selection |
|----|------------------|
| HD-7.2-001 | `grades_manager` owns seven administration permissions |
| HD-7.2-002A | A1 — already Cancelled = idempotent no-op |
| HD-7.2-002B | B1 — withdraw active seats atomically |
| HD-7.2-002D | D1 — CancelExam skips already Cancelled sessions |
| HD-7.2-003 | FAIL CLOSED if ANY CURRENT grade on session |
| HD-7.2-004 | Register exactly the seven names; no `exam.session.cancel` |
| HD-7.2-005 | Actor `grades_manager`; permission `exam.enrollment.present` |
| HD-7.2-006 | ALLOW MULTIPLE sessions per Exam + Subject |
| HD-7.2-007 | Move/Reassign FORBIDDEN |
| HD-7.2-008 | Correct FAIL CLOSED if session OR exam Cancelled |
| HD-7.2-009 | Enter ALLOW InProgress\|Completed; DENY Scheduled\|Cancelled |
| HD-7.2-010 | Hard room/time overlap DEFERRED |
| HD-7.2-012 | Seat number optional free-text |
| HD-7.2-013 | Fail-closed room→branch→school match when `room_id` set |
| HD-7.2-014 | School-local civil date/time |
| **HD-U08-001** | **A** — Already Withdrawn / repeat CancelExamEnrollment = **IDEMPOTENT NO-OP** (DL-7.2-U08-001) |
| **HD-U08-002** | **A** — Absent → Withdrawn = **FORBIDDEN / FAIL CLOSED** (DL-7.2-U08-001) |
| **HD-U08-003** | **B** — Cancelled session: U08 **ALLOWED only for still-active seats** + DR-002 (DL-7.2-U08-001) |
| HD-U08-004 | Fingerprint = existing Phase 7.2 enrollment-writer pattern (no new business decision) |

Approval date recorded in ballot/approval record: **2026-09-12**.

---

## 5. Permission Model

### 5.1 Seven Phase 7.2 administration permissions (HD-7.2-001 / HD-7.2-004)

Owned by **`grades_manager`**:

```text
exam.session.create
exam.session.update
exam.session.open
exam.session.close
exam.enrollment.create
exam.enrollment.update
exam.enrollment.cancel
```

HD-7.2-004 freezes catalog *design* as exactly these seven administration names.

```text
HD-7.2-004 does NOT describe eight permissions.
Permission catalog mutation remains NOT AUTHORIZED by this Design Lock.
```

### 5.2 Dedicated Present permission (HD-7.2-005)

```text
Permission: exam.enrollment.present
Owner:      grades_manager
```

```text
DISTINGUISH:
  7 Phase 7.2 administration permissions (HD-7.2-001 / HD-7.2-004)
+ 1 dedicated Present permission (HD-7.2-005)

Do NOT collapse Present into exam.enrollment.update.
Do NOT invent exam.session.cancel.
```

### 5.3 Forbidden permission

```text
exam.session.cancel = FORBIDDEN (HD-005 — not reopened)

CancelExamSession uses exam.session.update
```

### 5.4 Phase 7.1 ownership (unchanged)

```text
exam.create / exam.update / exam.cancel → grades_manager (HD-001)
HD-001 does NOT substitute for Phase 7.2 session/enrollment permissions.
```

---

## 6. Exam Session Lifecycle

### States (existing enums — frozen)

```text
Scheduled (1)
→ InProgress (2)
→ Completed (3)     [terminal]
→ Cancelled (4)     [terminal]
```

### Approved commands

| Command | Meaning | Permission (design) |
|---------|---------|---------------------|
| CreateExamSession | Create subject session under Exam | `exam.session.create` |
| UpdateExamSession | Allowlisted metadata while Scheduled (DR-003) | `exam.session.update` |
| OpenExamSession | Scheduled → InProgress | `exam.session.open` |
| CloseExamSession | InProgress → Completed | `exam.session.close` |
| CancelExamSession | Scheduled\|InProgress → Cancelled | `exam.session.update` |

### Frozen transition rules

| From | To | Allowed |
|------|----|---------|
| Scheduled | InProgress | **YES** — OpenExamSession |
| InProgress | Completed | **YES** — CloseExamSession |
| Scheduled \| InProgress | Cancelled | **YES** — CancelExamSession (or CancelExam cascade) |
| Scheduled | Completed | **FORBIDDEN** in Phase 7.2 (skip deferred) |
| Completed | * | **FORBIDDEN** |
| Cancelled | * | **FORBIDDEN** (no reopen) |

### Academic year

```text
Session inherits academic year from parent Exam.
No denormalized academic_year_id on exam_sessions.
```

### Not introduced

```text
CompleteExam
Restore
Move/Reassign
```

---

## 7. Exam Enrollment Lifecycle

### States (existing enums — frozen)

```text
Registered (1)
→ Confirmed (2)
→ Present (3)
→ Absent (4)        [inactive seat]
→ Withdrawn (5)     [inactive seat]

isActiveSeat = Registered | Confirmed | Present
```

### Approved commands

| Command | Meaning | Permission (design) |
|---------|---------|---------------------|
| CreateExamEnrollment | Seat → Registered | `exam.enrollment.create` |
| UpdateExamEnrollment | Narrow allowlisted transitions / fields (DR-003) | `exam.enrollment.update` |
| CancelExamEnrollment | Active → Withdrawn (DR-002); see §8 U08 matrix (DL-7.2-U08-001) | `exam.enrollment.cancel` |
| Present transition | Confirmed → Present | `exam.enrollment.present` |

### CreateExamEnrollment (frozen)

```text
enrollment.academic_year_id MUST equal exam.academic_year_id — FAIL CLOSED
Session not Cancelled
UNIQUE(exam_session_id, enrollment_id)
Active academic enrollment
School consistency via composites / SchoolContext
Initial status = Registered
```

### Frozen transition rules

| From | To | Allowed | Authority |
|------|----|---------|-----------|
| (new) | Registered | YES | CreateExamEnrollment |
| Registered | Confirmed | YES | UpdateExamEnrollment |
| Confirmed | Present | YES | `exam.enrollment.present` (HD-7.2-005) |
| Registered \| Confirmed \| Present | Absent | YES | UpdateExamEnrollment |
| Registered \| Confirmed \| Present | Withdrawn | YES | **U08** CancelExamEnrollment **or** **U05**/CancelExam cascade |
| Absent | Withdrawn | **FORBIDDEN** | HD-U08-002 = A (DL-7.2-U08-001) |
| Withdrawn | Withdrawn | **IDEMPOTENT NO-OP** (U08) | HD-U08-001 = A (DL-7.2-U08-001) |
| Absent \| Withdrawn | active | **FORBIDDEN** | Restore FORBIDDEN |
| Session A → Session B | — | **FORBIDDEN** | HD-7.2-007 |

### U08 CancelExamEnrollment transition matrix (DL-7.2-U08-001 — authoritative)

| From | To | U08 |
|------|-----|-----|
| Registered | Withdrawn | **ALLOWED** (DR-002) |
| Confirmed | Withdrawn | **ALLOWED** (DR-002) |
| Present | Withdrawn | **ALLOWED** (DR-002) |
| Absent | Withdrawn | **FORBIDDEN** |
| Withdrawn | Withdrawn | **IDEMPOTENT NO-OP** |
| Withdrawn | Registered / Confirmed / Present | **FORBIDDEN** |

```text
Ownership distinction (LOCKED):

U05 CancelExamSession  = session-cancellation cascade
  → session → Cancelled
  → withdraw ACTIVE seats only (Registered|Confirmed|Present)
  → ExamEnrollmentCancelled cause = exam_session_cancel
  → Absent seats SURVIVE (intentional)

U08 CancelExamEnrollment = manual enrollment cancellation
  → ExamEnrollmentCancelled cause = exam_enrollment_cancel
  → does NOT cancel the session
  → U07 MUST NOT provide * → Withdrawn
```

---

## 8. Cancellation Semantics

### CancelExamSession (HD-7.2-002 package)

| Sub-decision | Locked rule |
|--------------|-------------|
| **HD-7.2-002A** | Already Cancelled → **idempotent no-op** |
| **HD-7.2-002B** | Active seats → **Withdraw atomically** with session cancel |
| **HD-7.2-002D** | CancelExam → **skip** sessions already Cancelled |

### CancelExam (Phase 7.1 — authoritative, not redesigned)

```text
CancelExam remains authoritative.
When permitted: Exam → Cancelled; open sessions → Cancelled; active seats → Withdrawn.
No grade mutation.
```

### CancelExamEnrollment (DL-7.2-U08-001)

```text
Permission: exam.enrollment.cancel → grades_manager
Event: ExamEnrollmentCancelled
Cause (U08): exam_enrollment_cancel
Cause (U05 cascade seat events): exam_session_cancel
Do NOT merge causes. Do NOT create a new event class.
```

#### DR-002 (preserved)

```text
CURRENT grade for that seat (student_grades.is_current = true)
→ FAIL CLOSED
→ no withdrawal mutation
→ no cancellation event
→ no successful mutation/outbox outcome for the failed attempt

Historical (non-current) grades do NOT independently block U08.
U08 MUST NOT mutate / void / correct / finalize / rewrite grades.
Attendance MUST NOT be mutated.
```

#### HD-U08-001 — Already Withdrawn / repeat cancel

```text
If exam_enrollments.status = Withdrawn and CancelExamEnrollment is invoked again:
  IDEMPOTENT NO-OP
  — no second withdrawal mutation
  — no second ExamEnrollmentCancelled / duplicate outbox success event
  — business state remains Withdrawn
  — normal idempotency-key replay / conflict semantics preserved
  — already-Withdrawn is NOT a new cancellation

Rationale: terminal business state for this command; prevents duplicate events on
U05↔U08 race, retry, duplicate command, and administrative repeat; aligns with
CancelExamSession already-Cancelled (HD-7.2-002A) spirit.
```

#### HD-U08-002 — Absent → Withdrawn

```text
Absent → Withdrawn = FORBIDDEN / FAIL CLOSED

Rationale: Absent is outside the active-seat withdrawal set; preserves locked
transition boundary; U08 is not a general enrollment cleanup operation.
U05 still does not withdraw Absent — intentional.
```

#### HD-U08-003 — Cancelled session × U08

```text
If exam_session.status = Cancelled:

  U08 remains available ONLY for enrollment.status IN
    (Registered, Confirmed, Present)
  AND CURRENT grade does NOT exist (DR-002)
  AND all normal U08 authorization, school-isolation, state,
      concurrency, and idempotency checks pass.

  Cancelled + Registered → Withdrawn = ALLOWED
  Cancelled + Confirmed  → Withdrawn = ALLOWED
  Cancelled + Present    → Withdrawn = ALLOWED
  Cancelled + Absent     → FORBIDDEN   (HD-U08-002)
  Cancelled + Withdrawn  → IDEMPOTENT NO-OP (HD-U08-001)

Rationale: cancelled session does not make a still-active seat ineligible for
explicit administrative withdrawal; handles post-cancel cleanup and U05/U08 race
deterministically; preserves DR-002, school isolation, and distinct U08 cause;
does not reopen Absent → Withdrawn.
```

#### U05 vs U08 concurrency (behavioral lock — design only)

```text
U05 CancelExamSession may cancel session and withdraw active seats.
Concurrent U08 CancelExamEnrollment targeting the same seat:

  Final seat status MUST be deterministic: Withdrawn
  At most ONE actual withdrawal domain/outbox event represents the real transition
    (U05 cause=exam_session_cancel OR U08 cause=exam_enrollment_cancel)
  If U08 observes already Withdrawn → HD-U08-001 IDEMPOTENT NO-OP (no second event)

This Design Lock does not authorize concurrency implementation code.
```

#### Idempotency (Phase 7.2 writer pattern — preserved)

```text
Required idempotency key
Fingerprint (reuse enrollment-writer convention — HD-U08-004)
same-COMMIT mutation + outbox + idempotency
same key + same payload → REPLAY
same key + different payload → CONFLICT FAIL CLOSED
event identity ≠ idempotency identity (DR-006)
No event for Withdrawn → Withdrawn no-op (HD-U08-001)
```

### Forbidden on any cancel path

```text
grade deletion
grade voiding
grade conversion
automatic grade mutation
```

```text
Cancellation must not mutate grade facts.
```

---

## 9. CURRENT Grade Guard

### HD-7.2-003 — LOCKED

```text
If ANY CURRENT grade (student_grades.is_current = true)
exists for seats of the target session:
CancelExamSession → FAIL CLOSED
```

Aligned in spirit with CancelExam CURRENT-grade guard and DR-002; no grade side effects.

---

## 10. Present Semantics

### HD-7.2-005 — LOCKED

```text
Actor / Role:
grades_manager

Permission:
exam.enrollment.present
```

### Precondition

```text
Confirmed → Present
requires session = InProgress

Scheduled → Present = NOT ALLOWED
```

### Mandatory distinctions

```text
Present != student_grades.is_absent
Present does NOT mutate attendance.mark SSOT
Present does NOT automatically mutate grades
Present is an explicit exam-enrollment/session lifecycle fact
```

Present does **not** redefine Grade Entry timing (see §11).

---

## 11. Grade Entry Timing

### HD-7.2-009 — LOCKED (exact set)

```text
GRADE ENTRY ALLOWED:
InProgress
Completed

GRADE ENTRY DENIED:
Scheduled
Cancelled
```

```text
Do NOT broaden to “all non-cancelled sessions”.
Do NOT infer additional statuses.
Policy ≠ authorization to modify enter guards in this Design Lock task.
```

---

## 12. Grade Correction Policy

### HD-7.2-008 — LOCKED (design policy)

```text
CorrectStudentGrade
→ FAIL CLOSED if session is Cancelled
→ FAIL CLOSED if exam is Cancelled
```

```text
This does NOT authorize modifying StudentGradeWriteGuard.
Implementation of the guard change requires a future separate authorization.
No automatic grade mutation.
```

---

## 13. Multiple Sessions

### HD-7.2-006 — LOCKED

```text
Multiple exam_sessions for the same Exam + Subject = ALLOWED
```

```text
Do NOT introduce UNIQUE(exam_id, subject_id)
No uniqueness migration is authorized
```

---

## 14. Move/Reassign Policy

### HD-7.2-007 — LOCKED

```text
Move/Reassign ExamEnrollment = FORBIDDEN in Phase 7.2
```

```text
Update != Move
Do not reinterpret exam_session_id mutation as Move
No MoveExamEnrollment / ReassignExamEnrollment command authorized
```

Operational path (design only): CancelExamEnrollment (when permitted) + CreateExamEnrollment on target session.

---

## 15. Room Isolation

### HD-7.2-013 — LOCKED (application/domain design)

```text
If room_id is supplied:
  room.branch.school_id MUST equal current school_id

Failure = FAIL CLOSED
```

```text
Does NOT authorize:
  composite FK
  migration
  RLS change
  schema redesign
```

---

## 16. Room/Time Overlap

### HD-7.2-010 — LOCKED

```text
Hard room/time overlap enforcement = DEFERRED
```

```text
Not authorized:
  trigger
  exclusion constraint
  automatic conflict blocker
```

Teacher/invigilator conflict scheduling remains **OUT OF SCOPE** until an assignment model exists.

---

## 17. Seat Number

### HD-7.2-012 — LOCKED

```text
seat_number = optional free-text
```

```text
Not authorized:
  uniqueness constraint
  automatic generation
  new seat allocation subsystem
```

---

## 18. Time Semantics

### HD-7.2-014 — LOCKED

```text
session_date
start_time
end_time
=
school-local civil date/time
```

```text
Not introduced:
  UTC conversion
  timezone migration
  calendar redesign
```

---

## 19. Event Causation

### HD-7.2-011 / DD-017 — ARCHITECTURE RESOLUTION (preserved)

```text
Shared event classes where applicable
+ mandatory cause discriminator
+ outbox command_name
```

Conceptual causes:

```text
exam_cancel
exam_session_cancel
exam_enrollment_cancel
```

```text
Do NOT create new event classes merely because of conceptual causes.
event identity != idempotency identity (DR-006)
No event implementation changes authorized by this Design Lock
```

Cascade CancelExam child events already use `cause: exam_cancel` — preserve and distinguish direct command causes at implementation time (when separately authorized).

---

## 20. Idempotency / Outbox Boundary

### Frozen pattern (Phase 7.1 / DR-006 / P7-D5 — reuse)

```text
Every Phase 7.2 writer (when implemented under separate AuthZ):
  required idempotency key
  payload fingerprint
  same-COMMIT mutation + outbox + idempotency
  fail-closed payload conflict
  event identity != idempotency identity
```

```text
Do not redesign audit.outbox_messages / audit.idempotency_keys
Post-commit idempotency store = FORBIDDEN pattern
```

This Design Lock does **not** authorize implementing writers.

---

## 21. RLS Architecture Resolution

### DD-019 — ARCHITECTURE RESOLUTION (preserved)

```text
No RLS mutation authorized by this Design Lock
FORCE RLS / school isolation on sessions/enrollments remains as already proven
```

```text
Required future verification (before Phase 7.2 closure, after implementation AuthZ):
writer-path PostgreSQL tests
(same-school / cross-school / missing GUC / FORCE RLS)
```

```text
Not authorized now:
  enable/disable RLS
  change FORCE RLS
  change policies
  change WITH CHECK
```

---

## 22. Phase 7.1 Inherited Locks

| Authority | Frozen confirmation |
|-----------|---------------------|
| HD-001 | exam.create/update/cancel → grades_manager; does not auto-grant 7.2 perms |
| HD-005 | `exam.session.cancel` **FORBIDDEN**; CancelExamSession → `exam.session.update` |
| HD-006 | Present deferred to 7.2 — now resolved by HD-7.2-005 |
| DR-001 | CancelExam: no grade mutation; CURRENT-grade / Completed-session guards |
| DR-002 | CancelExamEnrollment: FAIL CLOSED on CURRENT grade; no grade mutation |
| DR-004 | Exam completion predicates; **no CompleteExam** command |
| DR-006 | Event identity ≠ idempotency identity |
| Phase 7.1 Final Closure | Create/Update/Cancel Exam **CLOSED**; HTTP blocked for 7.1 admin |
| Master Phase 7 Design Lock | Command vocabulary / lifecycle enums / academic-year contract |

### Explicit confirmations

```text
exam.session.cancel remains FORBIDDEN
Restore remains FORBIDDEN
CompleteExam remains OUT OF SCOPE
CancelExam remains authoritative
Cancellation does not mutate grades
Present remains distinct from is_absent
Event identity remains distinct from idempotency identity
```

---

## 23. Deferred / Out-of-Scope Scope

```text
HTTP exposure
Query AuthZ
Bulk assignment
Capacity enforcement
Scheduled → Completed skip
Teacher/invigilator conflict scheduling
CompleteExam
```

```text
Do not reopen these under Phase 7.2 Design Lock.
```

---

## 24. Forbidden Changes

Under this Design Lock (and until a separate Implementation Authorization Gate + any required DCRs):

```text
Register / mutate permissions or roles
Introduce exam.session.cancel
Implement or expose HTTP for Phase 7.2 writers
Modify CancelExam / CancelExam cascade
Modify StudentGradeWriteGuard / Grade handlers
Create Move/Reassign / Restore / CompleteExam
Add UNIQUE(exam_id, subject_id)
Add composite room FK / room uniqueness migrations
Add room overlap exclusion constraints / triggers
UTC / timezone / calendar redesign
RLS policy mutation
Hard-delete exam / session / enrollment / grade rows
Silent expansion of Grade Entry beyond InProgress|Completed
Collapse Present into exam.enrollment.update or attendance.mark
Auto-void / delete / convert grades on cancel
```

---

## 25. Implementation Boundary

```text
DESIGN LOCK = APPROVED / AMENDED / LOCKED (DL-7.2-U08-001)
IMPLEMENTATION = NOT AUTHORIZED
Batch 6 = NOT AUTHORIZED
U08 implementation = NOT AUTHORIZED
```

| Concern | State |
|---------|-------|
| Database | **NOT AUTHORIZED** / UNCHANGED by this artifact |
| Application | **NOT AUTHORIZED** |
| Permissions | **NOT AUTHORIZED** |
| RLS | **NOT AUTHORIZED** |
| HTTP | **NOT AUTHORIZED** |
| Migrations | **NOT AUTHORIZED** |
| Batch 6 / U08 unit AuthZ | **NOT AUTHORIZED** |
| Implementation Authorization Gate (unit) | Gate `7.2-U08` remains **NOT AUTHORIZED** |

```text
Design Lock amendment does not authorize implementation.
Next step = U08 READINESS RE-AUDIT, then separate human unit AuthZ if PASS.
```

---

## 26. Dependency Closure

| Dependency | Result |
|------------|--------|
| HD-7.2-001 → HD-7.2-004 | **PASS** |
| HD-7.2-001 → HD-7.2-005 | **PASS** (`grades_manager` + dedicated Present perm) |
| HD-7.2-002A/B/D → HD-7.2-003 | **PASS** |
| HD-7.2-003 → HD-7.2-008 | **PASS** |
| HD-7.2-005 → HD-7.2-009 | **PASS** (Present ≠ Grade Entry timing) |
| HD-7.2-006 → HD-7.2-007 | **PASS** (multiplicity ≠ Move) |
| HD-7.2-013 → room isolation | **PASS** (policy only; no FK) |
| Phase 7.1 → Phase 7.2 inheritance | **PASS** |
| RLS architecture resolution (DD-019) | **PASS** |
| Event causation architecture resolution (HD-7.2-011) | **PASS** |
| HD-U08-001/002/003 → CancelExamEnrollment matrix (DL-7.2-U08-001) | **PASS** |
| HD-U08-002 A ↔ HD-U08-003 B (no Absent reopen) | **PASS** |

```text
All dependency results = PASS
Design Lock may be declared APPROVED / AMENDED / LOCKED
```

---

## 27. Design Lock Verdict

```text
DESIGN LOCK:
APPROVED / AMENDED / LOCKED

Current revision:
DL-7.2-U08-001
```

```text
Meaning:
  The Phase 7.2 design is frozen, including U08 CancelExamEnrollment decisions
  HD-U08-001 = A, HD-U08-002 = A, HD-U08-003 = B.

Does NOT mean:
  Implementation authorized
  Batch 6 authorized
  U08 implementation authorized
```

### Final authorization state

```text
Implementation = NOT AUTHORIZED
Batch 6 = NOT AUTHORIZED
U08 implementation = NOT AUTHORIZED
U09–U15 = NOT AUTHORIZED
Database = NOT AUTHORIZED
Application = NOT AUTHORIZED
Permissions = NOT AUTHORIZED
RLS = NOT AUTHORIZED
HTTP = NOT AUTHORIZED
Migration = NOT AUTHORIZED
```

```text
STOP BEFORE IMPLEMENTATION = YES
NEXT STEP AFTER THIS AMENDMENT = U08 READINESS RE-AUDIT
(not automatic Batch 6 / U08 implementation authorization)
```

```text
Do not implement Phase 7.2 U08 in this amendment task.
Do not register permissions.
Do not modify RLS, migrations, Grade guards, or CancelExam.
Do not expose HTTP.
Do not change Gate 7.2-U08 AuthZ row to AUTHORIZED.
Do not create Batch 6 authorization.
```
