# MASTER PHASE 7 — PHASE 7.2 — HUMAN APPROVAL BALLOT

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Human Approval Ballot |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Document Type** | HUMAN DECISION COLLECTION ONLY |
| **Date** | 2026-09-11 |
| **Source** | `.cursor/database/phase-7.2/03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md` |
| **Purpose** | Present unresolved choices to human project / security / business owner |

```text
THIS DOCUMENT = ballot only
≠ Design Lock
≠ Implementation Authorization
≠ approval of any option
≠ permission / role / RLS / migration / application change
```

---

## 2. Authorization Boundary

```text
Implementation = NOT AUTHORIZED
Database changes = NOT AUTHORIZED
Application changes = NOT AUTHORIZED
Permission changes = NOT AUTHORIZED
RLS changes = NOT AUTHORIZED
HTTP = NOT AUTHORIZED
Design Lock = NOT AUTHORIZED
```

**Absolute rule:** Do not treat any `RECOMMENDATION — NOT APPROVAL` as approved. Human must mark each ballot section.

---

## 3. How to Complete This Ballot

For each HD below:

1. Read the question, evidence, locked constraints, and options.
2. Mark **exactly one** human selection (or OTHER with text).
3. Provide rationale and sign/date.
4. Leave Decision Status as `PENDING HUMAN RESPONSE` until the owner completes that section.

```text
Human approvals recorded by this task = 0
```

---

## 4. Explicitly Deferred / Confirmed (NOT on this ballot)

Do **not** reopen unless an authoritative artifact requires it:

| Topic | Status |
|-------|--------|
| HTTP exposure | **DEFERRED** |
| Query AuthZ | **DEFERRED** |
| Bulk assignment | **DEFERRED** |
| Capacity enforcement | **DEFERRED** |
| Scheduled → Completed skip | **DEFERRED** |
| Teacher/invigilator conflict scheduling | **OUT OF SCOPE** until assignment model exists |
| HD-7.2-011 Event causation | **ARCHITECTURE RESOLUTION** — not a human ballot item |
| HD-005 `exam.session.cancel` | **LOCKED FORBIDDEN** — do not reopen |
| Phase 7.1 CancelExam cascade | **CLOSED** — do not redesign |
| Restore enrollment | **FORBIDDEN** (Master Lock) |
| CompleteExam | **OUT OF SCOPE** |

---

## 5. Conflict Check

Reconciled against artifacts 01 / 02 / 03, Phase 7.1 closure, HD-001 amendment, 10A, and Master Lock.

```text
Ballot conflicts requiring STOP = NONE
```

All 13 items below remain **UNRESOLVED** in artifact 03. No automatic resolution applied.

---

## 6. CRITICAL BALLOTS

---

## BALLOT — HD-7.2-001 — Permission Ownership

### 1. Decision ID

`HD-7.2-001` (DD-004)

### 2. Question

Which role(s) own Phase 7.2 permissions:

```text
exam.session.create
exam.session.update
exam.session.open
exam.session.close
exam.enrollment.create
exam.enrollment.update
exam.enrollment.cancel
```

The human must explicitly decide whether:

* all seven permissions belong to `grades_manager`;
* session administration is separated from participation marking;
* a dedicated role is required;
* another organization model applies;
* or the decision remains unresolved.

### 3. Authoritative Evidence

- Live `config/security.php`: `grades_manager` owns `exam.create|update|cancel` **only**; no `exam.session.*` / `exam.enrollment.*`
- Phase 7.1 HD-001: approved **only** those three exam admin permissions → `grades_manager`
- 10A: no candidate role approved for session/enrollment permissions
- Roles present include `grades_manager`, `grades_teacher`, `grades_viewer`, `enrollment_*`, `attendance_*` — none catalogue Phase 7.2 session/enrollment perms

### 4. Existing Locked Constraints

```text
HD-001 DOES NOT automatically authorize Phase 7.2 permissions.
exam.session.cancel MUST NOT be introduced (HD-005).
Do not invent Examiner / temporary roles as a workaround.
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | All seven Phase 7.2 permissions → `grades_manager` |
| **B** | Session administration → `grades_manager`; Present / participation marking assigned separately (ties to HD-7.2-005) |
| **C** | Dedicated exam session / enrollment role(s) |
| **D** | Another explicitly documented organization model (must be written by owner) |
| **E** | Remain unresolved until business/security owner supplies organization policy |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A as default candidate for discussion symmetry with HD-001;
Option B if Present is invigilator-owned (HD-7.2-005).
```

**Do not silently select Option A.**

### 7. Decision Owner

**SECURITY** / **HUMAN PROJECT OWNER**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] APPROVE OPTION D   (attach written model: ______________________________)
[ ] APPROVE OPTION E
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## BALLOT — HD-7.2-002 — CancelExamSession / CancelExamEnrollment Cancellation Semantics

### 1. Decision ID

`HD-7.2-002` (DD-009)

### 2. Question

How do dedicated `CancelExamSession` / `CancelExamEnrollment` coexist with Phase 7.1 `CancelExam` cascade for already-cancelled sessions, active seats, CancelExam after partial cancels, event causation, and grade non-mutation?

**CURRENT-grade behavior is voted separately in HD-7.2-003 (sub-decision C).**

Keep **A / B / D** separately visible — do not collapse into one automatic approval.

### 3. Authoritative Evidence

- CancelExam (CLOSED): fail-closed on CURRENT grade / Completed session / Completed|Cancelled exam; cascades open sessions → Cancelled; active seats → Withdrawn; no grade mutation
- Cascade events: `cause: exam_cancel`
- Dedicated CancelExamSession / CancelExamEnrollment writers: **ABSENT**
- DR-002: CancelExamEnrollment fail-closed if CURRENT grade
- Master Lock CancelExamSession: Scheduled|InProgress → Cancelled; silent on CURRENT-grade guard and seat cascade

### 4. Existing Locked Constraints

```text
CancelExam cascade = authoritative — do not redesign
Never auto-void / delete / mutate grades as cancellation side effect
DR-001 / DR-002 grade non-mutation
HD-005: CancelExamSession uses exam.session.update
```

### 5. Available Options

#### A — Already Cancelled session

| Option | Description |
|--------|-------------|
| **A1** | Idempotent success / no-op (replay-safe) |
| **A2** | Fail closed “already cancelled” |

#### B — Active seats on CancelExamSession

| Option | Description |
|--------|-------------|
| **B1** | Cascade active seats → Withdrawn (atomic with session cancel) |
| **B2** | Fail closed if any active seat remains |
| **B3** | Leave seats active |

#### C — CURRENT grades

Vote on **HD-7.2-003** only.

#### D — CancelExam after individual session cancels

| Option | Description |
|--------|-------------|
| **D1** | Cancel remaining open sessions only; skip already Cancelled |
| **D2** | Fail CancelExam if any session already Cancelled |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
A1 + B1 + D1
(with C via HD-7.2-003 recommendation FAIL CLOSED — not approved here)
Never mutate grades.
```

### 7. Decision Owner

**BUSINESS** / **ARCHITECTURE** / **HUMAN PROJECT OWNER**

### 8. Human Selection

**Sub-ballot A**

```text
[ ] APPROVE OPTION A1
[ ] APPROVE OPTION A2
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

**Sub-ballot B**

```text
[ ] APPROVE OPTION B1
[ ] APPROVE OPTION B2
[ ] APPROVE OPTION B3
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

**Sub-ballot D**

```text
[ ] APPROVE OPTION D1
[ ] APPROVE OPTION D2
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
A: _______________________________________________________________________
B: _______________________________________________________________________
D: _______________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## BALLOT — HD-7.2-003 — CancelExamSession CURRENT-Grade Guard

### 1. Decision ID

`HD-7.2-003` (DD-027)

**Relation:** Critical **sub-decision C** of HD-7.2-002 / DD-009. Present independently; do not duplicate a conflicting final answer.

### 2. Question

Is `CancelExamSession` allowed when **ANY** CURRENT grade (`student_grades.is_current = true`) exists for seats of that session?

### 3. Authoritative Evidence

- CancelExam: **blocks** on CURRENT grade for the exam
- CancelExamEnrollment (DR-002): **blocks** on CURRENT grade for that seat
- CancelExamSession (Master Lock): **no** explicit CURRENT-grade rule
- Enter guard rejects Cancelled session; Correct guard does **not** re-check Cancelled (see HD-7.2-008)

### 4. Existing Locked Constraints

```text
Never auto-void grades
Never delete grades
Never mutate grades as cancellation side effect
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | **FAIL CLOSED** if any CURRENT grade exists on the session |
| **B** | Allow CancelExamSession despite CURRENT grades; rely on enter-block only |
| **C** | Allow cancel and require separate grade CQRS first — operational policy only (still no auto-void) |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A — FAIL CLOSED
```

### 7. Decision Owner

**BUSINESS** / **SECURITY** / **ARCHITECTURE**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## 7. HIGH BALLOTS

---

## BALLOT — HD-7.2-004 — Permission Catalog Registration

### 1. Decision ID

`HD-7.2-004` (DD-003)

### 2. Question

May the seven Master Lock / HD-005 permission names be registered into the live catalog, and when?

### 3. Authoritative Evidence

- Vocabulary DEFINED in Master Lock / 10A
- HD-005 **APPROVED**: CancelExamSession → `exam.session.update`; **forbid** `exam.session.cancel`
- Live catalog: seven names **ABSENT**

### 4. Existing Locked Constraints

```text
exam.session.cancel MUST NOT be introduced (HD-005) — DO NOT REOPEN
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | Register exactly the seven names after ownership (HD-7.2-001) is approved |
| **B** | Collapse open/close into `exam.session.update` (narrower catalog) |
| **C** | Defer all catalog registration until a later security gate |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A (sequenced after HD-7.2-001)
```

### 7. Decision Owner

**SECURITY** / **HUMAN PROJECT OWNER**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## BALLOT — HD-7.2-005 — Confirmed → Present

### 1. Decision ID

`HD-7.2-005` (DD-005 / prior HD-006 deferred)

### 2. Question

Independently resolve:

1. Who performs `Confirmed → Present`?
2. What permission is required?
3. Must session be `InProgress`?
4. Is `Scheduled → Present` allowed?
5. Is `Present → Absent` allowed?
6. Is Present an exam participation fact only?
7. Must Present affect grade eligibility?
8. Must Present interact with attendance?

**Do not infer** whether Present belongs to `grades_manager`, teacher, invigilator, or another role.

### 3. Authoritative Evidence

- Prior HD-006: **APPROVED AS DEFERRED** to Phase 7.2 Design Lock — actor/permission/session rules not decided
- Master Lock §10.3: Confirmed→Present actor/session-open **TBD**
- `isActiveSeat` includes Present; Enter does **not** require Present
- Seat Present ≠ `student_grades.is_absent`
- Registered|Confirmed|Present → Absent allowed in Master Lock design matrix

### 4. Existing Locked Constraints

```text
Present ≠ student_grades.is_absent
Do not merge exam seat participation with attendance.mark SSOT
Do not invent Present semantics in Phase 7.1 (closed)
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | Exam admin only via `exam.enrollment.update`; require session `InProgress`; forbid Present while Scheduled; Present→Absent allowed; Present does not gate grade eligibility; no attendance write |
| **B** | Separate invigilator/teacher permission for Present; require InProgress; otherwise like A |
| **C** | Allow Present while Scheduled |
| **D** | Auto-Present on grade enter — conflates domains |
| **E** | Defer Present transition out of Phase 7.2 Update matrix (enum unused until later) |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A or B with InProgress required; not D;
keep Present ≠ is_absent; no attendance mutation.
```

### 7. Decision Owner

**BUSINESS** / **SECURITY** / **HUMAN PROJECT OWNER**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] APPROVE OPTION D
[ ] APPROVE OPTION E
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

If Option B: name actor/permission explicitly:

```text
Actor / permission: _______________________________________________________
```

### 9. Human Rationale

```text
___________________________________________________________________________
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## BALLOT — HD-7.2-006 — Multiple Sessions Per Subject

### 1. Decision ID

`HD-7.2-006` (DD-006)

### 2. Question

May one Exam have multiple `exam_sessions` rows with the same `subject_id` (make-ups, parallel rooms, cohorts, resits, accommodations)?

**Do not create a uniqueness migration in response to this ballot.**

### 3. Authoritative Evidence

- DB: no UNIQUE `(exam_id, subject_id)`
- Index `(subject_id, session_date)` only
- Seat uniqueness: `(exam_session_id, enrollment_id)`
- Product rule: **UNKNOWN**

### 4. Existing Locked Constraints

```text
Do not add unique constraint in this ballot task
Do not implement schema change here
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | **ALLOW MULTIPLE** — status quo schema; no unique |
| **B** | **ONE SESSION ONLY** per exam+subject — future unique is a separate schema decision; do not implement now |
| **C** | Allow multiple only when typed (make-up/resit) — needs future schema |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A — ALLOW MULTIPLE
```

### 7. Decision Owner

**BUSINESS** / **HUMAN PROJECT OWNER**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## BALLOT — HD-7.2-007 — Move / Reassign Enrollment

### 1. Decision ID

`HD-7.2-007` (DD-010)

### 2. Question

May an existing ExamEnrollment move Session A → Session B?

**Do not implement Move/Reassign in response to this ballot.**  
**Do not reinterpret UpdateExamEnrollment as permission to change `exam_session_id`.**

### 3. Authoritative Evidence

- HD-004 command set: no Move command
- UpdateExamEnrollment: identity columns immutable in Master Lock
- Grade composites bind to enrollment seat + session
- Unique `(exam_session_id, enrollment_id)`

### 4. Existing Locked Constraints

```text
Do not reinterpret UpdateExamEnrollment as permission to change exam_session_id
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | **FORBIDDEN** in Phase 7.2 — CancelExamEnrollment (if permitted) + CreateExamEnrollment on target |
| **B** | Dedicated Move/Reassign command with grade/session guards |
| **C** | Allow Update to change `exam_session_id` (rejected by immutability design) |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A — FORBIDDEN
```

### 7. Decision Owner

**BUSINESS** / **ARCHITECTURE**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## BALLOT — HD-7.2-008 — Grade Correction After Session/Exam Cancellation

### 1. Decision ID

`HD-7.2-008` (DD-012)

### 2. Question

Must `CorrectStudentGrade` fail closed when session **or** exam is Cancelled?

**Do not modify `StudentGradeWriteGuard` in response to this ballot.**  
Record **policy only**.

### 3. Authoritative Evidence

- `assertCanEnter` rejects Cancelled session/exam
- `assertCanCorrect` does **not** re-check session/exam cancellation
- CancelExam blocked when CURRENT grade exists — reduces but does not eliminate paths if HD-7.2-003 Option B were chosen

### 4. Existing Locked Constraints

```text
Do not modify Grade handlers / StudentGradeWriteGuard in this ballot task
Do not silently change live Grade behavior here
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | Correct **FAIL CLOSED** if session or exam Cancelled |
| **B** | Allow Correct on Cancelled parents (history repair) |
| **C** | Allow only under elevated security role |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A — FAIL CLOSED
```

### 7. Decision Owner

**BUSINESS** / **SECURITY** / **ARCHITECTURE**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## BALLOT — HD-7.2-009 — Grade Entry Session Timing

### 1. Decision ID

`HD-7.2-009` (DD-013)

### 2. Question

Which session statuses allow Grade Enter?

```text
Scheduled / InProgress / Completed / Cancelled
```

**Do not change Grade Entry behavior in response to this ballot.**  
Record **policy only**. Current known behavior: Cancelled = denied.

### 3. Authoritative Evidence

- Current: Cancelled = **denied**; InProgress **not** required; Completed/Scheduled not blocked by enter guard today (DEFINED by omission)

### 4. Existing Locked Constraints

```text
Cancelled remain denied unless future DCR
Do not implement guard changes here
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | Enter only when `InProgress` |
| **B** | Enter when `Scheduled` or `InProgress` |
| **C** | Enter when `InProgress` or `Completed` (post-close scoring window) |
| **D** | Keep current: anything except Cancelled (+ active seat + other enter rules) |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option C or A as candidates — human must pick the allowed set.
```

### 7. Decision Owner

**BUSINESS** / **HUMAN PROJECT OWNER**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] APPROVE OPTION D
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

Final allowed set (if OTHER or clarification):

```text
Scheduled:   ALLOW / DENY
InProgress:  ALLOW / DENY
Completed:   ALLOW / DENY
Cancelled:   DENY (locked unless DCR)
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER)

---

## 8. MEDIUM BALLOTS

---

## BALLOT — HD-7.2-010 — Room / Time Overlap

### 1. Decision ID

`HD-7.2-010` (DD-007)

### 2. Question

Must the system enforce same-room overlapping `session_date` + time ranges?

Teacher/invigilator conflicts remain **OUT OF SCOPE** until an assignment model exists (not reopened here).

### 3. Authoritative Evidence

- CHECK: `end_time > start_time` only
- No exclusion constraint on room/time
- No invigilator assignment table on exam_sessions

### 4. Existing Locked Constraints

```text
Teacher/invigilator conflict scheduling = OUT OF SCOPE until assignment model exists
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | No overlap enforcement in Phase 7.2 |
| **B** | Fail-closed same room + overlapping times |
| **C** | Soft warning only (UI — HTTP out of scope) |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A (defer hard scheduling)
```

### 7. Decision Owner

**BUSINESS** / **DATABASE**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED)

---

## BALLOT — HD-7.2-012 — Seat Number Uniqueness

### 1. Decision ID

`HD-7.2-012` (DD-022)

### 2. Question

Is `seat_number` unique per session? Auto-generated?

### 3. Authoritative Evidence

- Nullable `string(10)`; no UNIQUE
- Immutable when Present or CURRENT grade (Master Lock)

### 4. Existing Locked Constraints

```text
Do not add uniqueness migration in this ballot task
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | Optional free-text; no uniqueness |
| **B** | Unique per session when not null |
| **C** | System-generated sequential seats |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A
```

### 7. Decision Owner

**BUSINESS**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED)

---

## BALLOT — HD-7.2-013 — Room School Isolation

### 1. Decision ID

`HD-7.2-013` (DD-025)

### 2. Question

If `room_id != null`, must the room belong to the same school as the exam session?

**Do not create a composite FK in response to this ballot.**  
Record only whether same-school validation is the approved **policy**.

### 3. Authoritative Evidence

| Fact | Evidence |
|------|----------|
| `organization.rooms` | Has `branch_id`; **no** `school_id` column |
| School path | `rooms.branch_id` → `branches.school_id` |
| `exam_sessions.room_id` | Nullable FK → `rooms(id)` **only** |
| Composite `(room_id, school_id)` FK | **ABSENT** |
| Cross-school room id at DB | **Possible** unless application validates |

### 4. Existing Locked Constraints

```text
Session school_id composite FK to parent exam remains LOCKED
Do not add composite room FK in this ballot task
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | Fail-closed same-school validation in handler when `room_id` set (via room→branch→school) |
| **B** | Rely solely on rooms/branches RLS + id FK |
| **C** | Disallow `room_id` until composite school FK exists |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A
```

### 7. Decision Owner

**SECURITY** / **ARCHITECTURE** / **DATABASE**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED — BLOCKER for room_id path)

---

## BALLOT — HD-7.2-014 — Timezone / Session Date Semantics

### 1. Decision ID

`HD-7.2-014` (DD-026)

### 2. Question

What timezone semantics apply to `session_date` / `start_time` / `end_time`?

**Do not modify timezone/schema behavior in response to this ballot.**

### 3. Authoritative Evidence

- Stored as date/time without session timestamptz
- School TZ policy for exams **UNKNOWN**

### 4. Existing Locked Constraints

```text
Do not change schema/timezone storage in this ballot task
```

### 5. Available Options

| Option | Description |
|--------|-------------|
| **A** | School-local civil date/time; no conversion in Phase 7.2; no academic-calendar gate |
| **B** | Require academic-calendar validation |
| **C** | Store/interpret as UTC (would need schema/product change) |

### 6. Recommended Option

```text
RECOMMENDATION — NOT APPROVAL:
Option A
```

### 7. Decision Owner

**BUSINESS** / **ARCHITECTURE**

### 8. Human Selection

```text
[ ] APPROVE OPTION A
[ ] APPROVE OPTION B
[ ] APPROVE OPTION C
[ ] REJECT
[ ] DEFER
[ ] REQUEST DESIGN CHANGE
[ ] OTHER: __________
```

### 9. Human Rationale

```text
___________________________________________________________________________
```

### 10. Approval Evidence / Reference

```text
___________________________________________________________________________
```

### 11. Date

```text
____ / ____ / ________
```

### 12. Decision Status

**PENDING HUMAN RESPONSE** (currently UNRESOLVED)

---

## 9. Not on Ballot — Architecture Resolution Reminder

| ID | Topic | Status |
|----|-------|--------|
| HD-7.2-011 | Event causation (DD-017) | **ARCHITECTURE RESOLUTION** — shared event classes + mandatory `cause` + outbox `command_name`. **Not** presented for human option selection. |

---

## 10. Ballot Summary Checklist (for human owner)

| HD | Priority | Blocks Design Lock | Selection complete? |
|----|----------|--------------------|---------------------|
| HD-7.2-001 | Critical | YES | [ ] |
| HD-7.2-002 A/B/D | Critical | YES | [ ] / [ ] / [ ] |
| HD-7.2-003 | Critical | YES | [ ] |
| HD-7.2-004 | High | YES | [ ] |
| HD-7.2-005 | High | YES | [ ] |
| HD-7.2-006 | High | YES | [ ] |
| HD-7.2-007 | High | YES | [ ] |
| HD-7.2-008 | High | YES | [ ] |
| HD-7.2-009 | High | YES | [ ] |
| HD-7.2-010 | Medium | NO* | [ ] |
| HD-7.2-012 | Medium | NO* | [ ] |
| HD-7.2-013 | Medium | YES | [ ] |
| HD-7.2-014 | Medium | NO* | [ ] |

\*Blocks Design Lock only if product demands a hard rule without affirming deferral/default (per artifact 03).

---

## 11. After Ballot Completion (future — not this task)

```text
When human selections are recorded with evidence:
  → produce human decision approval record (separate artifact)
  → only then Phase 7.2 Design Lock may be considered
  → Implementation Authorization remains a separate later gate

This ballot does NOT authorize Design Lock or implementation.
```

---

## 12. STOP

```text
Mode = HUMAN DECISION COLLECTION ONLY
Human approvals recorded by this task = 0
Implementation = NOT AUTHORIZED
Database = UNCHANGED
Application = UNCHANGED
Permissions = UNCHANGED
RLS = UNCHANGED
HTTP = NOT AUTHORIZED
Design Lock = NOT AUTHORIZED
STOP = YES
```
