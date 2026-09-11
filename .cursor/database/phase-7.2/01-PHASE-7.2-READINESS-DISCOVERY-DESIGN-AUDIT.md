# MASTER PHASE 7 — PHASE 7.2 — READINESS / DISCOVERY / DESIGN AUDIT

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Exam Session + Exam Enrollment Lifecycle — Readiness / Discovery / Design Audit |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Document Type** | READINESS / DISCOVERY / DESIGN AUDIT |
| **Date** | 2026-09-11 |
| **Mode** | READ-ONLY |
| **Implementation** | **NONE** |
| **Authorization** | **AUDIT ONLY** |

```text
THIS DOCUMENT = readiness / discovery / design audit only
≠ DESIGN LOCK
≠ HUMAN IMPLEMENTATION AUTHORIZATION
≠ permission catalog change
≠ migration / RLS / HTTP / application change
```

### Project baseline (evidence)

| Item | Evidence |
|------|----------|
| PHP | `^8.3` (`composer.json`) |
| Laravel | `^13.17` |
| Architecture | Clean Architecture + CQRS Application handlers |
| DB | PostgreSQL (schemas + FORCE RLS); disposable `sis_test` for PG integration |
| Testing | PHPUnit Feature/Unit + `phpunit.database-pgsql.xml` |

---

## 2. Authorization / Scope

```text
Phase 7.1 = CLOSED (PASS — CLOSED)
Phase 7.2 implementation = NOT AUTHORIZED
HTTP exposure = NOT AUTHORIZED
Query AuthZ = NOT AUTHORIZED
CompleteExam implementation = NOT AUTHORIZED
New permissions = NOT AUTHORIZED
RLS changes = NOT AUTHORIZED
Migrations = NOT AUTHORIZED
Database schema changes = NOT AUTHORIZED
Application code changes = NOT AUTHORIZED
```

This audit may conclude design-lock readiness. It does **not** grant implementation.

---

## 3. Phase 7.1 Closure Baseline

Authoritative closure:

`.cursor/database/phase-7.1/18-PHASE-7.1-EXAM-ADMINISTRATION-FINAL-CLOSURE-GATE.md`

| Item | Status |
|------|--------|
| CreateExam / UpdateExam / CancelExam | **CLOSED** |
| HD-001 | **APPROVED** — `exam.create\|update\|cancel` → `grades_manager` |
| HD-007 | **GRANTED** (7.1 only) |
| RLS runtime | **PASS** (artifact `17`) |
| HTTP (exam admin) | **BLOCKED** |
| CompleteExam | **OUT OF SCOPE** |

### Phase 7.1 CancelExam cascade (authoritative for 7.2)

When CancelExam is permitted (atomic):

```text
Exam → Cancelled
Scheduled/InProgress sessions → Cancelled
active seats (Registered|Confirmed|Present) → Withdrawn
NO grade mutation
```

Phase 7.2 **must treat** this as existing authoritative behavior — not redesign CancelExam.

Evidence: `CancelExamHandler`, `ExamCancelGuard`, `EloquentExamRepository::cancelOpenSessionsForExam` / `withdrawActiveEnrollmentsForExam`, events with `cause: exam_cancel`.

---

## 4. Current Database State

### 4.1 `exams.exams` (parent — closed surface)

| Aspect | Evidence |
|--------|----------|
| Key columns | `id`, `academic_year_id`, `school_id`, `term_id`, `exam_type_id`, `name`, `start_date`, `end_date`, `status`, timestamps |
| Status | SMALLINT 1–5 (`ExamStatus`) |
| Unique | `(id, school_id)` |
| RLS | ENABLE + FORCE; school isolation via `app.current_school_id` |

### 4.2 `exams.exam_sessions` — **LOCKED / PROVEN**

| Aspect | Evidence |
|--------|----------|
| Columns | `id`, `exam_id`, `school_id`, `subject_id`, `session_date`, `start_time`, `end_time`, `room_id` nullable, `max_grade` default 100, `pass_grade` default 50, `status` default 1, `created_at` (**no `updated_at`**) |
| Ownership | Parent exam via composite FK `(exam_id, school_id)` → `exams.exams`; school via `school_id` |
| Academic year | **No column** — inherits from parent Exam (**LOCKED** contract) |
| Uniques | `(id, school_id)` |
| Checks (PG) | `end_time > start_time`; pass/max grade bounds; `status BETWEEN 1 AND 4` |
| Indexes | `exam_id`; `(subject_id, session_date)`; `school_id` |
| RLS | ENABLE + FORCE; policy `exam_sessions_school_isolation` (**USING only** — no separate WITH CHECK) |

Source: `database/migrations/2026_09_10_150000_phase3a_create_exams_tables.php`, `…150100_phase3a_enable_exams_rls.php`, blueprint.

### 4.3 `exams.exam_enrollments` — **LOCKED / PROVEN**

| Aspect | Evidence |
|--------|----------|
| Columns | `id`, `exam_session_id`, `school_id`, `enrollment_id`, `seat_number` nullable, `status` default 1, `created_at` (**no `updated_at`**) |
| Identity uniqueness | `UNIQUE(exam_session_id, enrollment_id)` — seat = **session + academic enrollment**, not `exam_id + student_id` |
| School composite | `(exam_session_id, school_id)` → sessions; `(enrollment_id, school_id)` → `enrollment.enrollments`; `UNIQUE(id, school_id)` for grade FKs |
| Checks | `status BETWEEN 1 AND 5` |
| Indexes | `exam_session_id`; `enrollment_id`; `school_id` |
| RLS | ENABLE + FORCE; policy `exam_enrollments_school_isolation` (USING only) |

### 4.4 `exams.student_grades` — dependency

| Aspect | Evidence |
|--------|----------|
| FKs | Composite to enrollment seat + session + school |
| Partial unique | one CURRENT grade per `(exam_enrollment_id, academic_year_id)` |
| RLS | ENABLE + FORCE + **WITH CHECK** |

---

## 5. Exam Session Discovery

### Identity

```text
PK: exams.exam_sessions.id
School-scoped identity: UNIQUE(id, school_id)
Business ownership: exam_id + school_id (composite FK to exams.exams)
Subject: subject_id (required)
```

**UNKNOWN / not DB-enforced:** uniqueness of `(exam_id, subject_id)` or non-overlapping times for same exam/subject.

### Ownership

| Dimension | Source |
|-----------|--------|
| Exam | `exam_id` (+ school composite) |
| School | `school_id` (must match parent exam school via composite FK) |
| Academic year | Parent `exams.exams.academic_year_id` only |

### Lifecycle states (PROVEN enums + CHECK)

```text
Scheduled (1) → InProgress (2) → Completed (3) [terminal]
              ↘ Cancelled (4) [terminal]
```

Application transition commands: **ABSENT** (enums + CHECKs only).

### Timing fields (PROVEN)

```text
session_date, start_time, end_time
NO started_at / completed_at / cancelled_at columns
NO updated_at
```

Timezone / calendar semantics: **UNKNOWN** at DB (dates/times stored without TZ columns).

---

## 6. Exam Enrollment Discovery

### Identity

```text
UNIQUE(exam_session_id, enrollment_id)
→ one academic enrollment per session
NOT exam_id + student_id (student via enrollment.enrollments.student_id)
```

### Ownership chain

```text
exam_enrollments
  → exam_sessions (session + school)
    → exams.exams (exam + year + school)
  → enrollment.enrollments (academic enrollment + school)
    → students
```

Year match `enrollment.academic_year_id == exam.academic_year_id`: **LOCKED** as application fail-closed rule in Master Lock; **no DB CHECK** today → must be enforced in CreateExamEnrollment handler design.

### Lifecycle states (PROVEN)

```text
Registered (1) → Confirmed (2) → Present (3)
                              ↘ Absent (4)     [inactive seat]
*active* → Withdrawn (5)                      [inactive seat]

isActiveSeat = Registered | Confirmed | Present
```

---

## 7. Phase 7.1 Dependency Analysis

| 7.1 Command | Interaction with sessions/enrollments/grades |
|-------------|-----------------------------------------------|
| CreateExam | Creates exam only — **no** sessions/enrollments |
| UpdateExam | Metadata/status on exam; Complete uses DR-004 session predicates (read) |
| CancelExam | Cascades open sessions → Cancelled; active seats → Withdrawn; blocked by CURRENT grade / Completed session |

### Conflict risk with Phase 7.2

| Topic | Assessment |
|-------|------------|
| Dedicated CancelExamSession vs CancelExam cascade | Cascade events already use `ExamSessionCancelled` with `cause: exam_cancel`. Phase 7.2 CancelExamSession must not double-cancel or invent parallel semantics. |
| CancelExamEnrollment vs CancelExam withdraw | Same — Withdrawal from exam cancel is authoritative cascade. |
| Open/Close after Exam Cancelled | Parent Cancelled must fail-closed Create/Open (Master Lock preconditions). |
| Permission inheritance | **FORBIDDEN assumption** — 7.1 `grades_manager` ownership of `exam.*` three perms does **not** auto-grant session/enrollment ops. |

```text
Phase 7.1 CancelExam cascade = existing authoritative behavior
```

---

## 8. Grade Dependency Analysis

| Question | Evidence | Status |
|----------|----------|--------|
| Grade → session / seat | Composite FKs on `student_grades` | **LOCKED** |
| Enter on Cancelled session/exam | `StudentGradeWriteGuard` rejects | **DEFINED** |
| Enter on Withdrawn/Absent seat | `isActiveSeat` fails | **DEFINED** |
| Grade may exist historically under Cancelled exam? | CancelExam **blocked** if CURRENT grade; VOIDED non-current does not block | **LOCKED** (DR-001) |
| CancelExamEnrollment vs CURRENT grade | Master Lock DR-002: FAIL CLOSED; no grade mutation | **LOCKED** (design) |
| Correct after session Cancelled | `assertCanCorrect` does **not** re-check session/exam cancel | **UNKNOWN / DESIGN DECISION REQUIRED** for 7.2+ grade integrity |
| Grade enter when session Scheduled (not open) | Guard does **not** require InProgress | **DEFINED** (current); may need 7.2 policy decision |

Classification legend applied: LOCKED / DEFINED / UNKNOWN / DESIGN DECISION REQUIRED.

---

## 9. Application / CQRS Discovery

### Present

| Area | Paths |
|------|-------|
| Exam writers (7.1) | `CreateExam` / `UpdateExam` / `CancelExam` (+ mutation support) |
| Grade writers | Enter / Correct / Void / Finalize |
| Grade reads | List by session; current by exam enrollment |
| Enums / cascade events | Session/Enrollment status VOs; cascade cancel events |
| Exam persistence | `ExamRecord`, `ExamRepositoryInterface` / Eloquent (cascade methods) |

### ABSENT (Phase 7.2 surface)

```text
CreateExamSession / UpdateExamSession / OpenExamSession / CloseExamSession / CancelExamSession
CreateExamEnrollment / UpdateExamEnrollment / CancelExamEnrollment
ExamSessionRecord / ExamEnrollmentRecord
Dedicated Session/Enrollment repositories
```

---

## 10. Existing Write Paths / Legacy Paths

| Path | Writes sessions/enrollments? | Notes |
|------|------------------------------|-------|
| CancelExam cascade | **YES** | Authoritative Phase 7.1 |
| Test seeders / support graphs | **YES** (test-only) | Not production API |
| Controllers | **NO** session/enrollment lifecycle | |
| Direct Eloquent ExamSession model | **ABSENT** | |
| Grade handlers | **NO** (read session/seat status only) | |

No discovered production legacy dual-write path for session/enrollment lifecycle besides CancelExam cascade.

---

## 11. Security / Authorization Discovery

### Live catalog

| Permission | Present |
|------------|---------|
| `exam.create` / `exam.update` / `exam.cancel` | **YES** → `grades_manager` |
| `exam.session.*` | **ABSENT** |
| `exam.enrollment.*` | **ABSENT** |
| `exam.session.cancel` | **ABSENT** (HD-005 forbids introducing this name) |

### Design vocabulary (Master Lock — not catalogued)

```text
exam.session.create / update / open / close
exam.enrollment.create / update / cancel
```

HD-005: CancelExamSession uses **`exam.session.update`**, not `exam.session.cancel`.

```text
Phase 7.2 role → permission ownership = UNRESOLVED
HUMAN DECISION REQUIRED before implementation
```

Do **not** assume `grades_manager` inherits these.

---

## 12. RLS / School Isolation Discovery

| Table | ENABLE | FORCE | Policy | WITH CHECK |
|-------|--------|-------|--------|------------|
| `exams.exams` | YES | YES | school isolation | USING (shared) |
| `exams.exam_sessions` | YES | YES | school isolation | USING only |
| `exams.exam_enrollments` | YES | YES | school isolation | USING only |
| `exams.student_grades` | YES | YES | school isolation | USING + WITH CHECK |

Phase 7.1 Verification `17`: same-school / cross-school / missing GUC / FORCE RLS = **PASS** for these tables.

**Condition (non-blocking for design lock):** session/enrollment policies lack explicit WITH CHECK (PG uses USING for both when WITH CHECK omitted — verify behavior remains required in 7.2 tests).

No Phase 7.2 table lacks school isolation today — tables already exist.

---

## 13. Outbox / Idempotency Discovery

Phase 7.1 pattern (LOCKED for reuse):

```text
same-COMMIT: mutation + outbox.stage + idempotency.store
event identity ≠ idempotency identity (DR-006)
fingerprint conflict → FAIL CLOSED
```

Infrastructure: `audit.outbox_messages`, `audit.idempotency_keys` — **do not redesign**.

Candidate future events (design names only — Master Lock §22):

```text
ExamSessionCreated / Updated / Opened / Closed / Cancelled
ExamEnrollmentCreated / Updated / Cancelled
```

Note: `ExamSessionCancelled` / `ExamEnrollmentCancelled` already exist for **CancelExam cascade** — Phase 7.2 command events must distinguish causation (`cause` / command name) without colliding idempotency keys.

---

## 14. HTTP / API Boundary Discovery

| Surface | Status |
|---------|--------|
| Exam admin HTTP (Create/Update/Cancel Exam) | **ABSENT / BLOCKED** |
| Session/Enrollment lifecycle HTTP | **ABSENT** |
| `GET exam-sessions/{id}/grades` | **PRESENT** (grade read) |
| `GET exam-enrollments/{id}/current-grade` | **PRESENT** (grade read) |

```text
HTTP exposure for Phase 7.2 = DEFERRED
```

Reason: Phase 7.1 intentionally left HTTP blocked; 7.2 must not auto-expose; requires separate exposure authorization after command AuthZ lock.

---

## 15. Lifecycle State Machine Discovery

### 15.1 Exam Session

| From | To | Existing Evidence | Allowed? | Authority | Notes |
|------|----|-------------------|----------|-----------|-------|
| Scheduled | InProgress | Master Lock §10.2; enum | **LOCKED** (design) | `OpenExamSession` / future `exam.session.open` | App transition **ABSENT** |
| InProgress | Completed | Master Lock §10.2 | **LOCKED** (design) | `CloseExamSession` / `exam.session.close` | App **ABSENT** |
| Scheduled | Cancelled | Master Lock + CancelExam cascade | **LOCKED** | CancelExamSession **or** CancelExam cascade | Cascade **IMPLEMENTED** |
| InProgress | Cancelled | Same | **LOCKED** | Same | Cascade **IMPLEMENTED** |
| Completed | * | Master Lock | **FORBIDDEN** | — | |
| Cancelled | * | Master Lock | **FORBIDDEN** | — | No reopen |
| * | * (metadata) | DR-003 | Scheduled-only field allowlist | `UpdateExamSession` | Status not via Update |

### 15.2 Exam Enrollment

| From | To | Existing Evidence | Allowed? | Authority | Notes |
|------|----|-------------------|----------|-----------|-------|
| Registered | Confirmed | Master Lock §10.3 | **LOCKED** (design) | `UpdateExamEnrollment` | App **ABSENT** |
| Confirmed | Present | Master Lock; HD-006 | **DEFERRED / DESIGN DECISION REQUIRED** | TBD actor | HD-006 → 7.2 Design Lock |
| Registered\|Confirmed\|Present | Absent | Master Lock | **LOCKED** (design) | `exam.enrollment.update` | ≠ grade `is_absent` |
| active | Withdrawn | Master Lock DR-002 + CancelExam cascade | **LOCKED** | `CancelExamEnrollment` **or** CancelExam | CURRENT grade fail-closed for dedicated cancel |
| Absent\|Withdrawn | active | Master Lock | **FORBIDDEN** | — | Without Design Change |
| Move session / reassignment | — | — | **UNKNOWN** | — | **HUMAN / DESIGN DECISION REQUIRED** |

---

## 16. Integrity / Constraint Analysis

| Invariant | Current DB Enforcement | Application Enforcement | Test Evidence | Status |
|-----------|------------------------|-------------------------|---------------|--------|
| School ownership (session/enrollment) | Composite FKs + FORCE RLS | SchoolContext (when wired) | Phase 7.1 RLS suite | **PASS** |
| Exam owns session | Composite FK | Design preconditions | Schema tests | **PASS** |
| Seat unique per session+enrollment | UNIQUE | — | Schema | **PASS** |
| Duplicate subject sessions per exam | **NONE** | **ABSENT** | — | **DESIGN DECISION REQUIRED** |
| Session time range | CHECK | — | Schema | **PASS** |
| Pass ≤ max grade | CHECK | — | Schema | **PASS** |
| Status ranges | CHECK | Enums | Unit enum tests | **PASS** |
| Year match enrollment↔exam | **NONE** | Design LOCKED (must implement) | — | **DESIGN DECISION REQUIRED** (enforce in handler) |
| Cancelled exam → no open session create | **NONE** | Design precondition | — | **DESIGN DECISION REQUIRED** |
| Cancelled session → no grade enter | — | StudentGradeWriteGuard | Grade tests | **PASS** |
| Withdrawn seat → no grade enter | — | isActiveSeat | Grade tests | **PASS** |
| CURRENT grade blocks CancelExam | Query in repo | ExamCancelGuard | Feature tests | **PASS** |
| CURRENT grade blocks CancelExamEnrollment | — | Design DR-002 only | — | **DEFINED** (not implemented) |
| Cross-school relationships | Composite FKs + RLS | Authority | RLS tests | **PASS** |
| Session/enrollment RLS WITH CHECK explicit | Using-only policies | — | — | **CONDITION** (document / verify) |

---

## 17. Performance / Index / Growth Considerations

| Topic | Finding | Future requirement |
|-------|---------|-------------------|
| Leading `school_id` | Present on sessions/enrollments | Preserve for RLS + tenant filters |
| `(subject_id, session_date)` | Present | Useful for timetable-like queries |
| Seat unique | Present | Good for assign idempotency |
| Missing `(exam_id, status)` | Not present | Consider when listing open sessions by exam (measure first) |
| Missing `(exam_id, subject_id)` unique | Not present | Only if product forbids multi-session per subject |
| Growth | Sessions ≪ enrollments ≪ grades | Enrollments will dominate; batch assign; avoid N+1 seat creates |
| Partitioning | Grades partitioned by year; sessions/enrollments not | Re-evaluate only with measured volume |
| RLS predicate | GUC equality on `school_id` | Keep `school_id` selective + indexed |

**Do not create indexes in this phase.**

---

## 18. Design Decision Register

| ID | Decision | Current State | Risk | Required Before Implementation | Proposed Direction |
|----|----------|---------------|------|--------------------------------|--------------------|
| 7.2-DD-001 | Session command set finalization | Master Lock INCLUDE list | Medium | YES | **PROPOSAL ONLY:** Create/Update/Open/Close/CancelExamSession as in Master Lock |
| 7.2-DD-002 | Enrollment command set | Master Lock INCLUDE | Medium | YES | **PROPOSAL ONLY:** Create/Update/CancelExamEnrollment; no free-form CRUD |
| 7.2-DD-003 | Permission vocabulary | HD-005 locked cancel naming; catalog ABSENT | **High** | YES | **PROPOSAL ONLY:** adopt Master Lock names; cancel session via `exam.session.update` |
| 7.2-DD-004 | Role ownership for session/enrollment perms | **UNRESOLVED** | **High** | YES | **HUMAN DECISION REQUIRED** — do not assume `grades_manager` |
| 7.2-DD-005 | Confirmed → Present | HD-006 deferred | Medium | YES | Resolve in 7.2 Design Lock (actor + session-open precondition) |
| 7.2-DD-006 | Multi-session per subject under one exam | **UNKNOWN** DB | Medium | YES | **HUMAN DECISION REQUIRED** |
| 7.2-DD-007 | Session overlap / room conflict | **UNKNOWN** | Low–Med | Before impl if product needs | Optional constraint later |
| 7.2-DD-008 | Year match enforcement | Design LOCKED; no DB CHECK | High | YES | Fail-closed in CreateExamEnrollment |
| 7.2-DD-009 | Interaction: CancelExamSession vs CancelExam cascade | Cascade exists | High | YES | Dedicated cancel must be idempotent w.r.t. already-cancelled |
| 7.2-DD-010 | Move/reassign seat across sessions | **UNKNOWN** | Medium | YES | Default **FORBIDDEN** unless Design Change |
| 7.2-DD-011 | Restore Withdrawn/Absent → active | Master Lock FORBIDDEN | High | YES | Keep forbidden |
| 7.2-DD-012 | Grade correct after session cancel | Guard gap | Medium | YES | **DESIGN DECISION REQUIRED** |
| 7.2-DD-013 | Grade enter requires session InProgress? | Currently not required | Medium | YES | **HUMAN DECISION REQUIRED** |
| 7.2-DD-014 | Exam completion ownership | DR-004 via UpdateExam; no CompleteExam | Medium | YES | Keep CompleteExam out unless separate DCR; CloseExamSession feeds DR-004 |
| 7.2-DD-015 | HTTP exposure for 7.2 | Deferred | High | After AuthZ lock | Keep **BLOCKED** until separate authorization |
| 7.2-DD-016 | Idempotency required for all 7.2 writers | Pattern from 7.1 | High | YES | Require same-COMMIT + fingerprint |
| 7.2-DD-017 | Event causation vs cascade events | Cascade events exist | Medium | YES | Distinct command_name / cause |
| 7.2-DD-018 | `updated_at` on sessions/enrollments | Absent | Low | Optional | Prefer avoid schema change unless required |
| 7.2-DD-019 | Session RLS WITH CHECK parity | USING-only | Low | Verification | Confirm INSERT/UPDATE fail-closed in 7.2 PG tests |
| 7.2-DD-020 | Query AuthZ for sessions/enrollments | Deferred globally | Medium | Not for 7.2 writers | Keep query AuthZ **DEFERRED** |

---

## 19. Conflicts / Contradictions

| Item | Status |
|------|--------|
| Schema vs enums vs Master Lock status vocab | **NONE** (aligned) |
| HD-005 vs Master Lock §10.2 permission cell (`exam.session.update` or dedicated) | **RESOLVED by HD-005** → use `exam.session.update` for CancelExamSession |
| Phase 7.1 HTTP blocked vs grade GET on exam-sessions | **NOT A CONFLICT** — grade reads only; not session lifecycle |
| Cascade CancelExam events named like 7.2 command events | **CONTAINABLE** — distinguish cause; design register 7.2-DD-017 |

No critical schema contradiction discovered.

---

## 20. Missing Evidence

| Gap | Impact |
|-----|--------|
| Live PG catalog dump beyond migrations | Low — migrations + Phase 3A/3B tests + RLS Verification 17 sufficient for design |
| Product rule: one session per subject | Blocks uniqueness decision (7.2-DD-006) |
| Invigilator / teacher role for Present marking | Blocks HD-006 resolution |
| Measured production volume for sessions/seats | Performance only |

---

## 21. Implementation Readiness Assessment

| Gate | Status |
|------|--------|
| Database structure understood | **PASS** |
| Session lifecycle understood | **PASS** (design LOCKED; app ABSENT) |
| Enrollment lifecycle understood | **PASS** (with HD-006 deferred) |
| State transitions resolved | **PASS WITH GAPS** (Present transition deferred) |
| Grade dependencies understood | **PASS** (one correct-path gap listed) |
| Security model resolved | **BLOCKED** for implementation; **listed** for design |
| RLS model resolved | **PASS** (tables covered; WITH CHECK note) |
| Outbox/idempotency model resolved | **PASS** (reuse 7.1 pattern) |
| CQRS command boundaries resolved | **PASS** (Master Lock candidates; formal register next) |
| Legacy write paths understood | **PASS** |
| Integrity rules resolved | **PASS WITH CONDITIONS** (year match / multi-session) |
| Performance requirements understood | **PASS** |
| Conflicts resolved | **PASS** |
| Human decisions identified | **PASS** (register §18) |

---

## 22. Recommended Phase 7.2 Design-Lock Scope

Recommend Design Lock to cover **commands only** (writers):

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

Preserve:

```text
HD-004 / HD-005 / HD-006
DR-001 CancelExam cascade authority
DR-002 CancelExamEnrollment vs grades
DR-003 mutability allowlists
DR-004 exam completion predicates (via UpdateExam; no CompleteExam)
DR-006 event ≠ idempotency identity
Phase 7.1 exam.create/update/cancel ownership unchanged
```

---

## 23. Explicit Non-Scope

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
Grade correct/void/finalize redesign = NOT AUTHORIZED
Automatic PHP patching = NOT AUTHORIZED
```

---

## 24. Final Readiness Verdict

```text
FINAL VERDICT:
READY WITH CONDITIONS
```

### Conditions (must be locked before implementation — not before Design Decision Register)

1. **Human security ownership** for session/enrollment permissions (7.2-DD-003 / 7.2-DD-004).
2. **HD-006** Confirmed → Present actor + preconditions (7.2-DD-005).
3. **Product rules:** multi-session-per-subject; seat move/reassign; grade-enter requires InProgress (7.2-DD-006 / 010 / 013).
4. **Handler-enforced year match** and cancelled-parent fail-closed (7.2-DD-008 / Create preconditions).
5. **Event causation** strategy vs CancelExam cascade events (7.2-DD-017).
6. **HTTP remains DEFERRED** until separate authorization after AuthZ lock (7.2-DD-015).

No critical blocker prevents starting the Design Decision Register.

```text
NEXT AUTHORIZED ARTIFACT:
.cursor/database/phase-7.2/02-PHASE-7.2-DESIGN-DECISION-REGISTER.md
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
```
