# MASTER PHASE 7 — PHASE 7.2 — HUMAN DECISION RESOLUTION

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle — Human Decision Resolution |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Document Type** | HUMAN DECISION RESOLUTION / DESIGN GOVERNANCE |
| **Date** | 2026-09-11 |
| **Mode** | HUMAN DECISION RESOLUTION ONLY |
| **Predecessor** | `.cursor/database/phase-7.2/02-PHASE-7.2-DESIGN-DECISION-REGISTER.md` |
| **Implementation** | **NONE** |
| **Authorization** | **NO DESIGN LOCK · NO IMPLEMENTATION AUTHORIZATION** |

```text
THIS DOCUMENT = decision-resolution record only
≠ Design Lock
≠ Implementation Authorization
≠ permission / role / RLS / migration / application change
≠ auto-approval of proposals
```

**Rule:** A recommendation is not a decision. A proposal is not an approval. Only repository-evidenced human approval may be recorded as APPROVED/LOCKED for previously unresolved business/security choices.

---

## 2. Authorization State

```text
Phase 7.1 = PASS — CLOSED
Phase 7.2 Readiness Audit = COMPLETE
Phase 7.2 Design Decision Register = COMPLETE
Phase 7.2 Human Decision Resolution = CURRENT ARTIFACT
Phase 7.2 Design Lock = NOT AUTHORIZED
Phase 7.2 Implementation = NOT AUTHORIZED
HTTP = NOT AUTHORIZED
Query AuthZ = DEFERRED
Permission catalog mutation = NOT AUTHORIZED
RLS mutation = NOT AUTHORIZED
Migration = NOT AUTHORIZED
Application changes = NOT AUTHORIZED
```

These states are **unchanged** by this document.

---

## 3. Input Artifacts

| Artifact | Use |
|----------|-----|
| `.cursor/database/phase-7.2/01-PHASE-7.2-READINESS-DISCOVERY-DESIGN-AUDIT.md` | Discovery baseline |
| `.cursor/database/phase-7.2/02-PHASE-7.2-DESIGN-DECISION-REGISTER.md` | DD-001…028 unresolved set |
| `.cursor/database/phase-7.1/18-PHASE-7.1-EXAM-ADMINISTRATION-FINAL-CLOSURE-GATE.md` | Phase 7.1 CLOSED |
| `.cursor/database/phase-7.1/14-PHASE-7.1-EXAM-ADMINISTRATION-HD-001-SECURITY-DECISION-AMENDMENT.md` | exam.create/update/cancel → grades_manager |
| `.cursor/database/phase-7.1/10A-PHASE-7.1-EXAM-ADMINISTRATION-HUMAN-DECISION-RESOLUTION.md` | HD-004 / HD-005 / HD-006 |
| `.cursor/database/phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md` | Command/lifecycle/DR vocabulary |
| `config/security.php` | Live permission/role catalog |
| `database/migrations/2026_09_10_150000_phase3a_create_exams_tables.php` | exam_sessions / exam_enrollments |
| `database/migrations/2026_09_05_100100_create_organization_tables.php` | rooms / branches |
| `app/Domain/Exams/Services/StudentGradeWriteGuard.php` | Enter vs Correct guards |
| `app/Application/Exams/Commands/CancelExamHandler.php` | Cascade + outbox |
| `app/Domain/Exams/Events/ExamSessionCancelled.php` / `ExamEnrollmentCancelled.php` | `cause: exam_cancel` |

---

## 4. Human Decision Method

| Classification | Meaning |
|----------------|---------|
| **ALREADY LOCKED** | Fixed by prior authoritative design/schema/DR |
| **ALREADY APPROVED** | Explicit prior human approval on record |
| **HUMAN DECISION RESOLVED** | New human approval evidenced in this phase — **none in this artifact** |
| **ARCHITECTURE RESOLUTION** | Technical pattern resolved under existing architecture authority (not a business/security ownership choice) |
| **DEFERRED** | Explicitly postponed |
| **OUT OF SCOPE** | Excluded from Phase 7.2 |
| **PROPOSAL STILL UNRESOLVED** | Recommendation exists; no human approval |
| **BLOCKER** | Blocks Design Lock and/or implementation until resolved |
| **CONFIRMED — NO NEW HUMAN DECISION REQUIRED** | Prior lock/approval reaffirmed; no reopen |

```text
DD  = Design Decision ID (from register)
HD  = Human Decision ID (this resolution record)
```

**Non-negotiable:** Cursor does **not** convert Recommended Option → Final Decision for business/security choices.

---

## 5. Critical Decisions

---

## HD-7.2-001 — Permission Ownership (DD-004)

### Question

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

### Authoritative Evidence

- Live `config/security.php`: `grades_manager` owns `exam.create|update|cancel` **only**; **no** `exam.session.*` / `exam.enrollment.*`
- Phase 7.1 HD-001 amendment: approved **only** those three exam admin permissions → `grades_manager`
- 10A: explicitly no candidate role approved for session/enrollment permissions
- Roles present: `grades_manager`, `grades_teacher`, `grades_viewer`, `enrollment_*`, `attendance_*` — none catalogue session/enrollment exam perms

### Existing Locked Constraints

```text
HD-001 DOES NOT automatically authorize Phase 7.2 permissions.
exam.session.cancel MUST NOT be introduced (HD-005).
Do not invent Examiner / temporary roles as a workaround.
```

### Options

#### Option A

All seven Phase 7.2 permissions → `grades_manager`

#### Option B

Session administration → `grades_manager`; Present / participation marking assigned separately (ties to HD-7.2-005)

#### Option C

Dedicated exam session / enrollment role(s)

#### Option D

Another explicitly documented organization model (must be written by owner)

#### Option E

Remain unresolved until business/security owner supplies organization policy

### Recommended Option

**Option A** as default *candidate* for discussion symmetry with HD-001; **Option B** if Present is invigilator-owned (HD-7.2-005).

### Why

Centralizes exam administration under one role already owning exam create/update/cancel; reduces AuthZ fragmentation. Does **not** justify silent inheritance.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| A | Over-privilege if teachers should not cancel sessions |
| B | Split-brain AuthZ; Present permission must be named carefully |
| C | New role governance + seed/ops cost |
| D | Ambiguity until documented |
| E | Blocks Design Lock indefinitely (acceptable if intentional) |

### Human Decision Required

**YES**

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER**

### Decision Owner

**SECURITY** / **HUMAN PROJECT OWNER**

### Blocks Design Lock

**YES**

### Blocks Implementation

**YES**

### Dependencies

DD-003 / HD-7.2-004; DD-005 / HD-7.2-005

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## HD-7.2-002 — CancelExamSession / CancelExamEnrollment Cancellation Semantics (DD-009)

### Question

How do dedicated `CancelExamSession` / `CancelExamEnrollment` coexist with Phase 7.1 `CancelExam` cascade for:

- already Cancelled sessions;
- active seats on session cancel;
- CURRENT grades (see HD-7.2-003 / DD-027);
- CancelExam after partial session cancels;
- event causation;
- grade non-mutation.

### Authoritative Evidence

- CancelExam (CLOSED): fail-closed on CURRENT grade / Completed session / Completed|Cancelled exam; cascades Scheduled|InProgress → Cancelled; active seats → Withdrawn; **no grade mutation**
- Cascade events: `cause: exam_cancel`
- CancelExamSession / CancelExamEnrollment application writers: **ABSENT**
- DR-002: CancelExamEnrollment fail-closed if CURRENT grade; no grade mutation
- Master Lock CancelExamSession: Scheduled|InProgress → Cancelled; enter blocked on Cancelled; **silent** on CURRENT-grade guard and seat cascade

### Existing Locked Constraints

```text
CancelExam cascade = authoritative — do not redesign
Never auto-void / delete / mutate grades as cancellation side effect
DR-001 / DR-002 grade non-mutation
HD-005: CancelExamSession uses exam.session.update
```

### Substructure (consolidation with DD-027)

```text
DD-009
  ├── A — Already Cancelled behavior
  ├── B — Active seat behavior on CancelExamSession
  ├── C — CURRENT grade behavior
  │      └── DD-027 = HD-7.2-003 (explicit critical sub-decision)
  └── D — CancelExam coexistence after partial cancels
```

### Options

#### A — Already Cancelled session

- **A1** Idempotent success / no-op (replay-safe)
- **A2** Fail closed “already cancelled”

#### B — Active seats on CancelExamSession

- **B1** Cascade active seats → Withdrawn (atomic with session cancel)
- **B2** Fail closed if any active seat remains
- **B3** Leave seats active (inconsistent with Cancelled session + grade-enter rules)

#### C — CURRENT grades

See **HD-7.2-003** (DD-027). Do not decide here separately.

#### D — CancelExam after individual session cancels

- **D1** Cancel remaining open sessions only; skip already Cancelled (idempotent cascade) — matches current cascade intent
- **D2** Fail CancelExam if any session already Cancelled — contradicts cascade usefulness

### Recommended Option

**A1 + B1 + D1**, with **C = HD-7.2-003 recommendation FAIL CLOSED**; never mutate grades; distinguish event `cause` (see Architecture Resolution for DD-017).

### Why

Preserves CancelExam authority, avoids orphan active seats on cancelled sessions, keeps audit causality clear.

### Risks of Alternative Options

| Choice | Risk |
|--------|------|
| A2 | Breaks benign retries / dual-path cancel |
| B2 | Operational friction (force withdraw first) |
| B3 | Active seats under Cancelled session — integrity hazard |
| D2 | Blocks parent cancel after legitimate session cancels |

### Human Decision Required

**YES** (A/B/D; C via HD-7.2-003)

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER**

### Decision Owner

**BUSINESS** / **ARCHITECTURE** / **HUMAN PROJECT OWNER**

### Blocks Design Lock

**YES**

### Blocks Implementation

**YES**

### Dependencies

HD-7.2-003; DD-017; DR-001; DR-002

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## HD-7.2-003 — CancelExamSession CURRENT-Grade Guard (DD-027)

### Question

Is `CancelExamSession` allowed when **ANY** CURRENT grade (`student_grades.is_current = true`) exists for seats of that session?

### Authoritative Evidence

- CancelExam: **blocks** on CURRENT grade for the exam
- CancelExamEnrollment (DR-002): **blocks** on CURRENT grade for that seat
- CancelExamSession (Master Lock): **no** explicit CURRENT-grade rule
- Enter guard rejects Cancelled session; Correct guard does **not** re-check Cancelled (HD-7.2-008)

### Existing Locked Constraints

```text
Never auto-void grades
Never delete grades
Never mutate grades as cancellation side effect
```

### Options

#### Option A

**FAIL CLOSED** if any CURRENT grade exists on the session

#### Option B

Allow CancelExamSession despite CURRENT grades; rely on enter-block only

#### Option C

Allow cancel and require separate grade CQRS first — operational policy only (still no auto-void)

### Recommended Option

**Option A — FAIL CLOSED**

### Why

Aligns CancelExamSession with CancelExam / DR-002 spirit; closes integrity window if Correct does not yet re-check Cancelled parents.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| B | CURRENT grades under Cancelled session; worsens DD-012 gap |
| C | Same as A operationally if “require void first,” but weaker if not enforced |

### Human Decision Required

**YES**

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER**  
*(Critical sub-decision of DD-009 / HD-7.2-002 — not a contradictory second final answer)*

### Decision Owner

**BUSINESS** / **SECURITY** / **ARCHITECTURE**

### Blocks Design Lock

**YES**

### Blocks Implementation

**YES**

### Dependencies

HD-7.2-002; HD-7.2-008 (DD-012)

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## 6. High Decisions

---

## HD-7.2-004 — Permission Catalog Registration (DD-003)

### Question

May the seven Master Lock / HD-005 permission names be registered into the live catalog, and when?

### Authoritative Evidence

- Vocabulary DEFINED in Master Lock / 10A
- HD-005 **APPROVED**: CancelExamSession → `exam.session.update`; **forbid** `exam.session.cancel`
- Live catalog: seven names **ABSENT**

### Existing Locked Constraints

```text
exam.session.cancel MUST NOT be introduced (HD-005) — DO NOT REOPEN
```

### Options

#### Option A

Register exactly the seven names after ownership (HD-7.2-001) is approved

#### Option B

Collapse open/close into `exam.session.update` (narrower catalog)

#### Option C

Defer all catalog registration until a later security gate

### Recommended Option

**Option A** (sequenced after HD-7.2-001)

### Why

Matches Master Lock vocabulary; preserves HD-005; avoids premature catalog noise without owners.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| B | Weaker least-privilege for open/close |
| C | Blocks implementation AuthZ wiring |

### Human Decision Required

**YES** (registration timing/content; cancel naming already locked)

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER** (for catalog mutation authorization; naming partially locked)

### Decision Owner

**SECURITY** / **HUMAN PROJECT OWNER**

### Blocks Design Lock

**YES** (vocabulary adoption for Design Lock must be affirmed)

### Blocks Implementation

**YES**

### Dependencies

HD-7.2-001; HD-005

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE** (HD-005 approval covers cancel naming only)

---

## HD-7.2-005 — Confirmed → Present (DD-005 / HD-006)

### Question

Independently resolve:

1. Who performs `Confirmed → Present`?
2. What permission is required?
3. Must session be `InProgress`?
4. Is `Scheduled → Present` allowed?
5. Is `Present → Absent` allowed?
6. Is Present an exam participation fact only?
7. Must Present affect grade eligibility?
8. Must Present interact with attendance?

### Authoritative Evidence

- HD-006: **APPROVED AS DEFERRED** to Phase 7.2 Design Lock — actor/permission/session rules **not** decided
- Master Lock §10.3: Confirmed→Present actor/session-open **TBD**
- `isActiveSeat` includes Present; Enter does **not** require Present
- Locked distinction: seat Present ≠ `student_grades.is_absent`
- Registered|Confirmed|Present → Absent allowed in Master Lock design matrix

### Existing Locked Constraints

```text
Present ≠ student_grades.is_absent
Do not merge exam seat participation with attendance.mark SSOT
Do not invent Present semantics in Phase 7.1 (closed)
```

### Options

#### Option A

Exam admin only via `exam.enrollment.update`; require session `InProgress`; forbid Present while Scheduled; Present→Absent allowed; Present does not gate grade eligibility; no attendance write

#### Option B

Separate invigilator/teacher permission for Present; require InProgress; otherwise like A

#### Option C

Allow Present while Scheduled

#### Option D

Auto-Present on grade enter — conflates domains

#### Option E

Defer Present transition out of Phase 7.2 Update matrix (enum unused until later)

### Recommended Option

**Option A or B** with InProgress required; **not** D; keep Present ≠ `is_absent`; no attendance mutation.

### Why

Keeps participation explicit; avoids grade/attendance coupling; aligns with open-session operations.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| C | Present before session opens — weak operational meaning |
| D | Hidden side effects; breaks CQRS boundaries |
| E | Leaves HD-006 unresolved into later phase (acceptable if explicit) |

### Human Decision Required

**YES**

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER** (unless Design Lock explicitly scopes Present out)

### Decision Owner

**BUSINESS** / **SECURITY** / **HUMAN PROJECT OWNER**

### Blocks Design Lock

**YES** (must resolve or explicitly defer Present within Design Lock text)

### Blocks Implementation

**YES** for any Update path including Present

### Dependencies

HD-7.2-001; HD-7.2-009 (grade timing)

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE** (HD-006 only deferred the decision)

---

## HD-7.2-006 — Multiple Sessions Per Subject (DD-006)

### Question

May one Exam have multiple `exam_sessions` rows with the same `subject_id` (make-ups, parallel rooms, cohorts, resits, accommodations)?

### Authoritative Evidence

- DB: **no** UNIQUE `(exam_id, subject_id)`
- Index `(subject_id, session_date)` only
- Seat uniqueness: `(exam_session_id, enrollment_id)` — same enrollment may sit in multiple sessions of same exam if product allows
- Product rule: **UNKNOWN**

### Existing Locked Constraints

```text
Do not add unique constraint in this task
Do not implement schema change here
```

### Options

#### Option A

**ALLOW MULTIPLE** — status quo schema; no unique

#### Option B

**ONE SESSION ONLY** per exam+subject — record future unique as separate schema decision; do not implement now

#### Option C

Allow multiple only when typed (make-up/resit) — needs future schema

### Recommended Option

**Option A — ALLOW MULTIPLE**

### Why

Supports make-ups, parallel rooms, resits, accommodations without blocking Design Lock on a missing product unique; enrollment uniqueness remains per session.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| B | Breaks make-up/resit unless modeled elsewhere; future unique vs historical data |
| C | Schema/product complexity before need |

### Human Decision Required

**YES**

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER** for CreateExamSession uniqueness rules

### Decision Owner

**BUSINESS** / **HUMAN PROJECT OWNER**

### Blocks Design Lock

**YES**

### Blocks Implementation

**YES** (create validation rule)

### Dependencies

HD-7.2-007 (Move); grade identity remains seat-scoped

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## HD-7.2-007 — Move / Reassign Enrollment (DD-010)

### Question

May an existing ExamEnrollment move Session A → Session B?

### Authoritative Evidence

- HD-004 command set: **no** Move command
- UpdateExamEnrollment: identity columns immutable in Master Lock (`exam_session_id` not in allowlist)
- Grade composites bind to enrollment seat + session
- Unique `(exam_session_id, enrollment_id)`

### Existing Locked Constraints

```text
Do not reinterpret UpdateExamEnrollment as permission to change exam_session_id
```

### Options

#### Option A

**FORBIDDEN** in Phase 7.2 — operational path: CancelExamEnrollment (if permitted) + CreateExamEnrollment on target

#### Option B

Dedicated Move/Reassign command with grade/session guards

#### Option C

Allow Update to change `exam_session_id` — rejected by immutability design

### Recommended Option

**Option A — FORBIDDEN**

### Why

Preserves identity/audit/grade FK history; avoids silent history corruption.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| B | Complex grade/Present/Withdrawn matrix; needs full DCR |
| C | Breaks DR-003 immutability and grade composites |

### Human Decision Required

**YES** (affirm FORBIDDEN or approve Move DCR)

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER** if left ambiguous

### Decision Owner

**BUSINESS** / **ARCHITECTURE**

### Blocks Design Lock

**YES** (must state FORBIDDEN or approved Move contract)

### Blocks Implementation

**YES** if ambiguous

### Dependencies

HD-7.2-006; HD-7.2-002; DD-011 Restore FORBIDDEN

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## HD-7.2-008 — Grade Correction After Session/Exam Cancellation (DD-012)

### Question

Must `CorrectStudentGrade` fail closed when session **or** exam is Cancelled?

### Authoritative Evidence

- `StudentGradeWriteGuard::assertCanEnter` rejects Cancelled session/exam
- `assertCanCorrect` does **not** re-check session/exam cancellation
- CancelExam blocked when CURRENT grade exists — reduces but does not eliminate paths if CancelExamSession allows CURRENT grades (HD-7.2-003 Option B)

### Existing Locked Constraints

```text
Do not modify Grade handlers / StudentGradeWriteGuard in this task
Do not silently change live Grade behavior here
```

### Options

#### Option A

Correct **FAIL CLOSED** if session or exam Cancelled

#### Option B

Allow Correct on Cancelled parents (history repair)

#### Option C

Allow only under elevated security role

### Recommended Option

**Option A — FAIL CLOSED**

### Why

Aligns Correct with Enter; closes integrity gap especially if session cancel with CURRENT grades were ever allowed.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| B | Mutating CURRENT grades under cancelled administration |
| C | Policy complexity; still needs explicit AuthZ |

### Human Decision Required

**YES**

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER** for end-to-end integrity Design Lock (implementation of guard = separate later AuthZ)

### Decision Owner

**BUSINESS** / **SECURITY** / **ARCHITECTURE**

### Blocks Design Lock

**YES**

### Blocks Implementation

**NO** for session writers alone if Design Lock records dependency on separate grade-guard change; **YES** for claiming full exam-admin integrity closed

### Dependencies

HD-7.2-003; Grade CQRS (out of 7.2 write scope)

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## HD-7.2-009 — Grade Entry Session Timing (DD-013)

### Question

Which session statuses allow Grade Enter?

```text
Scheduled / InProgress / Completed / Cancelled
```

### Authoritative Evidence

- Current: Cancelled = **denied**; InProgress **not** required; Completed/Scheduled not blocked by guard today (**DEFINED** by omission)
- Must not change Grade behavior in this task

### Existing Locked Constraints

```text
Cancelled remain denied unless future DCR
Do not implement guard changes here
```

### Options

#### Option A

Enter only when `InProgress`

#### Option B

Enter when `Scheduled` or `InProgress`

#### Option C

Enter when `InProgress` or `Completed` (post-close scoring window)

#### Option D

Keep current: anything except Cancelled (+ active seat + other enter rules)

### Recommended Option

**Option C or A** as candidates — **not auto-chosen**; human must pick allowed set.

### Why

Business policy for live vs post-close grading; couples to CloseExamSession timing and Present (HD-7.2-005).

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| A | Blocks late entry after Close |
| B | Grades before session opens |
| D | Weakest operational meaning of Open/Close |

### Human Decision Required

**YES**

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER** for Design Lock integrity narrative

### Decision Owner

**BUSINESS** / **HUMAN PROJECT OWNER**

### Blocks Design Lock

**YES**

### Blocks Implementation

**NO** for Open/Close commands themselves; **YES** for declaring final grade-entry policy

### Dependencies

HD-7.2-005; DD-014 CompleteExam out of scope

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## 7. Medium / Low Decisions

---

## HD-7.2-010 — Room / Time Overlap (DD-007)

### Question

Must the system enforce same-room overlapping `session_date` + time ranges? Teacher/invigilator conflicts?

### Authoritative Evidence

- CHECK: `end_time > start_time` only
- No exclusion constraint on room/time
- No invigilator assignment table on exam_sessions

### Options

#### Option A

No overlap enforcement in Phase 7.2

#### Option B

Fail-closed same room + overlapping times

#### Option C

Soft warning only (UI — HTTP out of scope)

### Recommended Option

**Option A** (defer hard scheduling)

### Human Decision Required

**YES** if product requires hard scheduling; else affirm deferral

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** (deferral affirmation pending)

### Decision Owner

**BUSINESS** / **DATABASE**

### Blocks Design Lock

**NO** if Design Lock explicitly defers; **YES** if product demands hard rule without decision

### Blocks Implementation

**NO** if deferred

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

### Teacher/invigilator conflicts

**OUT OF SCOPE** until assignment model exists — **CONFIRMED**

---

## HD-7.2-011 — Event Causation (DD-017)

### Question

How do direct Phase 7.2 cancel commands distinguish causation from CancelExam cascade?

### Authoritative Evidence

- Cascade payloads: `'cause' => 'exam_cancel'` (**IMPLEMENTED**)
- DR-006: event identity ≠ idempotency identity (**LOCKED**)
- Direct command events: **ABSENT**
- No repository human approval required beyond adopting the established pattern

### Existing Locked Constraints

```text
Do not redesign outbox/idempotency infrastructure
Do not create event classes in this task
```

### Classification

**ARCHITECTURE RESOLUTION** (technical pattern with existing authority — not a business ownership choice)

### Architecture Decision (under existing authority)

```text
Reuse same event classes where applicable
+ mandatory cause discriminator
+ command_name in outbox envelope (existing pattern)
Conceptual causes:
  exam_cancel
  exam_session_cancel
  exam_enrollment_cancel
Do not invent parallel event type trees without Design Change
```

### Why this is not left as “Human Required = NO + Final = UNRESOLVED”

Existing cascade already established `cause` as the discriminator. Architecture authority (DR-006 + implemented cascade) is sufficient to lock the **pattern** for Design Lock entry on event causation. Creating **new PHP event classes** remains implementation-time and **NOT AUTHORIZED** now.

### Human Decision Required

**NO** (for pattern); **YES** only if product demands separate event type trees (Option B rejected unless DCR)

### Decision Status

**ARCHITECTURE RESOLUTION**

### Final Decision (architecture)

**RESOLVED — shared event classes + mandatory `cause` + outbox `command_name`; cascade keeps `exam_cancel`; direct commands use distinct cause values at implementation time**

### Approval Evidence

DR-006; live cascade `cause: exam_cancel`; Master Lock event identity rules

### Blocks Design Lock

**NO** (pattern resolved)

### Blocks Implementation

**NO** (pattern known; coding still unauthorized)

---

## HD-7.2-012 — Seat Number Uniqueness (DD-022)

### Question

Is `seat_number` unique per session? Auto-generated?

### Authoritative Evidence

Nullable `string(10)`; **no** UNIQUE; immutable when Present or CURRENT grade (Master Lock)

### Options

#### Option A

Optional free-text; no uniqueness

#### Option B

Unique per session when not null

#### Option C

System-generated sequential seats

### Recommended Option

**Option A**

### Human Decision Required

**YES** if uniqueness/generation required; affirm A otherwise

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED**

### Decision Owner

**BUSINESS**

### Blocks Design Lock

**NO** if optional free-text affirmed in Design Lock; **YES** if uniqueness demanded without decision

### Blocks Implementation

**NO** if A affirmed

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## HD-7.2-013 — Room School Isolation (DD-025)

### Question

If `room_id != null`, must the room belong to the same school as the exam session?

### Authoritative Evidence (inspected — not guessed)

| Fact | Evidence |
|------|----------|
| `organization.rooms` columns | `id`, `branch_id`, `code`, `name`, `capacity`, `room_type`, `status`, timestamps — **no `school_id` column** |
| School path | `rooms.branch_id` → `organization.branches.school_id` |
| `exams.exam_sessions.room_id` | Nullable FK → `organization.rooms(id)` **only** |
| Composite `(room_id, school_id)` FK | **ABSENT** |
| Cross-school room id reference at DB | **Possible** unless application validates room→branch→school |

### Existing Locked Constraints

```text
Session school_id composite FK to parent exam remains LOCKED
Do not add composite room FK in this task
```

### Options

#### Option A

Fail-closed same-school validation in handler when `room_id` set (via room→branch→school)

#### Option B

Rely solely on rooms/branches RLS + id FK (weaker for writer correctness)

#### Option C

Disallow `room_id` until composite school FK exists

### Recommended Option

**Option A**

### Why

DB does **not** enforce school alignment today; writers must fail closed to preserve tenant integrity.

### Risks of Alternative Options

| Option | Risk |
|--------|------|
| B | Cross-school room assignment possible |
| C | Blocks Master Lock Update allowlist field |

### Human Decision Required

**YES**

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** · **BLOCKER** for UpdateExamSession `room_id` path

### Decision Owner

**SECURITY** / **ARCHITECTURE** / **DATABASE**

### Blocks Design Lock

**YES**

### Blocks Implementation

**YES** for room assignment

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

## HD-7.2-014 — Timezone / Session Date Semantics (DD-026)

### Question

What timezone semantics apply to `session_date` / `start_time` / `end_time`?

### Authoritative Evidence

Stored as date/time without session timestamptz; school TZ policy for exams **UNKNOWN**

### Options

#### Option A

School-local civil date/time; no conversion in Phase 7.2; no academic-calendar gate

#### Option B

Require academic-calendar validation

#### Option C

Store/interpret as UTC (would need schema/product change)

### Recommended Option

**Option A**

### Human Decision Required

**YES** for calendar gating (B); affirm A otherwise

### Decision Status

**UNRESOLVED — HUMAN APPROVAL REQUIRED** (affirmation pending)

### Decision Owner

**BUSINESS** / **ARCHITECTURE**

### Blocks Design Lock

**NO** if A explicitly affirmed; **YES** if calendar gate demanded without decision

### Blocks Implementation

**NO** if A affirmed

### Human Decision

**UNRESOLVED**

### Approval Evidence

**NONE**

---

### Low / deferred (no new HD approval invented)

| DD | Topic | Status |
|----|-------|--------|
| DD-018 | `updated_at` | **PROPOSAL STILL UNRESOLVED** — recommend do not add; **not** a Design Lock blocker if Design Lock states “no schema change” |
| DD-021 | Bulk assignment | **DEFERRED** — CONFIRMED |
| DD-024 | Capacity | **DEFERRED** — CONFIRMED (rooms.capacity exists but unused by exam_sessions) |
| DD-028 | Scheduled → Completed | **DEFERRED / FORBIDDEN** unless DCR — CONFIRMED |
| DD-019 | RLS WITH CHECK / verification | **ARCHITECTURE RESOLUTION** — no RLS mutation; require writer-path PG tests before Phase 7.2 closure (mirror artifact 17) |

---

## 8. Locked Decisions Confirmed

| DD | Topic | Status |
|----|-------|--------|
| DD-001 | Five session commands | **CONFIRMED — NO NEW HUMAN DECISION REQUIRED** (HD-004 APPROVED) |
| DD-002 | Three enrollment commands | **CONFIRMED** (HD-004); Move/Bulk still via HD-7.2-007 / DD-021 |
| DD-008 | Academic-year fail-closed on CreateExamEnrollment | **CONFIRMED** (Master Lock); no denorm year column |
| DD-011 | Restore Forbidden | **CONFIRMED** |
| DD-014 | No CompleteExam; CloseExamSession feeds DR-004 only | **CONFIRMED** |
| DD-015 | HTTP deferred | **CONFIRMED** |
| DD-016 | same-COMMIT idempotency/outbox/fingerprint; event ≠ idempotency id | **CONFIRMED** |
| DD-020 | Query AuthZ deferred | **CONFIRMED** |
| HD-005 | CancelExamSession → `exam.session.update` | **ALREADY APPROVED** — not reopened |
| HD-001 (7.1) | exam.create/update/cancel → grades_manager | **ALREADY APPROVED** — does **not** grant 7.2 perms |

---

## 9. Deferred Decisions Confirmed

| DD | Topic | Status |
|----|-------|--------|
| DD-015 | HTTP | **DEFERRED** |
| DD-020 | Query AuthZ | **DEFERRED** |
| DD-021 | Bulk | **DEFERRED** |
| DD-024 | Capacity | **DEFERRED** |
| DD-028 | Scheduled→Completed skip | **DEFERRED** |
| HD-006 / DD-005 | Present (until HD-7.2-005 resolves) | **still UNRESOLVED** (was deferred *to* this resolution; not auto-resolved) |

---

## 10. DD-009 / DD-027 Consolidation

```text
NOT A CONTRADICTION

DD-009 = parent cancellation semantics package
DD-027 = explicit CURRENT-grade sub-decision (HD-7.2-003)

Single integrity rule pending human approval:
  CancelExamSession + ANY CURRENT grade on session → ?

Architectural recommendation: FAIL CLOSED
Human status: UNRESOLVED — HUMAN APPROVAL REQUIRED

Grade mutation on cancel: FORBIDDEN (already locked)
```

No duplicate conflicting Final Decisions are recorded.

---

## 11. Security Decision Matrix

| Operation | Candidate Permission | Proposed Role | Status | Human Approval |
|-----------|---------------------|---------------|--------|----------------|
| CreateExamSession | `exam.session.create` | UNRESOLVED | Catalog ABSENT | **YES** |
| UpdateExamSession | `exam.session.update` | UNRESOLVED | Catalog ABSENT | **YES** |
| OpenExamSession | `exam.session.open` | UNRESOLVED | Catalog ABSENT | **YES** |
| CloseExamSession | `exam.session.close` | UNRESOLVED | Catalog ABSENT | **YES** |
| CancelExamSession | `exam.session.update` | UNRESOLVED | HD-005 name LOCKED | **YES** (ownership) |
| CreateExamEnrollment | `exam.enrollment.create` | UNRESOLVED | Catalog ABSENT | **YES** |
| UpdateExamEnrollment | `exam.enrollment.update` | UNRESOLVED | Catalog ABSENT | **YES** |
| CancelExamEnrollment | `exam.enrollment.cancel` | UNRESOLVED | Catalog ABSENT | **YES** |

```text
HD-001 DOES NOT automatically authorize Phase 7.2 permissions.
```

---

## 12. Lifecycle Decision Matrix

| Area | Transition / Rule | Status |
|------|-------------------|--------|
| Session Scheduled→InProgress | OpenExamSession | **ALREADY LOCKED** (design) |
| Session InProgress→Completed | CloseExamSession | **ALREADY LOCKED** (design) |
| Session →Cancelled | CancelExamSession / CancelExam | **ALREADY LOCKED**; seat/grade guards **UNRESOLVED** (HD-7.2-002/003) |
| Session reopen | Forbidden | **ALREADY LOCKED** |
| Enrollment create → Registered | CreateExamEnrollment | **ALREADY LOCKED** |
| Registered→Confirmed | Update | **ALREADY LOCKED** (design) |
| Confirmed→Present | Update | **UNRESOLVED** (HD-7.2-005) |
| →Absent | Update | **ALREADY LOCKED** (design) pending Present policy interaction |
| →Withdrawn | CancelEnrollment / cascade | **ALREADY LOCKED** + DR-002 |
| Restore | Forbidden | **ALREADY LOCKED** |
| Move session | — | **UNRESOLVED** (HD-7.2-007; recommend FORBIDDEN) |
| Multi-session per subject | — | **UNRESOLVED** (HD-7.2-006) |

---

## 13. Grade Integrity Decision Matrix

| Topic | Status |
|-------|--------|
| Enter on Cancelled | **DEFINED** — denied |
| Enter requires InProgress | **UNRESOLVED** (HD-7.2-009) |
| Correct on Cancelled parent | **UNRESOLVED** (HD-7.2-008) |
| CancelExamEnrollment vs CURRENT grade | **ALREADY LOCKED** (DR-002) |
| CancelExamSession vs CURRENT grade | **UNRESOLVED** (HD-7.2-003) |
| Present ≠ `is_absent` | **ALREADY LOCKED** |
| CompleteExam | **OUT OF SCOPE** |
| Grade mutation on cancel | **FORBIDDEN** |

---

## 14. RLS / Tenant Isolation Decision Matrix

| Topic | Status |
|-------|--------|
| FORCE RLS sessions/enrollments | **DEFINED** / proven (Phase 7.1 artifact 17) |
| Explicit WITH CHECK on session/enrollment policies | **ABSENT**; PG USING applies — **DEFINED** |
| RLS policy amendment | **NOT AUTHORIZED** |
| Writer-path RLS verification for 7.2 | **ARCHITECTURE RESOLUTION** — required before closure |
| Room same-school enforcement | **UNRESOLVED** (HD-7.2-013); **no** composite room-school FK |

---

## 15. Event / Idempotency Decision Matrix

| Topic | Status |
|-------|--------|
| same-COMMIT outbox + idempotency + fingerprint | **ALREADY LOCKED** (DD-016) |
| event identity ≠ idempotency identity | **ALREADY LOCKED** (DR-006) |
| Cascade `cause: exam_cancel` | **DEFINED** |
| Direct-command cause discriminator | **ARCHITECTURE RESOLUTION** (HD-7.2-011) |
| New event class coding | **NOT AUTHORIZED** |

---

## 16. Design-Lock Readiness Matrix

| Gate | Status | Evidence |
|------|--------|----------|
| Session command set | **PASS** | DD-001 / HD-004 |
| Enrollment command set | **PASS** | DD-002 / HD-004 |
| Permission vocabulary (HD-005 cancel naming) | **PARTIAL** | HD-005 LOCKED; catalog registration **UNRESOLVED** (HD-7.2-004) |
| Permission ownership | **BLOCKED** | HD-7.2-001 |
| Present semantics | **BLOCKED** | HD-7.2-005 |
| Multi-session rule | **BLOCKED** | HD-7.2-006 |
| Cancellation semantics | **BLOCKED** | HD-7.2-002 / HD-7.2-003 |
| Reassignment | **BLOCKED** | HD-7.2-007 |
| Restore | **PASS** | DD-011 FORBIDDEN |
| Grade correction after cancel | **BLOCKED** | HD-7.2-008 |
| Grade entry timing | **BLOCKED** | HD-7.2-009 |
| Completion ownership | **PASS** | DD-014 / DR-004 |
| HTTP | **PASS** (deferred) | DD-015 |
| Idempotency | **PASS** | DD-016 |
| Event causation | **PASS** | HD-7.2-011 Architecture Resolution |
| RLS verification requirements | **PASS** (requirement known) | DD-019 Architecture Resolution |
| Query AuthZ | **PASS** (deferred) | DD-020 |
| Room ownership | **BLOCKED** | HD-7.2-013 |
| Timezone | **PARTIAL** | HD-7.2-014 affirmation pending (non-critical if A) |
| Academic-year match | **PASS** | DD-008 |
| Room overlap | **PARTIAL** | HD-7.2-010 deferral affirmation pending |
| Seat number | **PARTIAL** | HD-7.2-012 |
| Bulk / capacity / Scheduled→Completed | **PASS** (deferred) | DD-021/024/028 |

**PASS rule:** locked, approved, explicitly deferred, or out of scope — and not a material unresolved blocker.

---

## 17. Unresolved Human Decisions

| HD | Decision | Priority | Recommended | Human Required | Status | Blocks Design Lock |
|----|----------|----------|-------------|----------------|--------|--------------------|
| HD-7.2-001 | Permission ownership (DD-004) | Critical | Option A (or B w/ Present split) | YES | UNRESOLVED | **YES** |
| HD-7.2-002 | Cancellation semantics (DD-009 A/B/D) | Critical | A1+B1+D1 | YES | UNRESOLVED | **YES** |
| HD-7.2-003 | CancelExamSession CURRENT grade (DD-027) | Critical | FAIL CLOSED | YES | UNRESOLVED | **YES** |
| HD-7.2-004 | Permission catalog registration (DD-003) | High | Register seven after ownership | YES | UNRESOLVED | **YES** |
| HD-7.2-005 | Confirmed → Present (DD-005) | High | A or B; InProgress; ≠ is_absent | YES | UNRESOLVED | **YES** |
| HD-7.2-006 | Multi-session per subject (DD-006) | High | ALLOW MULTIPLE | YES | UNRESOLVED | **YES** |
| HD-7.2-007 | Move/reassign (DD-010) | High | FORBIDDEN | YES | UNRESOLVED | **YES** |
| HD-7.2-008 | Correct after cancel (DD-012) | High | FAIL CLOSED | YES | UNRESOLVED | **YES** |
| HD-7.2-009 | Grade enter timing (DD-013) | High | C or A (human pick) | YES | UNRESOLVED | **YES** |
| HD-7.2-010 | Room overlap (DD-007) | Medium | Defer enforcement | YES | UNRESOLVED | NO* |
| HD-7.2-012 | Seat number (DD-022) | Medium | Optional free-text | YES | UNRESOLVED | NO* |
| HD-7.2-013 | Room school isolation (DD-025) | Medium | Fail-closed same school | YES | UNRESOLVED | **YES** |
| HD-7.2-014 | Timezone (DD-026) | Medium | School-local civil | YES | UNRESOLVED | NO* |

\*Blocks Design Lock only if product demands a hard rule without affirming deferral/default.

| HD | Decision | Priority | Status |
|----|----------|----------|--------|
| HD-7.2-011 | Event causation (DD-017) | Medium | **ARCHITECTURE RESOLUTION** (not human-unresolved) |

```text
Human decisions unresolved (awaiting human approval) = 13
Critical unresolved = 3
High unresolved = 6
Medium unresolved = 4
Architecture resolutions (non-human) = 1 (+ DD-019 verification requirement)
Conflicts = 0
Human decisions newly approved in this artifact = 0
```

---

## 18. Final Gate

```text
FINAL VERDICT:
NOT READY FOR DESIGN LOCK
```

### Why

Critical blockers remain without repository human approval:

1. Permission ownership (HD-7.2-001)
2. Cancellation semantics package (HD-7.2-002)
3. CancelExamSession CURRENT-grade guard (HD-7.2-003)

Plus High blockers: catalog registration, Present, multi-session, Move, grade Correct/Enter policies, and room school isolation.

Recommendations exist. **Recommendations are not approvals.**

```text
NEXT AUTHORIZED STEP:
Human project owner / security owner records approvals
  against HD-7.2-001…014 (as applicable)
THEN:
.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md
  (only after blockers resolved or explicitly deferred in an approved human record)
```

```text
Design Lock = NOT AUTHORIZED
Implementation Authorization = NOT GRANTED
```

---

## 19. STOP Declaration

```text
Implementation = NOT AUTHORIZED
Database changes = NONE
Application changes = NONE
Permission changes = NONE
RLS changes = NONE
HTTP = NOT AUTHORIZED
Design Lock = NOT AUTHORIZED

Human decisions unresolved = 13
Critical unresolved = 3
High unresolved = 6
Conflicts = 0

STOP = YES
```

```text
Do not implement Phase 7.2.
Do not create permissions.
Do not modify roles or config/security.php.
Do not expose HTTP.
Do not modify RLS or migrations.
Do not modify Grade handlers or CancelExam.
Do not claim Design Locked.
Do not treat Recommended Option as Final Decision.
```
