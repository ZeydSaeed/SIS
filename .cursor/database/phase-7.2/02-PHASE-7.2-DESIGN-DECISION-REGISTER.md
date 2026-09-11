# MASTER PHASE 7 — PHASE 7.2 — DESIGN DECISION REGISTER

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle — Design Decision Register |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Document Type** | DESIGN DECISION REGISTER |
| **Date** | 2026-09-11 |
| **Mode** | DESIGN DECISION ANALYSIS ONLY |
| **Predecessor** | `.cursor/database/phase-7.2/01-PHASE-7.2-READINESS-DISCOVERY-DESIGN-AUDIT.md` |
| **Predecessor Verdict** | READY WITH CONDITIONS |
| **Implementation** | **NONE** |
| **Authorization** | **NO IMPLEMENTATION AUTHORIZATION** |

```text
THIS DOCUMENT = design decision formalization only
≠ DESIGN LOCK
≠ HUMAN IMPLEMENTATION AUTHORIZATION
≠ permission catalog change
≠ migration / RLS / HTTP / application change
```

### Authoritative inputs

| Artifact | Role |
|----------|------|
| `01-PHASE-7.2-READINESS-DISCOVERY-DESIGN-AUDIT.md` | Discovery baseline / DD-001…020 seed |
| `02-MASTER-PHASE-7-DESIGN-LOCK.md` | Master command / lifecycle / DR vocabulary |
| `10A-…HUMAN-DECISION-RESOLUTION.md` | HD-004 / HD-005 / HD-006 |
| `14-…HD-001-SECURITY-DECISION-AMENDMENT.md` | Phase 7.1 exam.* → grades_manager |
| `18-…FINAL-CLOSURE-GATE.md` | Phase 7.1 PASS — CLOSED |
| Live `config/security.php` | Current permission/role catalog |
| Live schema + `StudentGradeWriteGuard` / CancelExam cascade | Implementation evidence |

---

## 2. Authorization State

```text
Phase 7.1 = PASS — CLOSED
Phase 7.2 Design Lock = NOT YET AUTHORIZED
Phase 7.2 implementation = NOT AUTHORIZED
HTTP exposure = NOT AUTHORIZED
Query AuthZ = NOT AUTHORIZED
CompleteExam = NOT AUTHORIZED
New permissions = NOT AUTHORIZED
RLS changes = NOT AUTHORIZED
Migrations = NOT AUTHORIZED
Database schema changes = NOT AUTHORIZED
Application code changes = NOT AUTHORIZED
```

```text
STATUS AFTER THIS DOCUMENT:
DESIGN DECISIONS DOCUMENTED
HUMAN DECISIONS PENDING
IMPLEMENTATION NOT AUTHORIZED
```

---

## 3. Phase 7.1 Baseline

| Item | Status |
|------|--------|
| CreateExam / UpdateExam / CancelExam | **CLOSED** |
| HD-001 | **APPROVED** — `exam.create\|update\|cancel` → `grades_manager` |
| HD-004 | **APPROVED** — Phase 7.2 owns session/enrollment writers |
| HD-005 | **APPROVED** — CancelExamSession uses `exam.session.update` (no `exam.session.cancel`) |
| HD-006 | **APPROVED AS DEFERRED** — Confirmed→Present to Phase 7.2 Design Lock |
| DR-001 / DR-002 / DR-003 / DR-004 / DR-006 | **LOCKED** |
| CancelExam cascade | **Authoritative** — open sessions→Cancelled; active seats→Withdrawn; **no grade mutation** |
| Cascade events | `ExamSessionCancelled` / `ExamEnrollmentCancelled` with `cause: exam_cancel` |

**Rule:** Phase 7.2 MUST NOT redesign CancelExam. Phase 7.2 may only define how dedicated session/enrollment commands coexist with the cascade.

Do not reopen Phase 7.1 unless a genuine contradiction is proven. None found that reopens 7.1.

---

## 4. Phase 7.2 Scope

### In scope (design decisions only)

```text
CreateExamSession
UpdateExamSession
OpenExamSession
CloseExamSession
CancelExamSession

CreateExamEnrollment
UpdateExamEnrollment
CancelExamEnrollment
```

Plus: permission vocabulary/ownership, lifecycle transitions, grade integrity interactions, RLS verification requirements, same-COMMIT outbox/idempotency, event causation, HTTP/query deferral.

### Explicitly out of scope

```text
CompleteExam
Phase 7.1 exam admin redesign
Grade Enter/Correct/Void/Finalize redesign (except documenting integrity decisions)
HTTP exposure
Query AuthZ
Bulk import / publish queues
Schema/RLS/permission catalog mutation
```

---

## 5. Decision Classification Rules

| Status | Meaning |
|--------|---------|
| **LOCKED** | Fixed by authoritative prior decision; must not be changed by this register |
| **APPROVED** | Explicit human approval already recorded |
| **DEFINED** | Established by current schema/implementation; no new business choice required to understand it |
| **HUMAN DECISION REQUIRED** | Needs explicit project-owner / security / business authorization |
| **PROPOSAL** | Architectural recommendation only — **NOT approved** |
| **DEFERRED** | Intentionally postponed |
| **OUT OF SCOPE** | Excluded from Phase 7.2 |
| **CONFLICT** | Contradictory evidence; must resolve before proceeding |

```text
Never silently convert PROPOSAL → LOCKED.
Final Decision remains UNRESOLVED unless an authoritative human decision already exists.
```

---

## 6. Decision Register DD-001…DD-020

---

## 7.2-DD-001 — Session Command Set

### Question

Is the Phase 7.2 writer boundary for sessions exactly:

```text
CreateExamSession
UpdateExamSession
OpenExamSession
CloseExamSession
CancelExamSession
```

### Current Evidence

- Master Lock §9.2: all five **INCLUDE**
- HD-004 **APPROVED**: Phase 7.2 owns these commands
- Application writers: **ABSENT** (only CancelExam cascade mutates sessions)

### Existing Authority

HD-004; Master Lock §9.2 / §9.3; Phase 7.2 Readiness Audit §9 / §22

### Current Status

**LOCKED** (command set ownership and INCLUDE list)

### Options

A. Keep exactly the five HD-004 commands (no Start/Complete synonyms)  
B. Add synonyms (e.g. StartExamSession) — rejected by naming discipline  
C. Split Cancel into dedicated permission/command vocabulary beyond HD-005 — **FORBIDDEN** by HD-005 for permission name

### Architectural / Security / Data Integrity Impact

Defines CQRS write surface; prevents scope creep into CompleteExam or HTTP.

### Recommendation

Adopt Option A as the exclusive Phase 7.2 session writer set.

### Recommendation Status

**PROPOSAL ONLY** (command set itself is already LOCKED by HD-004; recommendation affirms no additions)

### Human Decision Required

**NO** (for the five-command set)

### Blocks Implementation?

**NO** (set is known); blocked by other DDs (security ownership, etc.)

### Dependencies

HD-004; feeds DD-003 / DD-009 / DD-014 / DD-016 / DD-017

### Reversibility

**LOW** (changing command surface after Design Lock is a Design Change Request)

### Decision Owner

**ARCHITECTURE** (already human-approved via HD-004)

### Final Decision

**LOCKED — Phase 7.2 session writers = Create / Update / Open / Close / CancelExamSession (HD-004)**

---

## 7.2-DD-002 — Enrollment Command Set

### Question

Is the Phase 7.2 enrollment writer boundary exactly:

```text
CreateExamEnrollment
UpdateExamEnrollment
CancelExamEnrollment
```

and should Move/Reassign, Restore, and Bulk assignment be commands or excluded?

### Current Evidence

- HD-004 **APPROVED** lists the three commands
- Master Lock: Update = **narrow** status/allowlist; Bulk = **DEFER** until product proven
- Move/Reassign: **UNKNOWN** in schema (identity = session+enrollment unique)
- Restore Withdrawn/Absent → active: Master Lock **FORBIDDEN**

### Existing Authority

HD-004; Master Lock §9.2 / §9.3 / §10.3; Readiness Audit DD-002 / DD-010 / DD-011

### Current Status

**LOCKED** for the three-command set; **HUMAN DECISION REQUIRED** for Move; **LOCKED FORBIDDEN** for Restore; **DEFERRED** for Bulk

### Options

A. Three commands only; Move/Restore/Bulk out of Phase 7.2  
B. Add `MoveExamEnrollment` / `ReassignExamEnrollment`  
C. Add `RestoreExamEnrollment`  
D. Add bulk assign command in 7.2

### Architectural / Security / Data Integrity Impact

Move changes identity uniqueness and audit semantics; Restore revives seats that grade enter treats as inactive; Bulk amplifies AuthZ/idempotency blast radius.

### Recommendation

Option A for Phase 7.2 Design Lock: three commands only. Treat Move as **FORBIDDEN** unless explicit Design Change (see DD-010). Keep Restore **FORBIDDEN** (DD-011). Keep Bulk **DEFERRED** (DD-021).

### Recommendation Status

**PROPOSAL ONLY** (for Move/Bulk); Restore already Master-Lock forbidden

### Human Decision Required

**YES** — only if product wants Move or Bulk in 7.2; otherwise affirm exclusion

### Blocks Implementation?

**YES** if Move is left ambiguous; **NO** if Design Lock explicitly excludes Move/Restore/Bulk

### Dependencies

DD-001; DD-010; DD-011; DD-021

### Reversibility

**LOW** for adding Move later without migration/audit story

### Decision Owner

**BUSINESS** / **ARCHITECTURE**

### Final Decision

**PARTIAL — Three enrollment commands LOCKED (HD-004). Move/Bulk = UNRESOLVED (default exclude). Restore = LOCKED FORBIDDEN (Master Lock).**

---

## 7.2-DD-003 — Permission Vocabulary

### Question

Which permission names authorize Phase 7.2 session/enrollment writers, given HD-005 forbids `exam.session.cancel`?

### Current Evidence

Master Lock / HD-005 design vocabulary:

```text
exam.session.create
exam.session.update   ← also CancelExamSession (HD-005)
exam.session.open
exam.session.close
exam.enrollment.create
exam.enrollment.update
exam.enrollment.cancel
```

Live catalog (`config/security.php`): **ABSENT** — only `exam.create|update|cancel` exist.

### Existing Authority

HD-005 **APPROVED**; Master Lock §10.2 / §23; Readiness Audit §11

### Current Status

**LOCKED** — cancel session permission name = `exam.session.update` (HD-005)  
**DEFINED** — candidate vocabulary in Master Lock  
**HUMAN DECISION REQUIRED** — whether to register exactly these names into the live catalog (no silent auto-approve of names as “live permissions”)

### Options

A. Register exactly the seven names above (no `exam.session.cancel`)  
B. Collapse open/close into `exam.session.update`  
C. Introduce `exam.session.cancel` — **FORBIDDEN** by HD-005  
D. Defer catalog registration until security ownership (DD-004) is decided

### Architectural / Security / Data Integrity Impact

Wrong vocabulary creates AuthZ drift vs Attendance (`attendance.session.cancel` exists — exams must **not** copy that pattern for cancel session).

### Recommendation

Option A + D sequencing: lock vocabulary as Master Lock/HD-005; register into catalog only after DD-004 role ownership is approved. Do not invent `exam.session.cancel`.

### Recommendation Status

**PROPOSAL ONLY** (catalog registration timing); HD-005 cancel naming remains LOCKED

### Human Decision Required

**YES** — catalog adoption of the seven names (vocabulary content partially locked)

### Blocks Implementation?

**YES** — handlers cannot ship without catalogued permissions + ownership

### Dependencies

DD-004 (ownership); HD-005; DD-001 / DD-002

### Reversibility

**MEDIUM**

### Decision Owner

**SECURITY** / **HUMAN PROJECT OWNER**

### Final Decision

**PARTIAL — HD-005 LOCKED (`exam.session.update` for CancelExamSession; no `exam.session.cancel`). Live catalog registration = UNRESOLVED.**

---

## 7.2-DD-004 — Permission Ownership

### Question

Which role(s) own `exam.session.*` and `exam.enrollment.*`?

### Current Evidence

Live roles in `config/security.php`:

| Role | Relevant perms today |
|------|----------------------|
| `grades_manager` | grades.* + `exam.create|update|cancel` only |
| `grades_teacher` | grades.view/create — **no** exam.* |
| `grades_viewer` | grades.view |
| `attendance_*` | attendance.* only |
| `enrollment_manager` | enrollment.* — **no** exam.* |

HD-001 approved **only** exam admin three permissions → `grades_manager`.  
10A explicitly: no candidate role approved for session/enrollment permissions.

### Existing Authority

HD-001 amendment; 10A HD-001 section; Readiness Audit §11 / DD-004

### Current Status

**HUMAN DECISION REQUIRED**

### Options

A. Map all seven session/enrollment perms → `grades_manager` (symmetric with HD-001)  
B. Split: create/update/open/close/cancel-session → `grades_manager`; Present marking (DD-005) → `grades_teacher` or new invigilator role  
C. Create dedicated `exam_session_manager` / `exam_enrollment_manager` roles  
D. Map session ops to attendance roles — **NOT recommended** (different domain SSOT)  
E. Leave unmapped until product org chart exists

### Architectural / Security / Data Integrity Impact

**CRITICAL.** Incorrect ownership creates over-privilege or unusable writers. Must **not** assume HD-001 inheritance.

### Recommendation

Option A as **default candidate** for Design Lock discussion only — still requires explicit human security approval. Option B if Present is invigilator-owned (pairs with DD-005).

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES**

### Blocks Implementation?

**YES** — **CRITICAL BLOCKER**

### Dependencies

DD-003; DD-005; Security Decision Table (§10)

### Reversibility

**MEDIUM** (role remapping possible; production audit trail impact)

### Decision Owner

**SECURITY** / **HUMAN PROJECT OWNER**

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-005 — Confirmed → Present

### Question

Who performs `Confirmed → Present`, under what permission and session preconditions, and what are grade implications?

Sub-questions (HD-006):

1. Actor/role?  
2. Permission?  
3. Must session be `InProgress`?  
4. Can Present be recorded while Scheduled?  
5. Can Present → Absent?  
6. Does Present affect grade eligibility?  
7. Attendance fact vs exam participation vs both?

### Current Evidence

- Enums: Present (3) is active seat; Absent (4) inactive  
- Master Lock §10.3: actor/permission/session-open policy **TBD**; role mapping **DEFERRED**  
- HD-006: **APPROVED AS DEFERRED** to Phase 7.2 Design Lock  
- `StudentGradeWriteGuard`: active seat includes Present; does **not** require Present to enter grade  
- Seat Absent ≠ grade `is_absent` — Master Lock LOCKED distinction

### Existing Authority

HD-006; Master Lock §10.3; Readiness Audit §8 / §15.2

### Current Status

**HUMAN DECISION REQUIRED**

### Options

A. Exam admin only via `exam.enrollment.update`; require session InProgress  
B. Invigilator/teacher via separate permission; require InProgress  
C. Allow Present while Scheduled  
D. Auto-Present on grade enter — **NOT recommended** (conflates domains)  
E. Defer Present transition out of 7.2 writers entirely (leave enum unused)

### Architectural / Security / Data Integrity Impact

Affects AuthZ matrix, UpdateExamEnrollment transition matrix, and possibly grade-entry timing (DD-013).

### Recommendation

Do **not** auto-mark Present on grade enter. Prefer Option A or B with **session must be InProgress** as default Design Lock candidate. Keep Present ≠ `is_absent`. Present→Absent remains Master Lock allowed (Registered|Confirmed|Present → Absent) unless human forbids.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES**

### Blocks Implementation?

**YES** for any UpdateExamEnrollment path that includes Present; can Design-Lock “Present transition deferred within 7.2” only if explicitly scoped

### Dependencies

DD-004; DD-013; DD-002

### Reversibility

**MEDIUM**

### Decision Owner

**BUSINESS** / **SECURITY** / **HUMAN PROJECT OWNER**

### Final Decision

**UNRESOLVED** (HD-006 still deferred to Design Lock resolution)

---

## 7.2-DD-006 — Multiple Sessions Per Subject

### Question

May one Exam have multiple `exam_sessions` for the same `subject_id` (make-ups, parallel rooms, cohorts, resits, accommodations)?

### Current Evidence

- DB: **no** UNIQUE `(exam_id, subject_id)`  
- Indexes: `(subject_id, session_date)` only  
- Product rule: **UNKNOWN**

### Existing Authority

Schema migration Phase 3A; Readiness Audit §5 / §16

### Current Status

**HUMAN DECISION REQUIRED**

### Options

A. Allow multiple sessions per subject (status quo schema)  
B. Enforce one session per subject per exam (app + optional future unique)  
C. Allow multiple only when tagged (make-up/resit) — requires schema later

### Architectural / Security / Data Integrity Impact

Affects CreateExamSession validation, reassignment (DD-010), and enrollment uniqueness across sessions of same subject.

### Recommendation

Option A for Phase 7.2 (no new unique constraint) unless product mandates single-session-per-subject. Document make-up as separate session rows.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES**

### Blocks Implementation?

**YES** for CreateExamSession uniqueness rules

### Dependencies

DD-010; DD-001

### Reversibility

**LOW** if unique constraint added later with historical multi-session data

### Decision Owner

**BUSINESS** / **HUMAN PROJECT OWNER**

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-007 — Session / Room Overlap

### Question

Must the system prevent same-room overlapping `session_date` + time ranges? Do teachers/invigilators create additional conflicts?

### Current Evidence

- CHECK: `end_time > start_time` only  
- `room_id` nullable FK — **no** exclusion constraint  
- Invigilator assignment table: **ABSENT** on exam_sessions

### Existing Authority

Schema; Master Lock (no room-overlap lock found); Readiness Audit DD-007

### Current Status

**HUMAN DECISION REQUIRED** (product); default architectural stance **DEFERRED** for 7.2 enforcement

### Options

A. No overlap enforcement in 7.2 (advisory later)  
B. Fail-closed same room + overlapping times  
C. Soft warning only (requires UI — out of HTTP scope)

### Architectural / Security / Data Integrity Impact

Scheduling integrity vs implementation cost; teacher conflicts need separate assignment model (not present).

### Recommendation

Option A for Phase 7.2 Design Lock — **do not** implement room exclusion constraints. Teacher/invigilator conflict = **OUT OF SCOPE** until assignment model exists.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** (if product requires hard scheduling)

### Blocks Implementation?

**NO** if Design Lock explicitly defers overlap enforcement

### Dependencies

DD-025 (room isolation); DD-026 (timezone)

### Reversibility

**HIGH** (can add later)

### Decision Owner

**BUSINESS** / **DATABASE**

### Final Decision

**UNRESOLVED** (recommend DEFER enforcement)

---

## 7.2-DD-008 — Academic Year Match

### Question

Confirm CreateExamEnrollment must fail closed when `enrollment.academic_year_id != exam.academic_year_id`, and whether a DB CHECK is required later.

### Current Evidence

- Master Lock §9.6 / CreateExamEnrollment: mismatch **FAIL CLOSED** — **LOCKED** design  
- Session/enrollment tables: **no** `academic_year_id` column (inherit via parent exam) — **LOCKED**  
- DB CHECK spanning enrollment↔exam years: **ABSENT**

### Existing Authority

Master Lock P7.1 academic-year contract; Readiness Audit §6 / DD-008

### Current Status

**LOCKED** — application fail-closed rule  
**PROPOSAL** — optional future DB enforcement (not required to start Design Lock)

### Options

A. Handler-only fail-closed (required)  
B. Handler + future composite/trigger CHECK (later phase)  
C. Denormalize `academic_year_id` onto enrollments — **FORBIDDEN** by Master Lock without DCR

### Architectural / Security / Data Integrity Impact

Year mismatch creates cross-year grade graph corruption risk.

### Recommendation

Option A mandatory for CreateExamEnrollment. Option B only after measured need; never Option C in 7.2.

### Recommendation Status

**PROPOSAL ONLY** for DB CHECK; fail-closed app rule is LOCKED

### Human Decision Required

**NO** for fail-closed app rule; **YES** only if someone proposes denormalization or skipping the check

### Blocks Implementation?

**YES** — handler must encode fail-closed (but decision content is LOCKED)

### Dependencies

DD-002 CreateExamEnrollment

### Reversibility

**LOW** for denormalization; **HIGH** for adding later CHECK

### Decision Owner

**ARCHITECTURE** / **DATABASE**

### Final Decision

**LOCKED — CreateExamEnrollment fail-closed on year mismatch. No denormalized year column. DB CHECK = UNRESOLVED / optional later.**

---

## 7.2-DD-009 — CancelExamSession vs CancelExam Cascade

### Question

How do dedicated CancelExamSession / CancelExamEnrollment interact with CancelExam cascade regarding idempotency, grades, seats, and event causation?

Must define behavior for:

1. CancelExamSession on already Cancelled session  
2. CancelExam after individual session cancellation  
3. CancelExamSession when session has active seats  
4. CancelExamSession when session has CURRENT grades  

### Current Evidence

- CancelExam: fails closed on CURRENT grade / Completed session / Completed|Cancelled exam; cascades open sessions + active seats; stages cascade events with `cause: exam_cancel`  
- CancelExamSession (app): **ABSENT**  
- Master Lock CancelExamSession: Scheduled|InProgress → Cancelled; grade **enter** blocked on Cancelled; **no** explicit CURRENT-grade guard on session cancel  
- DR-002: CancelExamEnrollment fails closed if CURRENT grade  

### Existing Authority

DR-001 / DR-002; CancelExamHandler; cascade event classes; Master Lock §9.3

### Current Status

**HUMAN DECISION REQUIRED** (especially CURRENT grades on CancelExamSession + seat cascade on session cancel)

### Options

**Already-cancelled session:**  
A1. Idempotent success (no-op + replay-safe)  
A2. Fail closed “already cancelled”

**Seats on CancelExamSession:**  
B1. Cascade active seats → Withdrawn (mirror CancelExam child behavior)  
B2. Fail closed if any active seat remains  
B3. Leave seats active (inconsistent with Cancelled session grade rules)

**CURRENT grades on CancelExamSession:**  
C1. Fail closed if any CURRENT grade on session (mirror CancelExam)  
C2. Allow cancel; rely on enter-block only (correct-path gap = DD-012)  
C3. Auto-void grades — **FORBIDDEN** (DR-001 spirit)

**CancelExam after partial session cancels:**  
D1. Cancel remaining open sessions only; already Cancelled skipped (idempotent cascade) — matches current cascade query intent

### Architectural / Security / Data Integrity Impact

**HIGH.** Wrong choice creates orphaned active seats, grade/session contradiction, or duplicate audit events.

### Recommendation

- A1 idempotent no-op for already Cancelled  
- B1 withdraw active seats atomically with session cancel  
- C1 fail closed on CURRENT grades  
- D1 keep CancelExam cascade as authoritative; skip already-cancelled  
- Events: distinguish `cause: exam_session_cancel` vs `exam_cancel` (DD-017)  
- Never mutate grades from session/enrollment cancel

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES**

### Blocks Implementation?

**YES** — **CRITICAL** for CancelExamSession / CancelExamEnrollment

### Dependencies

DD-012; DD-017; DR-001; DR-002

### Reversibility

**LOW**

### Decision Owner

**BUSINESS** / **ARCHITECTURE** / **HUMAN PROJECT OWNER**

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-010 — Move / Reassign Enrollment

### Question

May an exam enrollment move from Session A → Session B under the same exam (or otherwise)?

### Current Evidence

- Identity: `UNIQUE(exam_session_id, enrollment_id)` — move = change of identity FK  
- No Move command in HD-004  
- Grade FKs bind to exam_enrollment + session composites  
- Present/Absent/Withdrawn/grade history complicate safe move

### Existing Authority

HD-004 command list; schema uniqueness; Readiness Audit DD-010

### Current Status

**HUMAN DECISION REQUIRED** (default design stance: **FORBIDDEN**)

### Options

A. **FORBIDDEN** in Phase 7.2 (withdraw + create new seat)  
B. Dedicated Move command with grade/session guards  
C. Allow Update to change `exam_session_id` — **NOT recommended** (violates Update immutability)

### Architectural / Security / Data Integrity Impact

Moving seats with CURRENT grades or Present status risks silent history corruption.

### Recommendation

Option A — **FORBIDDEN** unless Design Change Request. Operational path: CancelExamEnrollment (if no CURRENT grade) + CreateExamEnrollment on target session.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** (to affirm FORBIDDEN or approve Move)

### Blocks Implementation?

**YES** if left ambiguous; **NO** if Design Lock marks FORBIDDEN

### Dependencies

DD-002; DD-006; DD-009; DD-011

### Reversibility

**LOW** once Move ships with production data

### Decision Owner

**BUSINESS** / **ARCHITECTURE**

### Final Decision

**UNRESOLVED** (recommend FORBIDDEN)

---

## 7.2-DD-011 — Restore Enrollment

### Question

May Withdrawn/Absent return to Registered/Confirmed/Present?

### Current Evidence

Master Lock §10.3: Absent|Withdrawn → active = **FORBIDDEN** without Design Change.

### Existing Authority

Master Lock §10.3; Readiness Audit DD-011

### Current Status

**LOCKED** — **FORBIDDEN**

### Options

A. Keep FORBIDDEN  
B. Design Change Request to allow restore — **out of this register’s power**

### Architectural / Security / Data Integrity Impact

Restore would revive seats that grade enter currently treats as inactive; audit/idempotency complexity.

### Recommendation

Keep FORBIDDEN. No Restore command in Phase 7.2.

### Recommendation Status

**PROPOSAL ONLY** (affirmation); underlying rule is LOCKED

### Human Decision Required

**NO** unless requesting a Design Change

### Blocks Implementation?

**NO**

### Dependencies

DD-002; DD-010

### Reversibility

**LOW** if later allowed without careful history rules

### Decision Owner

**ARCHITECTURE** (Master Lock)

### Final Decision

**LOCKED — Restore FORBIDDEN without Design Change Request**

---

## 7.2-DD-012 — Grade Correction After Session Cancellation

### Question

Must `CorrectStudentGrade` fail closed when session or exam is Cancelled?

### Current Evidence

- `StudentGradeWriteGuard::assertCanEnter` rejects Cancelled session/exam  
- `assertCanCorrect` checks current/void/score/chain **only** — **does not** re-check session/exam cancellation  
- CancelExam blocked when CURRENT grade exists — so new cancels shouldn’t create CURRENT+Cancelled normally; historical paths / future CancelExamSession (DD-009 Option C2) could

### Existing Authority

Live `StudentGradeWriteGuard`; Readiness Audit §8; Grade handlers (not to be modified in this task)

### Current Status

**HUMAN DECISION REQUIRED**

### Options

A. Correct must fail closed if session or exam Cancelled  
B. Allow correct on Cancelled parent (history repair)  
C. Allow correct only for VOID/chain repair under controlled security role

### Architectural / Security / Data Integrity Impact

**HIGH.** Gap enables CURRENT grade mutations under cancelled administration if session cancel is allowed with existing grades (DD-009).

### Recommendation

Option A — align Correct with Enter for Cancelled parents. Implementation belongs to a **separate authorized grade change**, not silent Phase 7.2 side-effect — but Design Lock must state the invariant.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES**

### Blocks Implementation?

**YES** for safe CancelExamSession semantics; Phase 7.2 session writers may still Design-Lock with explicit dependency on grade-guard follow-up

### Dependencies

DD-009; Grade CQRS (out of 7.2 write scope)

### Reversibility

**MEDIUM**

### Decision Owner

**BUSINESS** / **SECURITY** / **ARCHITECTURE**

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-013 — Grade Entry Requires Session InProgress

### Question

Which session statuses allow grade entry?

```text
Scheduled → ?
InProgress → ?
Completed → ?
Cancelled → denied (DEFINED today)
```

### Current Evidence

Enter guard: Cancelled session/exam blocked; **does not** require InProgress. Completed sessions can currently receive enter if seat active (behavior DEFINED by omission).

### Existing Authority

`StudentGradeWriteGuard::assertCanEnter`; Master Lock grade enter vs session open not locked as InProgress-only

### Current Status

**HUMAN DECISION REQUIRED**

### Options

A. Enter only when InProgress  
B. Enter when Scheduled or InProgress  
C. Enter when InProgress or Completed (post-close scoring window)  
D. Keep current (anything except Cancelled + active seat)

### Architectural / Security / Data Integrity Impact

Couples CloseExamSession timing to grading operations; affects DD-005 Present policy.

### Recommendation

Option C as candidate (allow Completed for late entry window) **or** A if product wants strict live-session grading — **do not** invent; human must choose. Cancelled remains denied.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES**

### Blocks Implementation?

**NO** for session Open/Close themselves; **YES** for declaring end-to-end exam ops integrity in Design Lock

### Dependencies

DD-005; DD-014; Grade Enter (separate)

### Reversibility

**MEDIUM**

### Decision Owner

**BUSINESS** / **HUMAN PROJECT OWNER**

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-014 — Exam Completion Ownership

### Question

How does CloseExamSession feed DR-004 exam completion without introducing CompleteExam?

### Current Evidence

- DR-004 LOCKED: Exam→Completed only when ≥1 session, no Scheduled/InProgress left, every non-cancelled Completed, cancelled stay cancelled, no auto-close  
- CompleteExam command: **OUT OF SCOPE** / requires DCR  
- UpdateExam remains the exam-status transition path (Phase 7.1 closed)

### Existing Authority

DR-004; Phase 7.1 closure; Master Lock §10.1

### Current Status

**LOCKED**

### Options

A. CloseExamSession only completes **sessions**; Exam Completed remains UpdateExam + DR-004  
B. CloseExamSession auto-completes Exam when predicates met — **conflicts** with “no CompleteExam / no auto” spirit  
C. Introduce CompleteExam in 7.2 — **OUT OF SCOPE**

### Architectural / Security / Data Integrity Impact

Keeps Phase 7.1 closed surface intact; prevents hidden exam completion side effects.

### Recommendation

Option A only.

### Recommendation Status

**PROPOSAL ONLY** (affirms LOCKED DR-004)

### Human Decision Required

**NO**

### Blocks Implementation?

**NO**

### Dependencies

DD-001 CloseExamSession; DR-004

### Reversibility

**LOW** if auto-complete introduced later without DCR

### Decision Owner

**ARCHITECTURE**

### Final Decision

**LOCKED — CloseExamSession does not CompleteExam. Exam completion remains UpdateExam + DR-004. CompleteExam OUT OF SCOPE.**

---

## 7.2-DD-015 — HTTP Exposure

### Question

When may Phase 7.2 commands be exposed over HTTP?

### Current Evidence

- Phase 7.1 exam admin HTTP: **BLOCKED**  
- Session/enrollment lifecycle routes: **ABSENT**  
- Grade GETs on exam-sessions/enrollments: **PRESENT** (reads only)

### Existing Authority

Phase 7.1 closure; HD-001 HTTP block pattern; Readiness Audit §14

### Current Status

**DEFERRED** / **LOCKED as deferred for Phase 7.2**

### Options

A. Keep HTTP DEFERRED until separate exposure authorization after permission lock + handler security + RLS tests + API contract + idempotency transport  
B. Expose with 7.2 implementation — **NOT recommended**

### Architectural / Security / Data Integrity Impact

Premature HTTP creates AuthZ/tenant exposure before DD-004 resolution.

### Recommendation

Option A. Prerequisites checklist: permission lock, handler AuthZ, SchoolContext, RLS runtime tests, FormRequest/DTO, idempotency key transport, outbox, correlation ID.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** before any future exposure grant (not for keeping deferred)

### Blocks Implementation?

**NO** for command-layer implementation authorization later; HTTP remains separately blocked

### Dependencies

DD-003; DD-004; DD-016; DD-019; DD-020

### Reversibility

**HIGH** while deferred

### Decision Owner

**SECURITY** / **HUMAN PROJECT OWNER**

### Final Decision

**LOCKED FOR PHASE 7.2 — HTTP = DEFERRED / NOT AUTHORIZED**

---

## 7.2-DD-016 — Idempotency

### Question

Must every Phase 7.2 writer use required idempotency key, payload fingerprint, same-COMMIT idempotency + outbox, fail-closed payload conflict, with event identity ≠ idempotency identity?

### Current Evidence

- Phase 7.1 Create/Update/CancelExam: same-COMMIT pattern **IMPLEMENTED**  
- P7-D5 / DR-006: **LOCKED**  
- Infrastructure: `audit.outbox_messages`, `audit.idempotency_keys` — do not redesign

### Existing Authority

Master Lock §14; DR-006; Phase 7.1 implementation; Readiness Audit §13

### Current Status

**LOCKED** (architectural requirement for Phase 7.2 writers)

### Options

A. Require same pattern as Phase 7.1 for all eight writers  
B. Optional keys until HTTP — **rejected** by P7-D5 for externally exposed; for internal-first still recommend required keys for parity  
C. Post-commit idempotency store — **FORBIDDEN** (Grade anti-pattern)

### Architectural / Security / Data Integrity Impact

Replay safety; prevents double seats/double opens.

### Recommendation

Option A for all Phase 7.2 writers from first implementation.

### Recommendation Status

**PROPOSAL ONLY** (affirms LOCKED pattern)

### Human Decision Required

**NO**

### Blocks Implementation?

**YES** — must implement pattern (requirement LOCKED)

### Dependencies

DD-001; DD-002; DD-017

### Reversibility

**LOW**

### Decision Owner

**ARCHITECTURE**

### Final Decision

**LOCKED — Required idempotency key + fingerprint + same-COMMIT outbox/idempotency + fail-closed conflict; event identity ≠ idempotency identity**

---

## 7.2-DD-017 — Event Causation

### Question

How do direct Phase 7.2 cancel commands distinguish causation from CancelExam cascade without duplicate/ambiguous audit history?

### Current Evidence

Cascade payloads already include `'cause' => 'exam_cancel'` on `ExamSessionCancelled` / `ExamEnrollmentCancelled`.  
Direct command events: **ABSENT**.  
Master Lock design names: ExamSessionCreated/Updated/Opened/Closed/Cancelled; ExamEnrollmentCreated/Updated/Cancelled.

### Existing Authority

Event classes; DR-006; CancelExamHandler; Readiness Audit DD-017

### Current Status

**DEFINED** (cascade cause exists); **PROPOSAL** for direct-command cause vocabulary

### Options

A. Reuse same event classes with `cause: exam_session_cancel` / `exam_enrollment_cancel`  
B. Separate event types for direct vs cascade  
C. Omit cause on direct commands — **NOT recommended**

### Architectural / Security / Data Integrity Impact

Audit consumers and idempotency must not treat cascade child events as command replays.

### Recommendation

Option A: keep class names; mandatory `cause` discriminator; command_name on outbox envelope as today; never reuse CancelExam idempotency keys for session commands.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**NO** for requiring a discriminator; **YES** only if choosing Option B (new types)

### Blocks Implementation?

**YES** — event payload contract must be Design-Locked before coding events

### Dependencies

DD-009; DD-016

### Reversibility

**MEDIUM**

### Decision Owner

**ARCHITECTURE**

### Final Decision

**UNRESOLVED** (cascade cause DEFINED; direct-command contract PROPOSAL)

---

## 7.2-DD-018 — updated_at

### Question

Do lifecycle commands require `updated_at` / optimistic concurrency columns on sessions/enrollments?

### Current Evidence

Tables have `created_at` only — **no** `updated_at`.  
Phase 7.1 exams table has timestamps; sessions/enrollments intentionally minimal.

### Existing Authority

Schema Phase 3A; Readiness Audit DD-018

### Current Status

**PROPOSAL**

### Options

A. Do **not** add `updated_at` merely for convention  
B. Add `updated_at` + optimistic lock version via migration  
C. Use status + unique constraints only for concurrency

### Architectural / Security / Data Integrity Impact

Schema change triggers full database-change governance; weak justification today.

### Recommendation

Option A + C for Phase 7.2. Rely on status transition guards + UNIQUE seat constraint + idempotency. Revisit only if measured lost-update incidents.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**NO** unless requesting schema change

### Blocks Implementation?

**NO**

### Dependencies

DD-028 (if added)

### Reversibility

**HIGH** if deferred

### Decision Owner

**DATABASE** / **ARCHITECTURE**

### Final Decision

**UNRESOLVED** (recommend no `updated_at` in 7.2)

---

## 7.2-DD-019 — RLS WITH CHECK

### Question

Given session/enrollment policies use USING without explicit WITH CHECK, what must Phase 7.2 require for verification?

### Current Evidence

- ENABLE + FORCE RLS on sessions/enrollments  
- Policy USING school isolation; WITH CHECK omitted → PostgreSQL applies USING for both  
- Grades have explicit WITH CHECK  
- Phase 7.1 RLS Verification `17` PASS for exams graph including these tables

### Existing Authority

RLS migrations; artifact `17`; Readiness Audit §12

### Current Status

**DEFINED** (PG semantics); **PROPOSAL** for mandatory 7.2 runtime test matrix

### Options

A. Document USING-only as acceptable; require 7.2 PG tests for INSERT/UPDATE same-school, cross-school, missing GUC, FORCE RLS  
B. Amend policies to add explicit WITH CHECK — schema/RLS change (**not authorized** now)  
C. Rely only on Phase 7.1 evidence without new tests — weaker

### Architectural / Security / Data Integrity Impact

Tenant isolation for new writers must be proven under writer paths, not only CancelExam cascade.

### Recommendation

Option A. Do not change RLS in Design Lock. Require dedicated runtime verification before Phase 7.2 closure (mirror artifact 17 scope for session/enrollment writers).

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**NO** for verification requirement; **YES** before any RLS policy amendment

### Blocks Implementation?

**NO** for starting implementation after AuthZ; **YES** for final closure without tests

### Dependencies

DD-015; implementation AuthZ gate (future)

### Reversibility

**HIGH**

### Decision Owner

**SECURITY** / **DATABASE**

### Final Decision

**PARTIAL — PG USING semantics DEFINED. Explicit WITH CHECK amendment = NOT AUTHORIZED. Runtime verification requirement = PROPOSAL / to be Design-Locked.**

---

## 7.2-DD-020 — Query AuthZ

### Question

Does Phase 7.2 authorize session/enrollment queries or view permissions?

### Current Evidence

HD-002/HD-003: query AuthZ deferred for exam administration.  
No `exam.session.view` / `exam.enrollment.view` in catalog.

### Existing Authority

10A HD-002 / HD-003; Readiness Audit DD-020

### Current Status

**DEFERRED** / **OUT OF SCOPE** for Phase 7.2 writers

### Options

A. Keep Query AuthZ DEFERRED  
B. Add view permissions in 7.2 — requires separate human AuthZ

### Architectural / Security / Data Integrity Impact

Handlers may load rows internally under SchoolContext without creating public query permissions.

### Recommendation

Option A. Internal reads for command preconditions allowed; no query handlers/routes/permissions.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**NO** to keep deferred; **YES** to introduce views

### Blocks Implementation?

**NO**

### Dependencies

DD-015

### Reversibility

**HIGH**

### Decision Owner

**SECURITY**

### Final Decision

**LOCKED FOR PHASE 7.2 — Query AuthZ = DEFERRED / NOT AUTHORIZED**

---

## 7. Additional Decisions

---

## 7.2-DD-021 — Batch Enrollment Assignment

### Question

Is bulk seat assignment in Phase 7.2?

### Current Evidence

Master Lock §9.2: bulk import / multi-step workflows = **DEFER** until product proven.

### Current Status

**DEFERRED**

### Options

A. Defer bulk; single CreateExamEnrollment only  
B. Add BulkAssignExamEnrollments in 7.2

### Recommendation

Option A.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** only to override deferral

### Blocks Implementation?

**NO** if deferred

### Final Decision

**DEFERRED — bulk out of Phase 7.2 unless Design Change**

---

## 7.2-DD-022 — Seat Number Generation / Uniqueness

### Question

Is `seat_number` unique per session? Auto-generated?

### Current Evidence

`seat_number` nullable string(10); **no** UNIQUE; immutable when Present or CURRENT grade (Master Lock).

### Current Status

**HUMAN DECISION REQUIRED** (product); schema allows duplicates

### Options

A. Optional free-text; no uniqueness  
B. Unique per session when not null  
C. System-generated sequential seats

### Recommendation

Option A for 7.2 unless product requires uniqueness (then app unique + optional later index).

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** if uniqueness required

### Blocks Implementation?

**NO** if optional free-text affirmed

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-023 — Concurrent Assignment Race Handling

### Question

How must CreateExamEnrollment behave under concurrent duplicate `(exam_session_id, enrollment_id)` inserts?

### Current Evidence

DB UNIQUE constraint exists — second insert fails at PG.

### Current Status

**DEFINED** (DB); **PROPOSAL** for application mapping to idempotent replay vs conflict error

### Options

A. Map unique violation to domain conflict; idempotency key handles true retries  
B. Catch and treat as success if seat exists with identical payload

### Recommendation

Option A + fingerprint: same key/same payload replay; different payload fail-closed; unique race → conflict domain error (not silent invent).

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**NO**

### Blocks Implementation?

**YES** — error-mapping must be Design-Locked

### Final Decision

**UNRESOLVED** (DB uniqueness DEFINED)

---

## 7.2-DD-024 — Session Capacity Enforcement

### Question

Must CreateExamEnrollment enforce max seats / room capacity?

### Current Evidence

No `max_seats` column; `room_id` nullable without capacity join required.

### Current Status

**DEFERRED** / **HUMAN DECISION REQUIRED** if product needs capacity

### Recommendation

No capacity enforcement in Phase 7.2.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** to require capacity

### Blocks Implementation?

**NO** if deferred

### Final Decision

**DEFERRED — no capacity enforcement in 7.2**

---

## 7.2-DD-025 — Room Ownership / School Isolation

### Question

Must Create/UpdateExamSession verify `room_id` belongs to the same school?

### Current Evidence

`room_id` is nullable `foreignId` — composite school FK to rooms **not** evident on exam_sessions migration snippet; school isolation relies on session `school_id` + RLS. Cross-school room id reference risk = **UNKNOWN** without rooms table composite contract review at Design Lock.

### Current Status

**HUMAN DECISION REQUIRED** / integrity verification required before implementation

### Options

A. Require same-school room validation in handler when room_id set  
B. Rely solely on rooms RLS + FK  
C. Disallow room_id until room school composite proven

### Recommendation

Option A fail-closed same-school room check when `room_id` present; confirm rooms schema at Design Lock evidence appendix.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** if room assignment is in 7.2 Update allowlist (it is, per Master Lock)

### Blocks Implementation?

**YES** for UpdateExamSession room_id path

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-026 — Timezone / Session Date Boundaries

### Question

What timezone semantics apply to `session_date` / `start_time` / `end_time`?

### Current Evidence

Stored without timestamptz session columns; school TZ policy **UNKNOWN** at exam_sessions.

### Current Status

**HUMAN DECISION REQUIRED** / **DEFERRED** for deep calendar integration

### Recommendation

Treat fields as school-local civil date/time without conversion in 7.2; no academic-calendar gate unless product requires.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** for calendar gating; **NO** if civil local affirmed

### Blocks Implementation?

**NO** if civil local affirmed in Design Lock

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-027 — CancelExamSession CURRENT-Grade Guard (Explicit)

### Question

Independent restatement of DD-009 C-options: does CURRENT grade on any seat of the session block CancelExamSession?

### Current Evidence

CancelExam: YES block. CancelExamEnrollment: YES block (DR-002). CancelExamSession: Master Lock silent.

### Current Status

**HUMAN DECISION REQUIRED** — **CRITICAL**

### Recommendation

Fail closed (align with CancelExam / DR-002 spirit). No grade mutation.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES**

### Blocks Implementation?

**YES**

### Dependencies

DD-009; DD-012

### Final Decision

**UNRESOLVED**

---

## 7.2-DD-028 — Scheduled → Completed Skip

### Question

May CloseExamSession transition Scheduled → Completed without InProgress?

### Current Evidence

Master Lock CloseExamSession: primary InProgress→Completed; Scheduled→Completed = **DEFER** unless product requires skip.

### Current Status

**DEFERRED** / **LOCKED as deferred** unless Design Change

### Recommendation

Forbid Scheduled→Completed in Phase 7.2; require Open then Close.

### Recommendation Status

**PROPOSAL ONLY**

### Human Decision Required

**YES** only to allow skip

### Blocks Implementation?

**NO** if skip remains forbidden

### Final Decision

**DEFERRED — Scheduled→Completed not in Phase 7.2**

---

## 8. Session State Machine Decisions

| From | To | Allowed? | Command | Permission (candidate) | Preconditions | Grade implications | Audit/event |
|------|----|----------|---------|------------------------|---------------|--------------------|-------------|
| Scheduled | InProgress | **LOCKED** (design) | OpenExamSession | `exam.session.open` | Parent exam not Cancelled; not terminal | Enter policy = DD-013 | ExamSessionOpened (future) |
| InProgress | Completed | **LOCKED** (design) | CloseExamSession | `exam.session.close` | Not Cancelled | Enter policy = DD-013 | ExamSessionClosed (future) |
| Scheduled | Cancelled | **LOCKED** | CancelExamSession **or** CancelExam cascade | `exam.session.update` / exam.cancel cascade | DD-009 / DD-027 guards TBD | Enter denied on Cancelled (**DEFINED**) | cause discriminator (DD-017) |
| InProgress | Cancelled | **LOCKED** | same | same | DD-009 / DD-027 | same | same |
| Scheduled | Completed | **DEFERRED** | — | — | Forbidden unless DCR (DD-028) | — | — |
| Completed | * | **FORBIDDEN** | — | — | — | — | — |
| Cancelled | * | **FORBIDDEN** | — | — | No reopen | — | — |
| * (metadata) | * | Scheduled-only allowlist | UpdateExamSession | `exam.session.update` | DR-003; CURRENT grade freezes max/pass | — | ExamSessionUpdated |

**Ownership:** academic year via parent exam only (**LOCKED**).

---

## 9. Enrollment State Machine Decisions

| From | To | Allowed? | Command | Permission (candidate) | Preconditions | Grade implications | Audit/event |
|------|----|----------|---------|------------------------|---------------|--------------------|-------------|
| (new) | Registered | **LOCKED** create | CreateExamEnrollment | `exam.enrollment.create` | Year match DD-008; session not Cancelled; unique seat; active academic enrollment | — | ExamEnrollmentCreated |
| Registered | Confirmed | **LOCKED** (design) | UpdateExamEnrollment | `exam.enrollment.update` | Session not Cancelled | Active seat | ExamEnrollmentUpdated |
| Confirmed | Present | **HUMAN DECISION REQUIRED** (DD-005 / HD-006) | UpdateExamEnrollment | TBD | Session status TBD | Active seat; Present ≠ `is_absent` | ExamEnrollmentUpdated |
| Registered\|Confirmed\|Present | Absent | **LOCKED** (design) | UpdateExamEnrollment | `exam.enrollment.update` | — | Inactive → enter blocked | ExamEnrollmentUpdated |
| *active* | Withdrawn | **LOCKED** | CancelExamEnrollment **or** CancelExam cascade | `exam.enrollment.cancel` / cascade | DR-002 CURRENT grade fail-closed for dedicated cancel | No grade mutation | cause discriminator |
| Absent\|Withdrawn | active | **FORBIDDEN** (DD-011) | — | — | — | — | — |
| Session A → Session B | — | **HUMAN DECISION REQUIRED** (DD-010; recommend FORBIDDEN) | — | — | — | Grade FK risk | — |

`isActiveSeat` = Registered | Confirmed | Present — **DEFINED**.

---

## 10. Security / Permission Decisions

| Operation | Permission | Proposed Role | Current Status | Human Approval Required |
|-----------|------------|---------------|----------------|-------------------------|
| CreateExamSession | `exam.session.create` | **UNRESOLVED** (candidate: `grades_manager`) | Vocabulary DEFINED; catalog ABSENT; ownership UNRESOLVED | **YES** |
| UpdateExamSession | `exam.session.update` | **UNRESOLVED** | same | **YES** |
| OpenExamSession | `exam.session.open` | **UNRESOLVED** | same | **YES** |
| CloseExamSession | `exam.session.close` | **UNRESOLVED** | same | **YES** |
| CancelExamSession | `exam.session.update` | **UNRESOLVED** | HD-005 **LOCKED** name; ownership UNRESOLVED | **YES** (ownership) |
| CreateExamEnrollment | `exam.enrollment.create` | **UNRESOLVED** | same | **YES** |
| UpdateExamEnrollment | `exam.enrollment.update` | **UNRESOLVED** (+ DD-005 actor split?) | same | **YES** |
| CancelExamEnrollment | `exam.enrollment.cancel` | **UNRESOLVED** | same | **YES** |

```text
HD-001 does NOT grant exam.session.* or exam.enrollment.*.
exam.session.cancel MUST NOT be introduced (HD-005).
```

---

## 11. Grade Integrity Decisions

| Topic | Status | Notes |
|-------|--------|-------|
| Grade → session/seat FKs | **LOCKED** | Composite FKs |
| Enter on Cancelled session/exam | **DEFINED** | Rejected |
| Enter on inactive seat | **DEFINED** | Rejected |
| Enter requires InProgress | **HUMAN DECISION REQUIRED** | DD-013 |
| Correct on Cancelled parent | **HUMAN DECISION REQUIRED** | DD-012 |
| CancelExamEnrollment vs CURRENT grade | **LOCKED** (DR-002) | Fail closed; no grade mutation |
| CancelExamSession vs CURRENT grade | **HUMAN DECISION REQUIRED** | DD-027 |
| Present vs `is_absent` | **LOCKED** | Distinct facts |
| CompleteExam | **OUT OF SCOPE** | DD-014 |

---

## 12. RLS / School Isolation Decisions

| Topic | Status |
|-------|--------|
| ENABLE + FORCE RLS on sessions/enrollments | **DEFINED** / proven |
| School predicate via `app.current_school_id` | **DEFINED** |
| Explicit WITH CHECK on session/enrollment policies | **ABSENT**; PG USING applies to both — **DEFINED** semantics |
| Policy amendment | **NOT AUTHORIZED** |
| Phase 7.2 writer RLS runtime tests | **PROPOSAL** required before closure (DD-019) |
| Room same-school validation | **UNRESOLVED** (DD-025) |

---

## 13. Outbox / Idempotency Decisions

| Topic | Status |
|-------|--------|
| same-COMMIT mutation + outbox + idempotency | **LOCKED** (DD-016) |
| Required key + fingerprint + fail-closed conflict | **LOCKED** |
| event identity ≠ idempotency identity | **LOCKED** (DR-006) |
| Redesign audit tables | **FORBIDDEN** |
| Post-commit idempotency store | **FORBIDDEN** |

---

## 14. Event Causation Decisions

| Topic | Status |
|-------|--------|
| Cascade `cause: exam_cancel` | **DEFINED** (implemented) |
| Direct command cause values | **PROPOSAL** (`exam_session_cancel` / `exam_enrollment_cancel`) |
| Separate event classes vs shared + cause | **PROPOSAL** prefer shared + cause (DD-017) |
| New event types implementation | **NOT AUTHORIZED** in this task |

---

## 15. HTTP Boundary Decision

```text
HTTP exposure for Phase 7.2 lifecycle writers = DEFERRED / NOT AUTHORIZED
Grade GET endpoints on exam-sessions/enrollments = existing reads only — not lifecycle authorization
Query AuthZ = DEFERRED (DD-020)
```

---

## 16. Decision Dependency Graph

```text
DD-004 Permission Ownership (CRITICAL)
        ↓
DD-003 Permission Vocabulary (catalog registration)
        ↓
Human Security Lock
        ↓
Future Implementation Authorization Gate
        ↓
Implementation (NOT NOW)

DD-005 Present Transition (HD-006)
        ↓
DD-013 Grade Entry Timing
        ↓
UpdateExamEnrollment transition matrix

DD-006 Multi-Session Rule
        ↓
DD-010 Reassignment
        ↓
Enrollment identity strategy (unique session+enrollment)

DD-009 Cancellation Semantics ←→ DD-027 CURRENT-grade on session cancel
        ↓
DD-012 Grade Correction after cancel
        ↓
DD-017 Event Causation

DD-008 Academic Year Match
        ↓
CreateExamEnrollment

DD-001 / DD-002 Command sets (LOCKED via HD-004)
        ↓
DD-016 Idempotency (LOCKED)
        ↓
DD-014 CloseExamSession feeds DR-004 (no CompleteExam)

DD-015 HTTP DEFERRED
DD-020 Query AuthZ DEFERRED
DD-019 RLS verification (closure gate)
DD-011 Restore FORBIDDEN (LOCKED)
DD-018 updated_at (recommend none)
DD-021 Bulk DEFERRED
DD-022 Seat number
DD-023 Race / unique mapping
DD-024 Capacity DEFERRED
DD-025 Room school check
DD-026 Timezone
DD-028 Scheduled→Completed DEFERRED
```

---

## 17. Human Decision Summary

| Decision ID | Topic | Priority | Why Human Decision Is Required | Recommendation |
|-------------|-------|----------|--------------------------------|----------------|
| 7.2-DD-004 | Permission ownership | **CRITICAL** | No approved role for session/enrollment perms; HD-001 must not inherit | Candidate map to `grades_manager` — **PROPOSAL ONLY** |
| 7.2-DD-027 | CancelExamSession vs CURRENT grade | **CRITICAL** | Master Lock silent; integrity vs CancelExam asymmetry | Fail closed — **PROPOSAL ONLY** |
| 7.2-DD-009 | Cancel session/seat cascade semantics | **CRITICAL** | Idempotency, seat withdraw, grade protection | Idempotent cancel; withdraw seats; no grade mutation — **PROPOSAL ONLY** |
| 7.2-DD-005 | Confirmed → Present | **HIGH** | HD-006 deferred; actor/session rules unknown | Admin via update; require InProgress — **PROPOSAL ONLY** |
| 7.2-DD-012 | Correct after cancel | **HIGH** | Guard gap vs enter | Fail closed Correct on Cancelled — **PROPOSAL ONLY** |
| 7.2-DD-013 | Grade enter timing | **HIGH** | Current allows non-InProgress | Human choose A/B/C/D — **PROPOSAL ONLY** |
| 7.2-DD-006 | Multi-session per subject | **HIGH** | No product uniqueness rule | Allow multiples; no unique — **PROPOSAL ONLY** |
| 7.2-DD-010 | Move/reassign | **HIGH** | Not in HD-004; identity risk | **FORBIDDEN** — **PROPOSAL ONLY** |
| 7.2-DD-003 | Catalog vocabulary registration | **HIGH** | Names not live; HD-005 locks cancel name only | Register seven names after DD-004 — **PROPOSAL ONLY** |
| 7.2-DD-025 | Room school isolation | **MEDIUM** | Room FK school composite unclear | Fail-closed same-school room — **PROPOSAL ONLY** |
| 7.2-DD-022 | Seat number uniqueness | **MEDIUM** | Nullable free text today | Optional non-unique — **PROPOSAL ONLY** |
| 7.2-DD-007 | Room overlap | **MEDIUM** | No exclusion constraint | Defer enforcement — **PROPOSAL ONLY** |
| 7.2-DD-026 | Timezone | **MEDIUM** | No TZ columns | School-local civil — **PROPOSAL ONLY** |
| 7.2-DD-017 | Direct event cause | **MEDIUM** | Cascade cause exists; direct absent | Shared events + cause — **PROPOSAL ONLY** |
| 7.2-DD-021 | Bulk assign | **LOW** | Master Lock defer | Keep deferred |
| 7.2-DD-024 | Capacity | **LOW** | No capacity column | Keep deferred |
| 7.2-DD-028 | Scheduled→Completed skip | **LOW** | Master Lock defer | Keep forbidden/deferred |
| 7.2-DD-018 | updated_at | **LOW** | Convention only | Do not add |

**Counts:** Human Decision Required (substantive YES) ≈ **16** (including ownership, Present, multi-session, reassignment, cancel semantics, grade correct/enter, room, seat number, etc.)  
**Conflicts:** **0**

---

## 18. Design-Lock Entry Criteria

Before Phase 7.2 Design Lock may be declared, resolve at minimum:

| Criterion | Related DDs | Required outcome |
|-----------|-------------|------------------|
| Security ownership | DD-004 | Explicit role→permission map approved |
| Permission vocabulary | DD-003, HD-005 | Seven names + no `exam.session.cancel` affirmed for catalog |
| Session lifecycle transitions | DD-001, DD-028 | Open/Close/Cancel matrix locked; skip Completed deferred/forbidden |
| Enrollment lifecycle transitions | DD-002, DD-005, DD-011 | Present rules resolved or explicitly out-of-7.2-update-scope; Restore forbidden |
| Present semantics | DD-005 | Actor, permission, session precondition |
| Multi-session rule | DD-006 | Allow or forbid duplicates per subject |
| Reassignment policy | DD-010 | Forbidden or approved Move contract |
| Grade/session interaction | DD-012, DD-013, DD-027 | Correct/enter/cancel-with-grade rules |
| Cancellation semantics | DD-009 | Idempotency, seats, grades, coexistence with CancelExam |
| Academic-year validation | DD-008 | Affirm fail-closed handler rule |
| Idempotency | DD-016 | Affirm same-COMMIT pattern |
| Event causation | DD-017 | Cause discriminator locked |
| RLS verification requirements | DD-019 | Test matrix required for closure |
| HTTP / Query | DD-015, DD-020 | Remain deferred unless separate grant |
| Room validation | DD-025 | Same-school rule when room_id set |

```text
Design Lock ≠ Implementation Authorization
```

---

## 19. Explicit Non-Scope

```text
Phase 7.1 = CLOSED

Phase 7.2 implementation = NOT AUTHORIZED
HTTP exposure = NOT AUTHORIZED
Query AuthZ = NOT AUTHORIZED
CompleteExam implementation = NOT AUTHORIZED
New permissions = NOT AUTHORIZED
RLS changes = NOT AUTHORIZED
Migrations = NOT AUTHORIZED
Database schema changes = NOT AUTHORIZED
Application code changes = NOT AUTHORIZED

No application files modified
No database files modified
No migrations created
No permissions created
No RLS changes
No HTTP routes
```

---

## 20. Final Decision-Register Status

```text
DESIGN DECISIONS DOCUMENTED
HUMAN DECISIONS PENDING
IMPLEMENTATION NOT AUTHORIZED
Design Lock = NOT YET AUTHORIZED
```

| Metric | Count |
|--------|-------|
| Decisions documented (DD-001…028) | **28** |
| Human decisions required (substantive) | **16** |
| Critical human decisions | **3** (DD-004, DD-009, DD-027) |
| High human decisions | **6** (DD-003, DD-005, DD-006, DD-010, DD-012, DD-013) |
| Conflicts | **0** |
| Already LOCKED / APPROVED baselines reused | HD-004, HD-005, DR-001…004, DR-006, DD-001/011/014/015/016/020 cores |

```text
NEXT AUTHORIZED ARTIFACT (recommended):
.cursor/database/phase-7.2/03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md
  (or equivalent human resolution of CRITICAL/HIGH rows in §17)
THEN:
.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md
  (only after human resolutions)
```

```text
IMPLEMENTATION AUTHORIZATION:
NOT GRANTED
```

```text
STOP.
Do not implement Phase 7.2.
Do not create permissions.
Do not expose HTTP.
Do not modify RLS or migrations.
Do not claim Design Locked.
```
