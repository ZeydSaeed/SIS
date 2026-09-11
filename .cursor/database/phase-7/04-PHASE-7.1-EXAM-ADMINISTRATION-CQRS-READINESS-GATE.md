# MASTER PHASE 7 — PHASE 7.1 — EXAM ADMINISTRATION CQRS

# READINESS / DESIGN RESOLUTION / IMPLEMENTATION AUTHORIZATION GATE

**Document Type:** READ-ONLY READINESS + DESIGN RESOLUTION + IMPLEMENTATION AUTHORIZATION GATE  
**Phase:** MASTER PHASE 7 — Assessment / Exams / Grades  
**Subphase:** PHASE 7.1 — Exam Administration CQRS  
**Date:** 2026-09-11  
**Authorization:** DESIGN / READINESS ONLY — **NO IMPLEMENTATION**  
**Implementation Authorization:** **NOT GRANTED**

```text
NO CODE · NO MIGRATIONS · NO DDL · NO RLS · NO PERMISSIONS · NO ROUTES
NO PHASE 7.1 IMPLEMENTATION · NO PHASE 7.2 · ATTENDANCE NOT REOPENED
P7-D2 RESULTS/GPA/RANKING/TRANSCRIPT NOT RESOLVED HERE
```

**Authoritative upstream (unchanged):**

* [`02-MASTER-PHASE-7-DESIGN-LOCK.md`](./02-MASTER-PHASE-7-DESIGN-LOCK.md)
* [`03-MASTER-PHASE-7-DESIGN-LOCK-GATE.md`](./03-MASTER-PHASE-7-DESIGN-LOCK-GATE.md)

This gate does **not** rewrite or weaken the Master Design Lock.  
Where Master Lock deferred Cancel detail to “7.1 design,” this document records **required decisions** and evidence — it does **not** silently amend `02-…DESIGN-LOCK.md`.

---

## 1. Executive Verdict

```text
BLOCKED
```

**Why BLOCKED (not READY WITH CONDITIONS / not READY FOR HUMAN IMPLEMENTATION AUTHORIZATION):**

| Critical gap | Class | Blocks 7.1? |
|--------------|-------|-------------|
| **CancelExam** session/seat cascade + grade interaction not settled in Master Design Lock | **DESIGN BLOCKER** | **YES** |
| **CancelExamEnrollment** vs current/finalized grade not settled | **DESIGN BLOCKER** | **YES** |
| `exam.*` permissions **PROVEN ABSENT**; role mapping unresolved (Design Lock: do not invent Examiner) | **IMPLEMENTATION BLOCKER** | **YES** |
| Desired idempotency **same-transaction** model **not** satisfied by current Grade/Attendance handlers (post-commit `store`) | **IMPLEMENTATION PREREQUISITE** | **YES** for safe 7.1 |
| Exam-admin concurrency strategy unproven (no writers; no tests) | **REQUIRED BEFORE IMPLEMENTATION** | **YES** |
| Narrow mutable-field sets for Update* not human-approved (proposed below only) | **DESIGN BLOCKER** until approved | **YES** |

**Foundation health (does not unblock 7.1):** Phase 3A schema + RLS/FORCE, Phase 3B grades SSOT + partitions parent, Phase 3B.1 Grade CQRS remain **PROVEN** and must be retained.

**No Design Conflict** with Master Lock was found that would require editing `02-…` / `03-…`. Open items are **deferred decisions** the Master Lock already labeled as TBD / KNOWN DESIGN RISK / DEFER to 7.1 — they remain **unresolved for implementation**.

---

## 2. Authorization Boundary

```text
THIS DOCUMENT ≠ IMPLEMENTATION AUTHORIZATION
THIS DOCUMENT ≠ PERMISSION TO CREATE COMMANDS / ROUTES / DDL / RLS
```

Allowed work performed: repository inspection, live PostgreSQL catalog inspection, comparison to Design Lock, design-resolution analysis, gate writing.

Forbidden work: all application/database/security implementation listed in the Phase 7.1 readiness prompt §0.

---

## 3. Authoritative Documents

| Document | Role | Status |
|----------|------|--------|
| `02-MASTER-PHASE-7-DESIGN-LOCK.md` | Master Phase 7 authority | **DESIGN LOCKED** — not modified |
| `03-MASTER-PHASE-7-DESIGN-LOCK-GATE.md` | Human design-lock gate | Unchanged |
| `01-PHASE-7-…READINESS-AUDIT.md` | Discovery input | Unchanged |
| Phase 3A / 3B / 3B.1 gates | Foundation evidence | Retained |
| Phase 6 Attendance final gate | Boundary | CLOSED — not reopened |
| This file (`04-…`) | Phase 7.1 readiness only | **NEW** |

If future work contradicts Master Lock:

```text
Design Change Request → Impact Analysis → Human Approval → Updated Design Lock
```

---

## 4. Evidence Classification

| Finding | Class | Evidence |
|---------|-------|----------|
| Exam foundation tables + school composite FKs | **PROVEN** | Live PG + migration `2026_09_10_150000` |
| School-scoped FORCE RLS on exams/sessions/enrollments/grades | **PROVEN** | Live `relrowsecurity` / `relforcerowsecurity` |
| `exam_types` global, RLS off | **PROVEN** | Live catalog |
| `student_grades` LIST partitioned; 0 children; no DEFAULT | **PROVEN** | Live + migration `160000` |
| Reject-DELETE trigger on grades | **PROVEN** | Live trigger + migration |
| Grade CQRS Enter/Correct/Void/Finalize | **PROVEN** | `app/Application/Exams/Commands/*` |
| CreateExam* / exam admin handlers | **PROVEN ABSENT** | Application search — zero matches |
| `exam.*` permissions / exam roles | **PROVEN ABSENT** | `config/security.php` |
| CancelExam cascade policy | **DOCUMENTED ONLY** (Master Lock TBD) | Design Lock §9.3 CancelExam |
| CancelExamEnrollment vs grade | **DOCUMENTED ONLY** (KNOWN DESIGN RISK) | Design Lock §9.3 / §35 |
| Idempotency store table | **PROVEN** | `audit.idempotency_keys` |
| Idempotency inside same UoW as mutation | **PROVEN ABSENT** | Grade + Attendance Cancel: `store` after commit |
| Session/enrollment `academic_year_id` columns | **PROVEN ABSENT** | Live columns |
| Exam admin concurrency tests | **PROVEN ABSENT** | tests search |

---

## 5. Existing Foundation Audit

### 5.1 `exams.exam_types` — **PROVEN**

| Expectation | Reality |
|-------------|---------|
| Global catalog | **YES** |
| No school RLS while global | **YES** (`rls=false`, `force=false`) |

### 5.2 `exams.exams` — **PROVEN**

| Expectation | Reality |
|-------------|---------|
| School scoped | **YES** (`school_id`) |
| RLS + FORCE | **YES** |
| Academic year association | **YES** (`academic_year_id` FK) |
| Status CHECK 1–5 | **YES** |

### 5.3 `exams.exam_sessions` — **PROVEN**

| Expectation | Reality |
|-------------|---------|
| School scoped + FORCE RLS | **YES** |
| Exam association | **YES** + composite `(exam_id, school_id)` |
| Own `academic_year_id` | **PROVEN ABSENT** — year only via parent exam |
| Status CHECK 1–4 | **YES** |

### 5.4 `exams.exam_enrollments` — **PROVEN**

| Expectation | Reality |
|-------------|---------|
| School scoped + FORCE RLS | **YES** |
| Session + enrollment composites | **YES** |
| Own `academic_year_id` | **PROVEN ABSENT** |
| Unique `(exam_session_id, enrollment_id)` | **YES** |

### 5.5 `exams.student_grades` — **PROVEN**

| Expectation | Reality |
|-------------|---------|
| Grade SSOT | **YES** |
| LIST by `academic_year_id` | **YES** |
| RLS + FORCE + WITH CHECK | **YES** |
| Current-grade partial UNIQUE | **YES** |
| No hard delete | **YES** (trigger) |
| Live partitions | **0** (years = **0**) |

### 5.6 Application writers for exam admin

```text
PROVEN ABSENT — Phase 7.1 gap (expected)
```

Grade writers remain the only Exams Application CQRS path.

---

## 6. Matrix A — Exam Lifecycle

**Authoritative vocabulary (Design Lock + domain enum) — do not invent Published/Archived:**

```text
Draft(1) → Scheduled(2) → InProgress(3) → Completed(4) TERMINAL
Draft|Scheduled|InProgress → Cancelled(5) TERMINAL
```

| Command | Current Status | Allowed? | New Status | Side Effects | Failure |
|---------|----------------|----------|------------|--------------|---------|
| CreateExam | — | **YES** (design) | **Draft (1)** | Insert exam row; no sessions | Invalid school/year/term/type; RLS deny |
| UpdateExam (metadata) | Draft | **CONDITIONAL** | Draft | Narrow fields only (§12) | Terminal; cross-school |
| UpdateExam (metadata) | Scheduled | **CONDITIONAL** | Scheduled | Narrow fields only | Same |
| UpdateExam (metadata) | InProgress | **CONDITIONAL** | InProgress | Narrow fields only | Same |
| UpdateExam (metadata) | Completed | **NO** | — | none | fail closed |
| UpdateExam (metadata) | Cancelled | **NO** | — | none | fail closed |
| UpdateExam status → Scheduled | Draft | **YES** (Design Lock matrix) | Scheduled | none required | Invalid dates |
| UpdateExam status → InProgress | Scheduled | **YES** (Design Lock) | InProgress | none auto-open sessions | Parent cancelled |
| UpdateExam status → Completed | InProgress | **UNKNOWN / BLOCKER** | Completed | Sessions policy TBD in Master Lock | — |
| CancelExam | Draft | **UNKNOWN / BLOCKER** | Cancelled? | Cascade TBD | — |
| CancelExam | Scheduled | **UNKNOWN / BLOCKER** | Cancelled? | Cascade TBD | — |
| CancelExam | InProgress | **UNKNOWN / BLOCKER** | Cancelled? | Cascade TBD | — |
| CancelExam | Completed | **NO** | — | none | fail closed |
| CancelExam | Cancelled | **NO** | — | none | fail closed |

```text
ExamStatus → Completed transition policy for sessions = UNKNOWN / BLOCKER (Master Lock: "Sessions policy TBD")
CancelExam child/grade policy = DESIGN BLOCKER (see §9)
```

---

## 7. Matrix B — Exam Session Lifecycle

```text
Scheduled(1) → InProgress(2) → Completed(3) TERMINAL
Scheduled|InProgress → Cancelled(4) TERMINAL
```

| Command | Valid current | Invalid | Target | Side effects | Grade impact | Enrollment impact | Event (design name) | Audit | Authz | Concurrency | Idempotency |
|---------|---------------|---------|--------|--------------|--------------|-------------------|---------------------|-------|-------|-------------|-------------|
| CreateExamSession | Parent exam not Cancelled/Completed* | Parent Cancelled; cross-school | Scheduled | Insert | None | None | `ExamSessionCreated` | Yes | `exam.session.create` | Unique natural key TBD | **REQUIRED** |
| UpdateExamSession | Scheduled (narrow); InProgress limited | Completed/Cancelled | same | Narrow fields §12 | None auto | None | `ExamSessionUpdated` | Yes | `exam.session.update` | Atomic row update | **REQUIRED** |
| OpenExamSession | Scheduled | Completed/Cancelled; parent Cancelled | InProgress | Status only | None | None | `ExamSessionOpened` | Yes | `exam.session.open` | `UPDATE … WHERE status=1` | **REQUIRED** |
| CloseExamSession | **InProgress only** | Scheduled† / Completed / Cancelled | Completed | Status only | None | None | `ExamSessionClosed` | Yes | `exam.session.close` | `UPDATE … WHERE status=2` | **REQUIRED** |
| CancelExamSession | Scheduled \| InProgress | Completed‡ / Cancelled | Cancelled | Status only | Enter already blocked if Cancelled (**PROVEN** WriteGuard) | Seats retained unless separate cancel | `ExamSessionCancelled` | Yes | `exam.session.close`≠; use cancel perm / update | Predicate update | **REQUIRED** |

\* Parent **Completed**: CreateSession = **UNKNOWN / recommend NO** (fail closed) until Design Change.  
† `Scheduled → Completed` = **DEFER** per Design Lock — **NOT allowed** in 7.1 without Design Change.  
‡ Cancel after Completed = **FORBIDDEN** without Design Change.

**Grade impact of CancelExamSession:** no direct grade mutation — **PROVEN** WriteGuard rejects enter on cancelled session; historical grades untouched.

---

## 8. Matrix C — Exam Enrollment Lifecycle

```text
Registered(1) → Confirmed(2) → Present(3)
Registered|Confirmed|Present → Absent(4)   [inactive seat]
Registered|Confirmed|Present → Withdrawn(5) [Cancel]
```

`ExamEnrollmentStatus` ≠ `student_grades.is_absent` — **DESIGN LOCKED**.

| Command | Rules |
|---------|--------|
| CreateExamEnrollment | Initial **Registered**; require active academic enrollment; session not Cancelled; unique `(exam_session_id, enrollment_id)` → duplicate = conflict; composite school FKs; **app must** assert enrollment.academic_year_id == exam.academic_year_id (**GAP**: no DB year FK on seat) |
| UpdateExamEnrollment | **Status transitions only** + optional `seat_number`; **immutable** identity keys |
| CancelExamEnrollment | → **Withdrawn**; grade interaction = **DESIGN BLOCKER** (§10) |

| FROM | TO via Update | Allowed? |
|------|---------------|----------|
| Registered | Confirmed | YES (design) |
| Confirmed | Present | YES (design) — actor mapping TBD |
| Registered\|Confirmed\|Present | Absent | YES (design) |
| *active* | Withdrawn | via **CancelExamEnrollment** only |
| Absent\|Withdrawn | any active | **FORBIDDEN** |
| Present | Confirmed | **NO** (not in Design Lock) |

**Terminal seat behavior:** Absent / Withdrawn are inactive for grade **enter** (`isActiveSeat` **PROVEN**). Historical grades may still exist.

---

## 9. CancelExam Resolution

### 9.1 Master Design Lock state

| Topic | Locked? | Quote / fact |
|-------|---------|--------------|
| Include CancelExam command | **YES** | P7-D4 INCLUDE |
| Session side effects | **NO** | “cascade cancel sessions/seats **or** reject if children active” |
| Grades when cancel | **NO** | “cancel after grades exist without explicit supersession policy (**DEFER detail to 7.1 design**)” |
| Hard delete | **FORBIDDEN** | Design Lock |

### 9.2 Options (must NOT assume)

| Concern | Options | Chosen for implementation? |
|---------|---------|----------------------------|
| Sessions | A unchanged · B auto-cancel · C invalid-retain · D other | **NONE LOCKED** |
| Enrollments | A unchanged · B auto-cancel · C auto-withdraw · D other | **NONE LOCKED** |
| Grades | A untouched · B voided · C deleted · D other | **NONE LOCKED** — C **forbidden** by SSOT |

### 9.3 Safety default (prompt) — **NOT Master-Lock**

> CancelExam MUST NOT silently mutate, void, delete, or rewrite Student Grades unless an explicit locked policy authorizes that behavior.

### 9.4 Gate classification

```text
P7.1-B-001 — DESIGN BLOCKER — CancelExam cascade + grade interaction UNSETTLED
```

### 9.5 Proposed resolution for human approval (NOT applied; NOT written into Master Lock)

**P7.1-DR-001 (PROPOSED — requires human approval):**

```text
1. Grades: ALWAYS untouched by CancelExam (no void, no delete, no rewrite).
2. If ANY student_grades row exists (current OR historical) for any seat under this exam → FAIL CLOSED.
3. If ANY exam_session status = Completed → FAIL CLOSED.
4. If exam status ∈ {Completed, Cancelled} → FAIL CLOSED.
5. On success (atomic transaction):
   - set exam → Cancelled
   - set all sessions in {Scheduled, InProgress} → Cancelled
   - set all seats in active statuses → Withdrawn
6. No hard DELETE.
7. Outbox: ExamCancelled (+ optional session/enrollment cancelled events or single aggregate event — naming decision required).
```

Until human approves P7.1-DR-001 (or an alternate explicit policy) via Design Change / gate acceptance that updates Master Lock Change Control:

```text
CancelExam = NOT IMPLEMENTABLE
```

---

## 10. CancelExamEnrollment Resolution

### 10.1 Master Design Lock state

| Topic | Locked? |
|-------|---------|
| Target status Withdrawn | **YES** |
| Grade interaction | **NO** — “KNOWN DESIGN RISK — fail-closed or require void first” |
| Hard delete seat | **FORBIDDEN** |

### 10.2 Distinction — **PROVEN / LOCKED**

| Concept | Meaning |
|---------|---------|
| Seat `Absent (4)` | Participation inactive |
| Grade `is_absent` | Score semantics on grade row |
| Seat `Withdrawn (5)` | Cancelled seat |
| Grade `Voided` | Grade lifecycle via Grade CQRS only |

### 10.3 Gate classification

```text
P7.1-B-002 — DESIGN BLOCKER — CancelExamEnrollment vs existing grade UNSETTLED
```

### 10.4 Proposed resolution (PROPOSED — human approval required)

**P7.1-DR-002:**

```text
1. CancelExamEnrollment MUST NOT automatically mutate StudentGrade state.
2. If a CURRENT grade exists for the exam_enrollment_id → FAIL CLOSED
   (actor must Void via VoidStudentGrade / Correct path first if business requires).
3. Finalized current grade → same FAIL CLOSED (no silent void).
4. Historical non-current voided grades may remain; seat still may move to Withdrawn only when no current grade.
5. No hard DELETE of seat or grades.
```

```text
CancelExamEnrollment = NOT IMPLEMENTABLE until P7.1-DR-002 (or alternate) approved
```

---

## 11. Matrix J — Grade Interaction

| Command | May touch `student_grades`? | Bypass Grade CQRS? | Notes |
|---------|----------------------------:|--------------------|-------|
| Create/Update Exam/Session/Enrollment | **NO** | N/A | Admin only |
| Open/Close Session | **NO** | N/A | |
| CancelExamSession | **NO** direct | N/A | Future enters blocked (**PROVEN** guard) |
| CancelExam | **NO** until policy; propose untouched + reject if any grades | **FORBIDDEN** silent void | **BLOCKER** |
| CancelExamEnrollment | **NO** auto-mutate; propose reject if current grade | **FORBIDDEN** silent void | **BLOCKER** |
| Enter/Correct/Void/Finalize | **YES** — sole write path | N/A | Retained 3B.1 |

**SSOT protection:** Phase 7.1 must not introduce `exam_results` / `marks` / `results.*` grade stores — Master Lock + Feature Contract.

---

## 12. Update Commands — Narrow Mutability (PROPOSED)

Master Lock: Update* must not be generic CRUD. Exact field lists were **not** fully enumerated → **DESIGN BLOCKER** until human approves.

### 12.1 `UpdateExam` — proposed mutable set

| Field | Mutable? | Allowed states | Rule |
|-------|----------|----------------|------|
| `name` | YES | Draft/Scheduled/InProgress | Audit |
| `start_date`, `end_date` | YES | Draft/Scheduled (InProgress = **NO** proposed) | `end >= start` CHECK |
| `exam_type_id` | YES | Draft only (proposed) | FK |
| `term_id` | YES | Draft only (proposed) | Must stay in year |
| `status` | YES via allowed transitions only | See Matrix A | Not free int patch |
| `id`, `school_id`, `academic_year_id` | **IMMUTABLE** | — | fail closed |
| `created_at` | **IMMUTABLE** | — | |

### 12.2 `UpdateExamSession` — proposed mutable set

| Field | Mutable? | Allowed states |
|-------|----------|----------------|
| `session_date`, `start_time`, `end_time` | YES | Scheduled; InProgress limited/NO for date (proposed: Scheduled only) |
| `room_id` | YES | Scheduled \| InProgress |
| `max_grade`, `pass_grade` | YES | **Scheduled only** (after grades exist → **NO**) |
| `subject_id` | **IMMUTABLE** after create (proposed) | — |
| `exam_id`, `school_id`, `id` | **IMMUTABLE** | — |
| `status` | Only via Open/Close/Cancel commands | Not via Update |

### 12.3 `UpdateExamEnrollment` — proposed mutable set

| Field | Mutable? |
|-------|----------|
| `status` | YES — allowed transitions Matrix C only |
| `seat_number` | YES — while not Withdrawn |
| `exam_session_id`, `enrollment_id`, `school_id`, `id` | **IMMUTABLE** |

```text
P7.1-B-003 — DESIGN BLOCKER until mutable-field tables human-approved
```

---

## 13. Matrix E — Idempotency Audit

### 13.1 P7-D5 (Master Lock)

```text
REQUIRED for externally exposed mutating Grade + Exam Administration commands
LOCKED as policy — NOT fully enforced on grades today (optional key)
```

### 13.2 Platform reality — **PROVEN**

| Item | Fact |
|------|------|
| Table | `audit.idempotency_keys` PK `(key, command_name)` |
| Grade pattern | Optional key; `find` before work; `store` **after** `UnitOfWork` commit |
| Attendance Cancel | Key **required**; `store` **after** transaction |
| Desired model (prompt) | validate + mutate + persist idempotency + outbox **same BEGIN/COMMIT** |
| Same-txn idempotency | **PROVEN ABSENT** in current handlers |

### 13.3 Scope / conflict semantics (Design Lock + Attendance pattern)

| Concern | Required for 7.1 |
|---------|------------------|
| Entry | `X-Idempotency-Key` header — **REQUIRED** on all 7.1 mutating APIs |
| Identity | `(key, command_name)` |
| School | Include `school_id` in cached payload; mismatch → conflict |
| Replay | Same key + same payload → cached result |
| Conflict | Same key + different payload → fail closed |
| Outbox | Stage inside UoW with mutation |
| Idempotency persist | **Must be inside same UoW** for new Exam Admin commands |

```text
P7.1-B-004 — IMPLEMENTATION PREREQUISITE
Exam Administration handlers MUST persist idempotency inside UnitOfWork.
Do not silently redesign global IdempotencyStore without impact analysis;
scoped handler pattern or transactional store use is required before 7.1 ship.
```

---

## 14. Matrix F — Concurrency Audit

| Race | Expected | DB guarantee | App guarantee today | Tests |
|------|----------|--------------|---------------------|-------|
| Duplicate CreateExamEnrollment | One wins / one conflict | UNIQUE `(session, enrollment)` **PROVEN** | **ABSENT** writers | **ABSENT** |
| Two CancelExam | One Cancelled; other fail closed | Need `UPDATE … WHERE status IN (1,2,3) RETURNING` | **ABSENT** | **ABSENT** |
| CancelExam vs CreateExamSession | Cancel wins or create fails if parent cancelled | App + parent status check | **ABSENT** | **ABSENT** |
| Open vs Cancel session | Predicate updates; one succeeds | Status CHECK only | **ABSENT** | **ABSENT** |
| Close vs Cancel | Same | Status CHECK | **ABSENT** | **ABSENT** |
| CancelExamEnrollment vs EnterGrade | Enter blocked if seat inactive **after** withdraw; race needs txn ordering | Partial unique current grade | WriteGuard **PROVEN** for inactive seat **after** state visible | Grade concurrency only |
| CancelExamEnrollment vs FinalizeGrade | Must not leave inconsistent current grade + withdrawn without policy | — | **BLOCKER** until DR-002 | **ABSENT** |
| Correct/Finalize grade races | Residual MEDIUM (Design Lock) | Current unique | Handlers exist | Partial |

**Required mechanism for 7.1 (design — not implemented):**

```text
atomic UPDATE … WHERE id = ? AND status = ? 
+ UNIQUE constraints
+ UnitOfWork transactions
+ fail closed on 0-row updates
(+ optional row lock SELECT FOR UPDATE of parent exam when cascading CancelExam)
```

```text
P7.1-B-005 — REQUIRED BEFORE IMPLEMENTATION — concurrency strategy + tests unproven
```

---

## 15. Matrix H — School / RLS Audit

| Aggregate | App school check | FORCE RLS | Cross-school expect |
|-----------|------------------|-----------|---------------------|
| Exam | Required in future handlers | **PROVEN** | FAIL CLOSED |
| ExamSession | Required | **PROVEN** | FAIL CLOSED |
| ExamEnrollment | Required | **PROVEN** | FAIL CLOSED |
| Student / Enrollment | Via composites + future guards | Enrollment RLS elsewhere | FAIL CLOSED |
| StudentGrade | GradePolicy + SchoolContext **PROVEN** | **PROVEN** | FAIL CLOSED |

Policies on exam foundation: USING `school_id = app.current_school_id` — sessions/exams/enrollments **no WITH CHECK** (grades have WITH CHECK). Residual: defense-in-depth still requires handler school binding (Design Lock).

Grade controller uses `SchoolContext::requireId()` — **PROVEN**. Application handlers receive `schoolId` on command — **PROVEN**.

---

## 16. Matrix I — Academic-Year Integrity

| Link | DB-enforced? | Class |
|------|--------------|-------|
| Exam.academic_year_id | YES FK | **PROVEN** |
| Session → exam school composite | YES | **PROVEN** |
| Session.academic_year_id | Column absent | **PROVEN ABSENT** |
| Seat.academic_year_id | Column absent | **PROVEN ABSENT** |
| Grade ↔ enrollment year composite | YES | **PROVEN** |
| Grade year ↔ exam year FK | NO direct | **PROVEN ABSENT** |

```text
GAP (documented): year consistency for seat creation depends on Application
asserting enrollment.academic_year_id == exam.academic_year_id.
Not auto-fixed in this gate (no DDL).
```

```text
P7.1-B-006 — MEDIUM — academic-year graph integrity partly application-only
```

Does **not** alone equal Cancel blockers, but must be in handler preconditions for CreateExamEnrollment / CreateExamSession.

---

## 17. Partition Readiness (P7-D9)

| Fact | Value | Class |
|------|-------|-------|
| Partition parent | YES | **PROVEN** |
| DEFAULT partition | NO | **PROVEN ABSENT** (correct) |
| `academic_years` count | 0 | **PROVEN** |
| Partition children | 0 | **PROVEN** |
| Missing partition on grade write | `grades.partition_missing` | **PROVEN** |

**Phase 7.1 note:** Exam Administration commands do **not** insert grades. Partition ops are **precondition for grade writes**, not for CreateExam itself.

```text
Operational rule remains: year create → ensure student_grades_ay_{id} before grade writes.
Do NOT create DEFAULT partition.
```

```text
P7.1-B-007 — OPS CONDITION (grades) — not a CancelExam design blocker
```

---

## 18. Matrix D — Permission / Role Audit

| Permission | Classification |
|------------|----------------|
| `grades.view/create/correct/void/finalize` | **EXISTS** (**PROVEN**) |
| `exam.create` | **ABSENT** (**PROVEN ABSENT**) — Design Lock DOCUMENTED ONLY |
| `exam.update` | **ABSENT** |
| `exam.session.create/update/open/close` | **ABSENT** |
| `exam.enrollment.create/update/cancel` | **ABSENT** |

| Role | Status |
|------|--------|
| `grades_manager` / `grades_teacher` / `grades_viewer` | **PROVEN** |
| Examiner / Registrar / Exam Officer | **PROVEN ABSENT** — **must not invent** |

```text
P7.1-B-008 — IMPLEMENTATION BLOCKER — role → exam.* permission mapping unresolved
```

Human must approve either:

* map `exam.*` onto existing roles (e.g. extend `grades_manager`), **or**
* introduce new roles via Security Change Control  

before implementation.

---

## 19. Matrix G — Outbox / Audit Contract

| Command | Event required? | Proposed name | Payload must include | Txn with mutation |
|---------|-----------------|---------------|----------------------|-------------------|
| CreateExam | YES | `ExamCreated` | school_id, exam_id, academic_year_id, actor, occurred_at | YES |
| UpdateExam | YES | `ExamUpdated` | + changed fields / status | YES |
| CancelExam | YES | `ExamCancelled` | + previous status | YES |
| CreateExamSession | YES | `ExamSessionCreated` | session_id, exam_id, school_id | YES |
| UpdateExamSession | YES | `ExamSessionUpdated` | | YES |
| OpenExamSession | YES | `ExamSessionOpened` | previous→new status | YES |
| CloseExamSession | YES | `ExamSessionClosed` | | YES |
| CancelExamSession | YES | `ExamSessionCancelled` | | YES |
| CreateExamEnrollment | YES | `ExamEnrollmentCreated` | seat_id, session_id, enrollment_id | YES |
| UpdateExamEnrollment | YES | `ExamEnrollmentUpdated` | status transition | YES |
| CancelExamEnrollment | YES | `ExamEnrollmentCancelled` | | YES |

```text
Event names = DESIGN DECISION REQUIRED (convention proposed above; not established in code).
Security audit events also required per sensitive write baseline — naming TBD with Security.
```

```text
P7.1-B-009 — DESIGN DECISION REQUIRED — domain event catalog not established in code
```

Expected architecture (Design Lock):

```text
HTTP → Authz → Command → Handler → Domain guards → Repository → UoW{mutate + outbox[+idempotency]} → Commit
```

---

## 20. CQRS Boundary Audit

| Anti-pattern | Status for 7.1 |
|--------------|----------------|
| Generic Eloquent save from controller | **FORBIDDEN** |
| Business logic in controller | **FORBIDDEN** |
| Parallel God ExamService | **FORBIDDEN** (Feature Contract) |
| Expected path | Match Grade CQRS + Attendance Cancel patterns |

Exam admin Application layer **PROVEN ABSENT** — to be created only after human implementation authorization.

---

## 21. Hard-Delete Safety Audit

| Object | Hard delete allowed? | Evidence |
|--------|---------------------|----------|
| student_grades | **NO** | Trigger **PROVEN** |
| exams / sessions / enrollments | No DELETE API designed; Cancel = status | Design Lock forbids hard delete |
| Cascade DELETE FKs | RESTRICT on exam FKs | **PROVEN** migrations |

Any destructive cascade = **BLOCKER**. Cancel must be status transitions only.

---

## 22. Scope Boundary Audit

| Area | Phase 7.1 |
|------|-----------|
| Attendance | **OUT** — Phase 6 CLOSED |
| Results / GPA / Ranking / Transcript | **OUT** — P7-D2 blocked |
| Graduation / Certificates | **OUT** |
| Generic Assessment | **OUT** |
| Student/guardian grade reads | **OUT** — P7-D10 |
| Grade Enter/Correct/Void/Finalize redesign | **OUT** except interaction rules |
| UI | **OUT** |

No Design Conflict requiring Master Lock edit was detected for scope.

---

## 23. Blocker Register

| ID | Severity | Area | Evidence | Problem | Why it matters | Required decision | Approver | Blocks impl? |
|----|----------|------|----------|---------|----------------|-------------------|----------|--------------|
| **P7.1-B-001** | **CRITICAL** | CancelExam | Design Lock §9.3 | Cascade + grade policy unset | Can corrupt SSOT or orphan graph | Approve P7.1-DR-001 or alternate; update Design Lock via Change Control | Human | **YES** |
| **P7.1-B-002** | **CRITICAL** | CancelExamEnrollment | Design Lock KNOWN DESIGN RISK | Grade interaction unset | Silent void or stranded finalized grades | Approve P7.1-DR-002 or alternate | Human | **YES** |
| **P7.1-B-003** | **HIGH** | Update* mutability | Design Lock “narrow” only | Field allowlist unset | CRUD escape hatch | Approve §12 tables | Human | **YES** |
| **P7.1-B-004** | **HIGH** | Idempotency | Handlers post-commit store | Same-txn model unmet | Duplicate side effects on retry | 7.1 handlers in-UoW idempotency (scoped) | Human + Arch | **YES** |
| **P7.1-B-005** | **HIGH** | Concurrency | No admin writers/tests | Races undefined in code | Double cancel / open-close races | Predicate updates + tests plan | Human | **YES** |
| **P7.1-B-006** | **MEDIUM** | Academic year | No year on session/seat | App-only year match | Cross-year seating | Handler asserts; optional future DDL via DB change skill | Human | Condition |
| **P7.1-B-007** | **MEDIUM** | Partitions | 0 years / 0 parts | Ops for grades | Grade writes fail-closed | Ops year+partition runbook | Ops | Condition (grades) |
| **P7.1-B-008** | **CRITICAL** | AuthZ roles | Permissions ABSENT | Who may exam.* | SoD / security | Map roles without inventing Examiner | Human Security | **YES** |
| **P7.1-B-009** | **MEDIUM** | Outbox names | Events future-only | Catalog unset | Inconsistent consumers | Approve event name list §19 | Human | Condition |
| **P7.1-B-010** | **HIGH** | Exam → Completed | Design Lock sessions TBD | Complete exam while sessions open? | Incomplete lifecycle Matrix A | Lock CompleteExam/session precondition | Human | **YES** for Complete path |
| **P7.1-B-011** | **LOW** | Close Scheduled→Completed | Design Lock DEFER | Skip InProgress | Lifecycle hole | Keep forbidden in 7.1 | — | No if kept forbidden |

---

## 24. Implementation Preconditions Checklist

```text
[ ] CancelExam behavior locked (Master Lock Change Control after human approves DR-001)
[ ] CancelExamEnrollment behavior locked (DR-002 + Design Lock update)
[ ] Exam lifecycle locked (incl. Completed / sessions precondition — B-010)
[ ] Session lifecycle locked (Scheduled→Completed remains forbidden)
[ ] Enrollment lifecycle locked
[ ] Mutable fields locked (B-003)
[ ] Permissions proven/approved (create exam.* only after auth)
[ ] Role mapping resolved (B-008)
[ ] Idempotency transaction model resolved (B-004)
[ ] Concurrency strategy resolved (B-005)
[ ] Outbox contract resolved (B-009)
[ ] Audit contract resolved
[ ] School isolation proven (foundation YES; handlers N/A yet)
[ ] RLS + FORCE verified (foundation YES)
[ ] Academic-year integrity verified (B-006 residual)
[ ] Partition prerequisite verified (ops; grades)
[x] Grade SSOT protected (design)
[x] No hard delete (design + grades trigger)
[x] No Attendance scope leakage (this gate)
[x] No Results/GPA/Ranking/Transcript scope leakage (this gate)
```

**All unchecked critical items remain open → overall BLOCKED.**

---

## 25. Matrix K — Command Readiness

| Command | Classification |
|---------|----------------|
| CreateExam | **BLOCKED** (perms/roles/idempotency/events; mutability OK-ish) |
| UpdateExam | **BLOCKED** (mutable fields + Complete path + perms) |
| CancelExam | **BLOCKED** (B-001 CRITICAL) |
| CreateExamSession | **BLOCKED** (perms/idempotency/parent Completed rule) |
| UpdateExamSession | **BLOCKED** (mutable fields + perms) |
| OpenExamSession | **BLOCKED** (perms/concurrency/idempotency) |
| CloseExamSession | **BLOCKED** (perms/concurrency/idempotency) |
| CancelExamSession | **BLOCKED** (perms; grade direct OK; cascade from CancelExam separate) |
| CreateExamEnrollment | **BLOCKED** (year assert + perms + idempotency) |
| UpdateExamEnrollment | **BLOCKED** (perms + transition matrix OK-ish) |
| CancelExamEnrollment | **BLOCKED** (B-002 CRITICAL) |

None are **READY FOR IMPLEMENTATION**.

---

## 26. Final Readiness Verdict

```text
BLOCKED
```

**Not used:**

```text
READY WITH CONDITIONS — HUMAN IMPLEMENTATION AUTHORIZATION REQUIRED
READY FOR HUMAN IMPLEMENTATION AUTHORIZATION
IMPLEMENTATION AUTHORIZED
```

**Unblock path (human-driven):**

1. Approve **P7.1-DR-001** / **P7.1-DR-002** (or alternates).  
2. Issue **Design Change Request** → update Master Design Lock Change Control for Cancel + Complete-exam session policy + mutable fields.  
3. Resolve **role → `exam.*` permission** mapping (B-008).  
4. Accept **in-UoW idempotency** requirement for 7.1 writers (B-004).  
5. Accept concurrency predicate strategy + mandatory tests (B-005).  
6. Only then may a **separate** human prompt authorize Phase 7.1 implementation.

**No Design Conflict** requiring silent Master Lock edit was found — gaps are deferred decisions, not contradictions.

---

## 27. Explicit Human Authorization Statement

> **PHASE 7.1 IS NOT IMPLEMENTED.**
>
> This document is a readiness/design gate only.
>
> No implementation authorization is granted by this document.
>
> If and only if the human reviewer explicitly approves implementation, the next authorized step may be a separate implementation prompt for Phase 7.1.
>
> Until then:
>
> **NO CODE / MIGRATION / DDL / RLS / ROUTE / PERMISSION IMPLEMENTATION IS AUTHORIZED.**

---

## 28. Stop Condition

```text
PHASE 7.1 READINESS / DESIGN AUTHORIZATION GATE COMPLETE

STATUS: BLOCKED
IMPLEMENTATION AUTHORIZATION: NOT GRANTED

NO CODE CHANGED
NO MIGRATIONS
NO DDL
NO RLS CHANGES
NO PERMISSIONS ADDED
NO ROUTES ADDED
MASTER DESIGN LOCK FILES NOT MODIFIED
ATTENDANCE NOT REOPENED
P7-D2 NOT RESOLVED
PHASE 7.2 NOT STARTED

NEXT STEP: HUMAN REVIEW OF BLOCKERS P7.1-B-001 … B-011
         + APPROVAL/REJECTION OF P7.1-DR-001 / P7.1-DR-002
```

---

## Evidence Index

| Source | Use |
|--------|------|
| Live PG `exams.*` catalog (2026-09-11) | RLS, FKs, partitions, columns |
| Migrations `150000`/`150100`/`160000`/`160100` | DDL/RLS |
| `app/Application/Exams/**` | Grade-only CQRS; admin absent |
| `EnterStudentGradeHandler` + Attendance `CancelAttendanceSessionHandler` | Idempotency post-commit |
| `audit.idempotency_keys` | Store schema |
| `config/security.php` | grades.* only |
| `StudentGradeWriteGuard` | Cancelled session / inactive seat |
| `02-MASTER-PHASE-7-DESIGN-LOCK.md` | P7-D4/D5; Cancel TBD |
| Domain `ExamStatus` / `ExamSessionStatus` / `ExamEnrollmentStatus` | Lifecycle vocabulary |
