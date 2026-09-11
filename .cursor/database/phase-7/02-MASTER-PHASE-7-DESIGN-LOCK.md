# MASTER PHASE 7 — ASSESSMENT / EXAMS / GRADES

# PHASE 7 — DESIGN LOCK

**Document Type:** AUTHORITATIVE DESIGN FREEZE  
**Date:** 2026-09-11  
**Status:** DESIGN LOCKED  
**Authorization:** DESIGN ONLY — **NO IMPLEMENTATION AUTHORIZATION**  
**Upstream:** [`01-PHASE-7-ASSESSMENT-EXAMS-GRADES-READINESS-AUDIT.md`](./01-PHASE-7-ASSESSMENT-EXAMS-GRADES-READINESS-AUDIT.md) — READY FOR DESIGN LOCK  
**Attendance boundary:** Master Phase 6 Final Gate — **PASS WITH CONDITIONS** (CLOSED — not reopened)

```text
NO CODE · NO MIGRATIONS · NO DDL · NO RLS · NO PERMISSIONS · NO ROUTES
NO LEGACY DELETE · NO PHASE 7.1+ · NO RESULTS/GPA/RANKING/TRANSCRIPT IMPLEMENTATION
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 0. Purpose

This document is the **single authoritative Design Lock** for Master Phase 7.

It converts the completed Readiness / Discovery / Design Audit into an immutable contract so future implementation cannot:

* rebuild Phase 3A / 3B / 3B.1 foundations
* create a parallel grade ledger
* blur Exam and Generic Assessment
* absorb Graduation or Certificates
* reopen Attendance
* silently implement Results / GPA / Ranking / Transcript
* bypass RLS, CQRS, or historical integrity
* introduce unsafe DEFAULT partitions
* invent undocumented permissions
* advance sub-phases without human authorization

```text
AUDIT → RECONCILE → DECIDE → FREEZE → GATE → STOP
```

---

## 1. Absolute Boundary

### 1.1 This Design Lock freezes design only

Allowed outcomes of this document:

* official scope freeze (P7-D1…P7-D10)
* lifecycle / SSOT / RLS / CQRS / authorization contracts
* non-goals
* future sub-phase structure + gates
* conflict resolution table

Forbidden until separate human implementation authorization:

* PHP / Laravel / React changes
* migrations / DDL / indexes / partitions / triggers
* RLS / FORCE RLS / permissions / routes
* commands / handlers / DTOs / repositories / domain entities / events
* tests for unimplemented features
* destructive cleanup or legacy deletion

### 1.2 Critical reconciliation principle

```text
Phase 7 does NOT rebuild the existing Exam/Grade foundation.
Phase 7 extends and hardens it under Option B.
```

There remains **exactly one** authoritative Student Grade ledger:

```text
exams.student_grades
```

No parallel Grade ledger may be created unless a future explicitly approved architecture supersedes this Design Lock.

---

## 2. Source-of-Truth Priority

When sources conflict, apply this order:

1. Master SIS Database Specification / Master Phase ordering (`docs/database/SIS-DATABASE-PHASE-0-GATE.md`)
2. Approved human decisions explicitly recorded for this project
3. This Design Lock (after approval) supersedes prior Phase 7 ambiguity
4. Phase 7 Readiness Audit (`01-PHASE-7-…`)
5. Phase 3A / 3B / 3B.1 implementation gates
6. Phase 6 Attendance Final Gate (boundary only)
7. Current PostgreSQL live metadata
8. Current Laravel implementation
9. Existing tests
10. Blueprint / design documents (`database-blueprint.md`, Phase 3C.* docs)
11. Legacy / stale documentation

Conflicts are identified below and resolved by explicit Design Lock decisions — never silently.

---

## 3. Evidence Inputs Inspected

| Source | Classification | Use |
|--------|----------------|-----|
| Phase 7 Readiness Audit | **PROVEN** (read-only audit) | Primary reconciliation input |
| Phase 0 Master phase list | **PROVEN** | Phase 7 vs Phase 18 numbering |
| Phase 3A Gate — PASS | **PROVEN** | Exam foundation retained |
| Phase 3B Gate — PASS WITH CONDITIONS | **PROVEN** | Grade ledger retained |
| Phase 3B.1 Gate — PASS WITH CONDITIONS | **PROVEN** | Grade CQRS retained |
| Phase 3C.0–3C.6 design docs | **DOCUMENTED ONLY** | Results/GPA/Ranking/Transcript architecture |
| Phase 6 Attendance Final Gate | **PROVEN** | Attendance CLOSED |
| `docs/sis/exams/FEATURE-CONTRACT.md` | **PROVEN** | Exams BC allow/forbid |
| Migrations `150000/150100/160000/160100` | **PROVEN** | Schema + RLS |
| Live PG catalog (audit 2026-09-11) | **PROVEN** | RLS/FORCE; 0 year partitions; empty `results` |
| Domain enums + WriteGuard + Rules | **PROVEN** | Lifecycles + invariants |
| Grade handlers / Policy / routes | **PROVEN** | Write/read surface |
| Application Exam admin writers | **PROVEN ABSENT** | Gap → P7-D4 |
| Blueprint rank columns vs 3C.5 | **CONFLICTING** | Resolved by P7-D8 |

---

## 4. Master Decision Table (P7-D1 … P7-D10)

| Decision | Decision ID | Decision | Status | Rationale | Future Phase |
|----------|-------------|----------|--------|-----------|--------------|
| Scope | **P7-D1** | **OPTION B** — reconcile foundation + Exam Administration CQRS; Results/GPA/Ranking/Transcript separately governed | **LOCKED** | Rebuild would duplicate SSOT; admin writers are the major functional gap; Results semantics unresolved | Phase 7.1–7.3 core; 7.4–7.5 conditional |
| Results / GPA / Ranking / Transcript ownership | **P7-D2** | **DEFERRED — OWNERSHIP DECISION REQUIRED**; design-only; not Grade SSOT; implementation prohibited | **DEFERRED** | 3C design ≠ Master Phase 7 or 18 assignment; insufficient to permanently assign | Blocked until ownership lock |
| Graduation boundary | **P7-D3** | Separate bounded context — OUTSIDE Phase 7 | **LOCKED** | Graduation already implemented; not exam-score SSOT | Graduation track |
| Exam Administration CQRS | **P7-D4** | In scope for Phase 7.1–7.2; command surface frozen below; not implemented now | **LOCKED** | Tables exist; writers absent | Phase 7.1 / 7.2 |
| Idempotency | **P7-D5** | **REQUIRED** for externally exposed mutating Grade + Exam Administration commands (future enforcement) | **LOCKED** (policy); **NOT IMPLEMENTED** | Replay safety; align with Attendance Cancel pattern; current keys optional | Phase 7.1 / 7.3 |
| Letter / pass-fail / derived values | **P7-D6** | Letter/GPA/credits/weight aggregation **NOT** authoritative grade facts; pass-fail prefer derived; vocab gaps deferred | **LOCKED** / partial **DEFERRED** | Matches 3B row + 3C HD open items | 7.3 / Results track |
| Enrollment date-window | **P7-D7** | **DEFERRED DESIGN DECISION** — no DB date-window CHECKs today; WriteGuard has activity checks only | **DEFERRED** | Insufficient product policy | Phase 7.3 (if approved) |
| Ranking blueprint conflict | **P7-D8** | Authoritative = Phase 3C.5 / DL-005 snapshot architecture; blueprint `rank_*` on term/annual = **STALE** | **LOCKED** | Doc conflict resolved without DDL | Results / Ranking track |
| Partition operations | **P7-D9** | Year LIST partitions; **no DEFAULT**; fail-closed before writes; year creation must ensure partition | **LOCKED** | Live 0 years / 0 children; safer than Attendance DEFAULT | Ops + 7.3 |
| Grade read model | **P7-D10** | Manager/teacher/viewer paths exist; student/guardian reads **DEFERRED**; write≠read authority | **LOCKED** / partial **DEFERRED** | No student/guardian APIs found | Phase 7.6 (conditional) |

---

## 5. P7-D1 — Official Scope (OPTION B)

### 5.1 Decision

```text
P7-D1 = OPTION B
```

> Phase 7 is an incremental extension of the existing Exam/Grade foundation and includes Exam Administration CQRS, while Results / GPA / Ranking / Transcript remain separately governed design tracks until their ownership is explicitly resolved (P7-D2).

### 5.2 Why Option B (not A/C/D)

| Option | Meaning | Verdict |
|--------|---------|---------|
| A | Close/reconcile 3A+3B.1 only | **REJECTED** — leaves exam admin gap (P7-F03) unaddressed |
| **B** | + Exam Administration CQRS | **SELECTED** |
| C | + Results/GPA/Transcript DDL+app | **REJECTED for automatic inclusion** — ownership deferred (P7-D2); HD policies open |
| D | + Generic Assessment taxonomy | **REJECTED** — not justified (P7-F06) |

### 5.3 Phase 7 includes (authorized for future design/implementation planning only)

1. Existing Exam/Grade foundation reconciliation (retain 3A/3B/3B.1)
2. Exam Administration CQRS
3. Exam / session / seat lifecycle command definitions
4. Exam Administration authorization
5. Exam Administration idempotency / concurrency rules
6. Grade lifecycle hardening **only where this Design Lock explicitly requires it**
7. Grade security / RLS contract freeze
8. Grade historical / versioning contract freeze
9. Integration boundaries with Enrollment
10. Explicit disposition of Results / GPA / Ranking / Transcript (P7-D2)
11. Explicit disposition of Generic Assessment
12. Explicit separation from Graduation
13. Explicit separation from Attendance
14. Partition operational contract
15. Reporting / read-model **boundaries** (not reporting implementation)

### 5.4 Phase 7 does NOT automatically include

* Generic Assessment engine / rubrics / competency / continuous assessment
* Results tables unless separately authorized after P7-D2
* GPA / Ranking / Transcript engines unless separately authorized after P7-D2
* Graduation / Certificates / Attendance changes
* UI redesign
* Reporting / Analytics implementation
* Destructive migrations or legacy deletion

### 5.5 Conflict check vs approved human decisions

| Check | Result |
|-------|--------|
| Human-approved 3B.1 forbids Phase 3C without separate approval | **RESPECTED** — Option B does not authorize Results implementation |
| Feature Contract forbids Phase 3C coupling in Exams BC | **RESPECTED** |
| No repository human decision found mandating Option C/D for Master Phase 7 | **NONE FOUND** — Option B proceeds |

**Evidence class:** **DESIGN LOCKED** (P7-D1) on **PROVEN** foundation + **PROVEN ABSENT** admin writers.

---

## 6. Foundations Retained (Non-Rebuild)

### 6.1 Phase 3A — Exam foundation — RETAIN

```text
exams.exam_types          — global catalog (no school RLS)
exams.exams               — school-scoped; RLS + FORCE
exams.exam_sessions       — school-scoped; RLS + FORCE
exams.exam_enrollments    — school-scoped; RLS + FORCE
```

**Status:** COMPLETE (DB) — Gate PASS  
**Evidence:** **PROVEN**

### 6.2 Phase 3B — Grade ledger — RETAIN

```text
exams.student_grades      — partitioned LIST(academic_year_id); RLS + FORCE
```

**Status:** COMPLETE (DB) — Gate PASS WITH CONDITIONS  
**Evidence:** **PROVEN**

### 6.3 Phase 3B.1 — Grade CQRS — RETAIN

```text
EnterStudentGrade
CorrectStudentGrade
VoidStudentGrade
FinalizeStudentGrade
GetStudentGrade
GetCurrentGradeForExamEnrollment
ListGradesForExamSession
ListGradesForEnrollment
```

**Status:** COMPLETE (app for grade ops) — Gate PASS WITH CONDITIONS  
**Evidence:** **PROVEN**

### 6.4 Parallel ledger prohibition

```text
FORBIDDEN: second authoritative student grade store
FORBIDDEN: writing scores onto exam_enrollments as SSOT
FORBIDDEN: results.* becoming writable grade SSOT
```

Authoritative current grade:

```text
exams.student_grades
WHERE is_current = true
```

subject to partial UNIQUE `(exam_enrollment_id, academic_year_id) WHERE is_current`.

---

## 7. P7-D2 — Results / GPA / Ranking / Transcript Ownership

### 7.1 Provisional governance (frozen)

| Artifact | Physical status | Semantic status |
|----------|-----------------|-----------------|
| `results.term_results` | **NOT IMPLEMENTED** | DESIGN ONLY — NOT Grade SSOT |
| `results.annual_results` | **NOT IMPLEMENTED** | DESIGN ONLY — NOT Grade SSOT |
| GPA | **NOT IMPLEMENTED** | DESIGN ONLY — DERIVED FROM AUTHORITATIVE ACADEMIC FACTS |
| Ranking | **NOT IMPLEMENTED** | DESIGN ONLY — NO PARALLEL GRADE LEDGER |
| Transcript | **NOT IMPLEMENTED** | DESIGN ONLY — READ/DERIVED DOCUMENT MODEL ONLY |

### 7.2 Ownership decision

```text
P7-D2 = DEFERRED — OWNERSHIP DECISION REQUIRED
IMPLEMENTATION: PROHIBITED until ownership is explicitly locked
```

**Why not permanently Phase 7:** Master Phase 7 = Assessment / Exams / Grades; Results aggregation is a distinct semantic layer with open HD-01…HD-18 policies (3C.0).

**Why not permanently Phase 18:** Master Phase 18 = Reporting / Analytics **MVs**. Versioned Results / GPA / Ranking / Transcript (3C DL-001…DL-016) are **academic projection/document models**, not interchangeable with reporting MVs.

**Authoritative design docs until ownership lock:** Phase 3C.0–3C.6 + DL-001…DL-016 (design-only).  
**Authoritative grade SSOT remains:** `exams.student_grades` (DL-001).

### 7.3 Absolute SSOT rule (locked now)

No future `term_results` / `annual_results` / GPA / ranking / transcript implementation may become a second authoritative Grade ledger.

Traceability minimum:

```text
student_grades
+ enrollment
+ academic structure
+ approved grading policy
```

### 7.4 Conditional sub-phases

```text
PHASE 7.4 / 7.5 remain CONDITIONAL and BLOCKED
until P7-D2 ownership is resolved by human Design Change.
```

**Evidence class:** **DESIGN LOCKED** (deferral) / underlying architecture **DOCUMENTED ONLY**.

---

## 8. P7-D3 — Graduation Boundary

```text
Graduation remains a separate bounded context.
```

**MUST NOT be absorbed into Phase 7:**

```text
CompletionOutcome
RequirementEvaluation
GraduationApproval
GraduationAward
AwardVersion
PublishAward
RevokeAward
```

Existing Graduation implementation remains valid. **Do not refactor as part of Phase 7.**

Certificates (Phase 4.1) remain separate. Do not merge Certificates / Graduation / Assessment / Exam / Grades into one generic academic table.

**Evidence class:** **PROVEN** (Graduation BC exists) + **DESIGN LOCKED**.

---

## 9. P7-D4 — Exam Administration CQRS (Design Only)

### 9.1 Gap statement

Exam foundation tables exist; **Application writers for create/update/lifecycle transitions were not found**.  
**Evidence:** **PROVEN ABSENT**.

### 9.2 Command surface — evaluated

| Command | Verdict | Purpose |
|---------|---------|---------|
| `CreateExam` | **INCLUDE** | Create school/year/term-scoped exam |
| `UpdateExam` | **INCLUDE** | Update non-terminal exam metadata / schedule |
| `CancelExam` | **INCLUDE** | Terminal cancel when no forbidden side effects |
| `CreateExamSession` | **INCLUDE** | Create subject session under exam |
| `UpdateExamSession` | **INCLUDE** | Update session metadata before terminal |
| `OpenExamSession` | **INCLUDE** | Transition toward / to `InProgress` (see §10) |
| `CloseExamSession` | **INCLUDE** | Transition to `Completed` |
| `CancelExamSession` | **INCLUDE** | Transition to `Cancelled` |
| `CreateExamEnrollment` | **INCLUDE** | Seat student enrollment into session |
| `UpdateExamEnrollment` | **INCLUDE (narrow)** | Status transitions only — not free-form CRUD |
| `CancelExamEnrollment` | **INCLUDE** | Seat → `Withdrawn` |

Do **not** add commands merely for CRUD symmetry. Bulk import / publish queues / multi-step approval workflows = **DEFER** until product requirement proven.

### 9.3 Per-command design contract (frozen; not implemented)

Common defaults for all included commands:

| Field | Contract |
|-------|----------|
| Bounded context | `Exams` |
| School boundary | Required `school_id` via SchoolContext; mismatch fail-closed |
| Academic-year boundary | Required `academic_year_id` on exam graph |
| Authorization | Future `exam.*` / `exam.session.*` / `exam.enrollment.*` permissions (§23) |
| Idempotency | **REQUIRED** when externally exposed (P7-D5) |
| Concurrency | Optimistic/unique constraints; no silent overwrite of terminal state |
| Transaction | Handler-owned UnitOfWork; outbox staged in same transaction |
| Audit | Security audit + domain outbox event |
| RLS | Writer still subject to FORCE RLS |

#### CreateExam

| Field | Value |
|-------|-------|
| Aggregate | Exam |
| Actor | Authorized exam admin (role mapping TBD — do not invent Examiner) |
| Preconditions | Valid school, academic year, term, exam_type; dates coherent |
| Forbidden | Creating into another school; bypassing SchoolContext |
| Audit / outbox | `ExamCreated` (future) |
| Idempotency | Same key + same payload → same result; same key + different payload → conflict |

#### UpdateExam

| Field | Value |
|-------|-------|
| Aggregate | Exam |
| Preconditions | Exam exists in school; status not terminal (`Completed`/`Cancelled`) unless explicitly allowed field set (none today) |
| Forbidden transitions | Silent revive from `Cancelled`; cross-school update |
| Audit / outbox | `ExamUpdated` (future) |

#### CancelExam

| Field | Value |
|-------|-------|
| Aggregate | Exam |
| Preconditions | Not already terminal; define session/seat side-effect policy before implement |
| Forbidden | Hard delete; cancel after grades exist without explicit supersession policy (**DEFER detail to 7.1 design**) |
| Side effects | Must be explicit in implementation design — cascade cancel sessions/seats **or** reject if children active |
| Audit / outbox | `ExamCancelled` (future) |

#### CreateExamSession / UpdateExamSession

| Field | Value |
|-------|-------|
| Aggregate | ExamSession (under Exam) |
| Preconditions | Parent exam not Cancelled; subject/school/year consistency via composite FKs |
| Forbidden | Orphan session; client-supplied denorm identity that disagrees with parent |
| Audit / outbox | `ExamSessionCreated` / `ExamSessionUpdated` (future) |

#### OpenExamSession

| Field | Value |
|-------|-------|
| Meaning | Legal transition into `ExamSessionStatus::InProgress` (value 2) |
| FROM → TO | `Scheduled (1) → InProgress (2)` (**PROVEN** enum; app transition absent) |
| Forbidden | Open from `Completed`/`Cancelled`; open when parent exam Cancelled |
| Audit / outbox | `ExamSessionOpened` (future) |

#### CloseExamSession

| Field | Value |
|-------|-------|
| Meaning | Legal transition into `ExamSessionStatus::Completed` (value 3) |
| FROM → TO | `InProgress (2) → Completed (3)` (primary); evaluate `Scheduled → Completed` as **DEFER** unless product requires skip |
| Forbidden | Close from `Cancelled`; reopen after Completed without separate approved command |
| Audit / outbox | `ExamSessionClosed` (future) |

#### CancelExamSession

| Field | Value |
|-------|-------|
| FROM → TO | `Scheduled|InProgress → Cancelled (4)` |
| Forbidden | Cancel after Completed without explicit policy; hard delete |
| Grade interaction | Enter already rejects cancelled session — preserve |
| Audit / outbox | `ExamSessionCancelled` (future) |

#### CreateExamEnrollment

| Field | Value |
|-------|-------|
| Aggregate | ExamEnrollment (seat) |
| Preconditions | Active academic enrollment; session not Cancelled; unique `(exam_session_id, enrollment_id)` |
| Forbidden | Cross-school seat; duplicate seat |
| Initial status | `Registered (1)` unless policy says otherwise |
| Audit / outbox | `ExamEnrollmentCreated` (future) |

#### UpdateExamEnrollment (narrow)

| Field | Value |
|-------|-------|
| Allowed | Status transitions among Registered → Confirmed → Present / Absent / Withdrawn per §10 |
| Forbidden | Changing enrollment_id / session_id / school_id identity; free-form patch of denorm keys |
| Audit / outbox | `ExamEnrollmentUpdated` (future) |

#### CancelExamEnrollment

| Field | Value |
|-------|-------|
| FROM → TO | Active seat → `Withdrawn (5)` |
| Preconditions | Define interaction with current grade (**KNOWN DESIGN RISK** — must fail-closed or require void first) |
| Forbidden | Hard delete seat history |
| Audit / outbox | `ExamEnrollmentCancelled` (future) |

---

## 10. Exam / Session / Seat Lifecycles (Authoritative Vocabulary)

**Do not invent** Draft→Published/Active→Closed/Archived. Freeze **existing** enums.

### 10.1 Exam (`ExamStatus`) — **PROVEN**

```text
Draft (1)
→ Scheduled (2)
→ InProgress (3)
→ Completed (4)     [terminal]
→ Cancelled (5)     [terminal]
```

| FROM | TO | Actor | Permission (future) | Preconditions | Side effects | Audit | Outbox | Idempotency |
|------|----|-------|---------------------|---------------|--------------|-------|--------|-------------|
| Draft | Scheduled | Exam admin | `exam.update` | Valid dates/type | None required | Yes | ExamScheduled (future) | Required |
| Scheduled | InProgress | Exam admin | `exam.update` | Not cancelled | Optional session opens | Yes | Future | Required |
| InProgress | Completed | Exam admin | `exam.update` | Sessions policy TBD | None auto-grade | Yes | Future | Required |
| Draft\|Scheduled\|InProgress | Cancelled | Exam admin | `exam.update` | Child policy TBD | May block if grades | Yes | Future | Required |
| Completed | * | — | — | — | **FORBIDDEN** without Design Change | — | — | — |
| Cancelled | * | — | — | — | **FORBIDDEN** revive | — | — | — |

`isTerminal()` = Completed | Cancelled — **PROVEN**.

### 10.2 Exam Session (`ExamSessionStatus`) — **PROVEN**

```text
Scheduled (1)
→ InProgress (2)
→ Completed (3)     [terminal]
→ Cancelled (4)     [terminal]
```

| FROM | TO | Command mapping | Permission (future) | Forbidden notes |
|------|----|-----------------|---------------------|-----------------|
| Scheduled | InProgress | `OpenExamSession` | `exam.session.open` | Not from Completed/Cancelled |
| InProgress | Completed | `CloseExamSession` | `exam.session.close` | No silent reopen |
| Scheduled\|InProgress | Cancelled | `CancelExamSession` | `exam.session.update` (or dedicated) | Grade enter already blocked on Cancelled |
| Completed\|Cancelled | non-self | — | — | **FORBIDDEN** |

### 10.3 Exam Enrollment / Seat (`ExamEnrollmentStatus`) — **PROVEN**

```text
Registered (1)
→ Confirmed (2)
→ Present (3)
→ Absent (4)        [inactive seat]
→ Withdrawn (5)     [inactive seat]
```

`isActiveSeat()` = Registered | Confirmed | Present — **PROVEN**.

| FROM | TO | Actor | Permission (future) | Preconditions | Side effects | Notes |
|------|----|-------|---------------------|---------------|--------------|-------|
| Registered | Confirmed | Exam admin | `exam.enrollment.update` | Seat exists; session not Cancelled | None | |
| Confirmed | Present | Exam admin / invigilator mapping TBD | `exam.enrollment.update` | Session open policy TBD | None | Role mapping **DEFERRED** |
| Registered\|Confirmed\|Present | Absent | Exam admin | `exam.enrollment.update` | — | Seat inactive → grade enter blocked | Distinct from grade `is_absent` |
| *active* | Withdrawn | Exam admin | `exam.enrollment.cancel` | Grade interaction policy TBD | May require void first | |
| Absent\|Withdrawn | active | — | — | — | **FORBIDDEN** without Design Change | |

**Seat Absent ≠ Grade `is_absent`.** Seat status is participation; grade absence is score semantics. Both may coexist but are not the same fact.

Application transition commands for exam/session/seat: **MISSING** today — **PARTIALLY PROVEN** (enums + CHECKs only).

---

## 11. Grade Lifecycle (Frozen)

Preserve existing lifecycle unless a future Design Change proves defect:

```text
Draft (1)
→ Entered (2)
→ Submitted (3)
→ Finalized (4)

Void → Voided (5) + is_current = false

Correct = previous grade VOIDED + new current grade (Entered)
```

### 11.1 Current implementation facts — **PROVEN**

| Fact | Evidence |
|------|----------|
| Enter writes `Entered` (not Draft) | Enter handler |
| Submitted reserved; no Submit command | 3B.1 gate — deferred workflow |
| Finalize allows Draft \| Entered \| Submitted → Finalized | `StudentGradeRules::assertCanFinalize` |
| Correct = void prior + insert new current | Correct handler |
| No hard delete | BEFORE DELETE trigger |
| Current uniqueness | partial UNIQUE WHERE `is_current` |

### 11.2 Preserved invariants — **DESIGN LOCKED**

* no hard delete
* historical retention of voided rows
* correction lineage via `correction_of_grade_id`
* current-row uniqueness
* void lineage / `is_current=false` when Voided
* actor attribution (`entered_by`, timestamps, `finalized_at`)
* correction/void reasons required
* transactional outbox for grade events

### 11.3 Submitted workflow

```text
STATUS: RESERVED ENUM — WORKFLOW NOT IMPLEMENTED
CLASSIFICATION: DEFERRED (hardening may complete in 7.3 if authorized)
```

Do not remove enum value. Do not pretend Submit API exists.

---

## 12. Score Semantics (Frozen)

### 12.1 Authoritative columns — **PROVEN**

```text
score       NUMERIC(5,2)   NULL when absent
max_score   NUMERIC(5,2)   NOT NULL — historical snapshot at enter
is_absent   boolean
```

**Naming note:** Master prompt wording `raw_score` is **not** the live column. Authoritative name is `score`. Do not rename without Design Change + migration authorization.

### 12.2 Absent semantics — **PROVEN**

```text
is_absent = true  ⇒  score IS NULL
is_absent = false ⇒  score numeric in [0, max_score]
max_score > 0
```

### 12.3 max_score snapshot — **DESIGN LOCKED**

`max_score` is a **historical snapshot** (from session at enter). Client override of `max_score` / denorm identity is **FORBIDDEN** (Feature Contract).

### 12.4 Not authoritative stored grade facts

| Concept | Status |
|---------|--------|
| Letter grade | **DERIVED / FUTURE** — do not store as SSOT unless grading policy + Design Change |
| GPA | **DERIVED / FUTURE** — must NOT store on `student_grades` |
| Weighted / normalized score | **DERIVED / FUTURE** |
| Credits | Must NOT be silently introduced on `student_grades` |
| Pass/fail | Prefer derivation from `score`, `max_score`, session `pass_grade`/`max_grade`, and approved policy — unless historical snapshot explicitly required later |

`exam_types.weight_percentage` must **not** automatically become an aggregation engine (P7-D6).

---

## 13. Grade Vocabulary Gap

| Outcome | Current model | Classification | Notes |
|---------|---------------|----------------|-------|
| Excused | Absent | **DEFERRED** | HD-05 open in 3C.0; not a grade status |
| Withheld | Absent | **DEFERRED** | No enum/column |
| Incomplete | Absent | **DEFERRED** | HD-05 / HD-07 territory |
| Withdrawn | Seat status only (`ExamEnrollmentStatus::Withdrawn`) | **NOT APPLICABLE** as grade status today | Do not conflate with grade Voided |

```text
Do NOT silently add Excused / Withheld / Incomplete / Withdrawn grade statuses.
Any future addition requires a separate approved Design Change.
```

**Evidence class:** **PROVEN ABSENT** as grade outcomes + **DESIGN LOCKED** deferral.

---

## 14. P7-D5 — Idempotency

### 14.1 Current state — **PROVEN**

```text
X-Idempotency-Key = optional on Enter / Correct / Void / Finalize
```

Handlers consult IdempotencyStore only when key present. Store persistence is **post-commit** (3B.1 residual risk).

### 14.2 Design Lock decision

```text
P7-D5 = REQUIRED for externally exposed mutating Grade and Exam Administration commands
STATUS: LOCKED as future policy — NOT IMPLEMENTED now
```

No repository human policy was found mandating permanent optional idempotency for grades; Attendance Cancel already requires keys — alignment is justified.

### 14.3 Frozen semantics (for future enforcement)

| Topic | Rule |
|-------|------|
| Why | Safe retries; prevent duplicate enters/corrections under network retry |
| Request identity | `X-Idempotency-Key` + actor + school + command type |
| Replay | Same key + same payload → return original successful result |
| Conflict | Same key + different payload → conflict (4xx), no execute |
| Transaction | Prefer record inside UnitOfWork before commit (hardening target); document residual if post-commit remains |
| Retention | Follow platform IdempotencyStore policy (no silent infinite retain without ops policy) |
| Concurrency | Unique-current constraint remains authoritative even with keys |

---

## 15. P7-D7 — Enrollment Date-Window Eligibility

### 15.1 Current evidence — **PROVEN**

WriteGuard checks:

* active exam seat
* session/exam not Cancelled
* academic enrollment active (`status` + `effective_to`)
* score semantics / current-grade uniqueness

**No** enrollment start/end date-window CHECKs in DB or WriteGuard for seating/grading.

### 15.2 Decision

```text
P7-D7 = DEFERRED DESIGN DECISION
```

| Question | Interim rule |
|----------|--------------|
| Seat outside enrollment dates? | **UNKNOWN** — defer |
| Grade enter outside enrollment dates? | **UNKNOWN** — defer |
| Historical correction exempt? | **LIKELY YES** once date-window exists — must confirm |
| Enforcement layer | Prefer Domain/Application rules first; DB CHECKs only if stable and non-contradictory with corrections |

**Implementation prohibited** until Design Change answers the above.

---

## 16. P7-D8 — Ranking Blueprint Conflict

| Artifact | Classification | Authority |
|----------|----------------|-----------|
| Phase 3C.5 Ranking Architecture + DL-005 | **AUTHORITATIVE design** for Ranking | Ranking = versioned snapshot projection — **not** academic truth |
| Blueprint `results.term_results.rank_in_section` | **STALE sketch** | Must NOT be copied as Ranking SSOT |
| Blueprint `annual_results.rank_in_section` / `rank_in_class` | **STALE sketch** | Same |
| Blueprint other `rank_in_*` sketches | **STALE / example** | Same |

```text
Future implementation must NOT treat blueprint rank_* columns as Ranking SSOT.
Authoritative ranking design = Phase 3C.5 snapshot model (when ownership unlocked).
No DDL in this Design Lock.
```

**Evidence class:** **CONFLICTING** docs → **DESIGN LOCKED** resolution.

---

## 17. P7-D9 — Partition Policy

### 17.1 Freeze

```text
exams.student_grades = academic-year LIST-partitioned Grade ledger
NO DEFAULT PARTITION (rejected unless future approved architecture changes this)
```

### 17.2 Operational rule (locked; not implemented here)

> Academic-year creation MUST ensure the corresponding `student_grades_ay_{id}` partition exists before grade writes are permitted.

Fail-closed via `StudentGradesPartitionManager` / `grades.partition_missing` — **PROVEN**.

### 17.3 Live condition (ops readiness, not design defect)

```text
academic.academic_years = 0
student_grades partitions = 0
```

Treat as **operational readiness condition**, not justification to add DEFAULT partition.

### 17.4 Future partition creation must be

```text
deterministic · observable · transactionally safe · fail-closed
```

**REJECTED:** automatic unsafe DEFAULT partition to “make writes work.”

---

## 18. P7-D10 — Grade Read Model Boundaries

### 18.1 Existing — **PROVEN**

| Audience | Access today |
|----------|--------------|
| Manager (`grades_manager`) | view + create + correct + void + finalize |
| Teacher (`grades_teacher`) | view + create |
| Viewer (`grades_viewer`) | view |
| Student | **NOT FOUND** |
| Guardian | **NOT FOUND** |

Routes: grade get + session/enrollment list + current-by-exam-enrollment.

### 18.2 Freeze

| Concern | Rule |
|---------|------|
| Write authority | Existing `grades.create|correct|void|finalize` — unchanged by this Lock |
| Read authority | Separate from write; hiding UI ≠ security |
| School scope | SchoolContext + Policy + RLS |
| Student ownership | Future student read must bind enrollment/student ownership server-side |
| Guardian relationship | Future guardian read must bind verified guardian–student relationship |

```text
Student/guardian grade read APIs = DEFERRED (Phase 7.6 conditional)
Do not add APIs in this Design Lock.
```

---

## 19. Generic Assessment Decision

```text
Assessment / AssessmentType / AssessmentComponent / Rubric /
Competency / ContinuousAssessment
= NOT PART OF CURRENT PHASE 7 IMPLEMENTATION
```

| Reason | Detail |
|--------|--------|
| Evidence | **PROVEN ABSENT** — no tables/modules |
| Model | Exam-centric chain is the actual SIS model |
| Risk | Generic Assessment would invent parallel abstraction |

```text
Exam ≠ Generic Assessment
```

unless a future domain model + human approval proves otherwise.

**Classification:** **NOT JUSTIFIED** / **OUTSIDE SCOPE**.

---

## 20. RLS / School Isolation Contract

| Table | RLS | FORCE | Notes |
|-------|-----|-------|-------|
| `exams.exam_types` | false | false | Global catalog — RLS not required while truly global |
| `exams.exams` | true | true | School-scoped |
| `exams.exam_sessions` | true | true | School-scoped |
| `exams.exam_enrollments` | true | true | School-scoped |
| `exams.student_grades` | true | true | + WITH CHECK |

Defense in depth — **DESIGN LOCKED**:

```text
HTTP authorization
+ CQRS handler authorization
+ domain invariants
+ school ownership checks
+ PostgreSQL RLS
+ FORCE RLS
```

No single layer is sufficient.

---

## 21. Authorization / SoD

### 21.1 Existing grade permissions — **PROVEN** — RETAIN

```text
grades.view
grades.create
grades.correct
grades.void
grades.finalize
```

Roles present: `grades_manager`, `grades_teacher`, `grades_viewer`.

### 21.2 Future Exam Administration permissions — DESIGN ONLY (do not add now)

```text
exam.create
exam.update
exam.session.create
exam.session.update
exam.session.open
exam.session.close
exam.enrollment.create
exam.enrollment.update
exam.enrollment.cancel
```

### 21.3 Roles

Do **not** invent Examiner / Registrar / AcademicAdmin roles without evidence.

```text
Role mapping for exam admin = DEFERRED PRODUCT DECISION
May extend existing security roles or add named roles after approval.
```

Teacher vs Manager SoD for grades remains as implemented (teacher cannot correct/void/finalize) — **PROVEN**.

---

## 22. Audit / Outbox

### 22.1 Existing grade events — **PROVEN** — RETAIN

```text
StudentGradeEntered
StudentGradeCorrected
StudentGradeVoided
StudentGradeFinalized
```

### 22.2 Future Exam Administration events (design only)

```text
ExamCreated / ExamUpdated / ExamCancelled
ExamSessionCreated / ExamSessionUpdated / ExamSessionOpened /
ExamSessionClosed / ExamSessionCancelled
ExamEnrollmentCreated / ExamEnrollmentUpdated / ExamEnrollmentCancelled
```

### 22.3 Event envelope (frozen shape)

Each event MUST carry (or map to outbox fields providing):

```text
actor
school_id
aggregate_id
aggregate_type
event_type
occurred_at
correlation_id
causation_id
payload version
```

Business write + outbox staging remain **transactionally consistent** inside UnitOfWork.

---

## 23. Concurrency

| Risk | Protection | Status |
|------|------------|--------|
| Duplicate grade entry | Partial UNIQUE current + WriteGuard + tests | **PROVEN** |
| Concurrent correction | Current-row void + insert; residual races | **KNOWN RESIDUAL RISK** (MEDIUM) |
| Concurrent finalization | Status transition on current row | **KNOWN RESIDUAL RISK** — monitor |
| Concurrent void | Current-only void rule | **PARTIALLY PROVEN** |
| Session seat allocation | UNIQUE `(exam_session_id, enrollment_id)` | **PROVEN** (DB); app writers absent |
| Lifecycle transition races | No app writers yet | **KNOWN RESIDUAL RISK** for 7.1/7.2 |
| Unique-current constraint | Must remain authoritative | **DESIGN LOCKED** — do not weaken |

Document residual races; do not silently “fix” in this Lock.

---

## 24. Normalization — Intentional Denormalization

Frozen rationale for grade denormalized fields:

```text
student_id
subject_id
session_id
enrollment_id
school_id
academic_year_id
```

These remain denormalized where required for:

* RLS
* partitioning
* historical integrity
* query performance

```text
Denormalization is INTENTIONAL, not accidental.
Client-supplied denorm identity must not be trusted over server-resolved context.
```

---

## 25. Reporting Boundary

### Operational SSOT

```text
exams.*
exams.student_grades
```

### Derived / read models (not write authorities)

```text
results.*
GPA
ranking
transcript
reporting views
analytics
```

No reporting artifact may silently become a write authority for scores.

---

## 26. Attendance Boundary

Phase 6 Attendance is **CLOSED**.

```text
NO Phase 7 modification to Attendance lifecycle
NO Phase 7 modification to Attendance CQRS
NO Phase 7 modification to Attendance RLS
NO Phase 7 modification to Attendance legacy quarantine
```

Attendance may be referenced only as an upstream fact. Do not reopen Reopen / Cancel / UI / legacy writer deletion / partition decisions under Phase 7.

---

## 27. Master Phase Number Reconciliation

| Old / adjacent artifact | Status | Design Lock disposition |
|-------------------------|--------|-------------------------|
| Old Phase 3A Exam foundation | COMPLETE | **RETAIN** — foundation of Master Phase 7 |
| Old Phase 3B `student_grades` | COMPLETE | **RETAIN** — Grade SSOT |
| Old Phase 3B.1 Grade CQRS/API | COMPLETE | **RETAIN** — write/read path |
| Old Phase 3C.1–3C.6 Results/GPA/Ranking/Transcript design | DOCUMENTED ONLY | **DEFER** — P7-D2 ownership; do not treat as implemented |
| Old Graduation 3C.12–3C.19 | IMPLEMENTED | **OUTSIDE SCOPE** — keep separate BC |
| Phase 4.1 Certificates | IMPLEMENTED (adjacent) | **OUTSIDE SCOPE** |
| Master Phase 7 | THIS LOCK | **MERGE INTO PHASE 7** = Option B scope only |
| Master Phase 18 Reporting/Analytics MVs | Future | **NOT automatic home** for Results engines; distinct from 3C Results BC |
| Generic Assessment | ABSENT | **DEFER** / outside current Phase 7 |
| Attendance Phase 6 | CLOSED | **OUTSIDE SCOPE** — do not modify |
| Blueprint rank_* on results | STALE | **SUPERSEDED** by 3C.5 for ranking meaning |

Historical documentation is **not deleted**. This Design Lock is the reconciliation layer.

---

## 28. Findings Reconciliation (P7-F01…P7-F11)

| ID | Finding | Severity | Evidence | Impact | Decision | Implementation consequence |
|----|---------|----------|----------|--------|----------|----------------------------|
| P7-F01 | Strong Exams/Grades foundation exists | — | 3A/3B/3B.1 + live | Foundation | **RETAIN** | No rebuild |
| P7-F02 | Master ≠ old 3A/3B/3C labels | MEDIUM | Phase 0 + docs | Confusion | **RECONCILE** via §27 | Docs only |
| P7-F03 | No exam administration CQRS | HIGH | App search | Product gap | **P7-D4 INCLUDE** | Phase 7.1–7.2 after auth |
| P7-F04 | `results.*` empty | HIGH | Live PG | No results DDL | **P7-D2 DEFER** | No tables until ownership |
| P7-F05 | GPA/ranking/transcript design-only | HIGH | 3C docs | Scope creep risk | **P7-D2 DEFER** | 7.4–7.5 blocked |
| P7-F06 | Generic Assessment absent | MEDIUM | Search | Abstraction risk | **OUT OF SCOPE** | Do not invent |
| P7-F07 | Zero year partitions live | MEDIUM | Live PG | Ops fail-closed | **P7-D9** | Ops ensure partitions |
| P7-F08 | Optional grade idempotency | LOW–MED | Handlers | Replay risk | **P7-D5 REQUIRED (future)** | Enforce in 7.1/7.3 |
| P7-F09 | Blueprint vs 3C ranking conflict | MEDIUM | Blueprint + 3C.5 | Wrong DDL risk | **P7-D8** | Ignore stale rank_* |
| P7-F10 | No scale EXPLAIN evidence | MEDIUM | Audit | Perf claims | **ADVISORY** | Measure later |
| P7-F11 | Attendance must stay untouched | — | Phase 6 gate | Boundary | **LOCKED** | No Attendance edits |

---

## 29. PHASE 7 NON-GOALS

Explicitly out of Phase 7 unless a future Design Change + human authorization says otherwise:

* no rebuild of Phase 3A
* no rebuild of Phase 3B
* no rebuild of Phase 3B.1
* no Graduation implementation / refactor
* no Certificates implementation / merge
* no Attendance changes
* no Generic Assessment implementation
* no UI redesign
* no Reporting implementation unless separately approved
* no Analytics implementation
* no destructive migration
* no legacy deletion
* no automatic schema cleanup
* no automatic ranking implementation
* no automatic GPA implementation
* no automatic transcript implementation
* no Results DDL until P7-D2 ownership resolved
* no DEFAULT partition on `student_grades`
* no second grade ledger
* no invented Examiner/Registrar roles without evidence
* no silent addition of Excused/Withheld/Incomplete grade statuses

---

## 30. Implementation Sub-Phase Structure (Future Only)

```text
PHASE 7.0 — DESIGN LOCK                    ← THIS DOCUMENT
PHASE 7.1 — EXAM ADMINISTRATION CQRS
PHASE 7.2 — EXAM SESSION / ENROLLMENT LIFECYCLE
PHASE 7.3 — GRADE HARDENING / POLICY COMPLETION
PHASE 7.4 — RESULTS / ACADEMIC AGGREGATION     [CONDITIONAL — requires P7-D2]
PHASE 7.5 — GPA / RANKING / TRANSCRIPT         [CONDITIONAL — requires P7-D2]
PHASE 7.6 — READ MODELS / ACCESS               [CONDITIONAL — student/guardian]
PHASE 7.7 — FINAL PHASE 7 DATABASE GATE
```

```text
7.4 / 7.5 / 7.6 are NOT automatically authorized by this Design Lock.
```

---

## 31. Future Implementation Gates

For every future sub-phase:

```text
DESIGN LOCK (this doc + any sub-phase design lock)
→ HUMAN IMPLEMENTATION AUTHORIZATION
→ IMPLEMENTATION
→ TESTING
→ DATABASE VERIFICATION
→ SECURITY/RLS VERIFICATION
→ ARCHITECTURE VERIFICATION
→ FINAL SUB-PHASE GATE
→ HUMAN REVIEW
→ NEXT AUTHORIZATION
```

```text
FORBIDDEN: Design Lock → automatic implementation
```

---

## 32. Database Safety Prohibitions

NEVER automatically:

```text
DROP TABLE
DROP COLUMN
DROP CONSTRAINT
DROP INDEX
DISABLE RLS
DISABLE FORCE RLS
REMOVE FK
CREATE DEFAULT PARTITION
DELETE grades
DELETE enrollment history
DELETE audit history
```

Any destructive action requires explicit human authorization.

---

## 33. Change Control

After approval of this Design Lock, any change to:

* scope
* lifecycle
* SSOT
* RLS
* permissions
* idempotency
* partition policy
* Results ownership
* GPA / Ranking / Transcript
* Graduation boundary
* Attendance boundary
* Generic Assessment inclusion

requires:

```text
Design Change Request
→ Impact Analysis
→ Human Approval
→ Updated Design Lock
```

No silent scope changes.

---

## 34. Design Lock Acceptance Criteria

| Criterion | Status |
|-----------|--------|
| Phase 3A foundation explicitly retained | **YES** |
| Phase 3B foundation explicitly retained | **YES** |
| Phase 3B.1 CQRS explicitly retained | **YES** |
| `exams.student_grades` remains Grade SSOT | **YES** |
| Grade lifecycle frozen | **YES** |
| Correction/void lineage frozen | **YES** |
| RLS/Force RLS contract frozen | **YES** |
| Exam Administration scope frozen | **YES** |
| Authorization boundary frozen | **YES** |
| Idempotency policy frozen (future required) | **YES** |
| Results ownership frozen or deferred | **DEFERRED (explicit)** |
| GPA ownership frozen or deferred | **DEFERRED (explicit)** |
| Ranking ownership frozen or deferred | **DEFERRED (explicit)** |
| Transcript ownership frozen or deferred | **DEFERRED (explicit)** |
| Generic Assessment explicitly classified | **YES — OUT OF SCOPE** |
| Graduation boundary frozen | **YES** |
| Attendance boundary frozen | **YES** |
| Partition policy frozen | **YES** |
| Date-window policy frozen/deferred | **DEFERRED (explicit)** |
| Ranking blueprint conflict resolved/documented | **YES** |
| Grade read-model boundary frozen/deferred | **YES** |
| No implementation occurred | **YES** |
| No DB changes occurred | **YES** |
| No code changes occurred | **YES** |
| No destructive action occurred | **YES** |

---

## 35. Open Risks (Documented, Not Fixed)

| Risk | Class |
|------|-------|
| P7-D2 ownership unresolved → 7.4/7.5 blocked | **GOVERNANCE** |
| CancelExam / CancelExamEnrollment vs existing grades | **KNOWN DESIGN RISK** |
| Concurrent correct/finalize residual races | **KNOWN RESIDUAL RISK** |
| Idempotency post-commit today | **KNOWN RESIDUAL RISK** |
| Zero partitions until years exist | **OPS CONDITION** |
| Submitted workflow incomplete | **DEFERRED** |
| Date-window eligibility undefined | **DEFERRED** |
| Student/guardian read absent | **DEFERRED** |
| Unmeasured scale (no EXPLAIN at 45K) | **ADVISORY** |

---

## 36. Final Statements

```text
MASTER PHASE 7 DESIGN LOCK: COMPLETE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

Phase 7 Design Lock is complete, but **no implementation authorization has been granted**.  
No Phase 7.1+ implementation may begin until explicit human approval is received.

```text
NEXT AUTHORIZED STEP: HUMAN REVIEW ONLY
```

---

## Evidence Index

| Path | Role |
|------|------|
| `.cursor/database/phase-7/01-PHASE-7-ASSESSMENT-EXAMS-GRADES-READINESS-AUDIT.md` | Readiness input |
| `.cursor/database/phase-attendance/22-MASTER-PHASE-6-ATTENDANCE-FINAL-DATABASE-GATE.md` | Attendance closed |
| `docs/database/SIS-DATABASE-PHASE-0-GATE.md` | Master phase list |
| `docs/database/SIS-DATABASE-PHASE-3A-GATE.md` | 3A PASS |
| `docs/database/SIS-DATABASE-PHASE-3B-STUDENT-GRADES-GATE.md` | 3B |
| `docs/database/SIS-DATABASE-PHASE-3B.1-GRADE-APPLICATION-HARDENING-GATE.md` | 3B.1 |
| `docs/database/SIS-DATABASE-PHASE-3C.0-DECISION-GATE.md` | DL/HD registers |
| `docs/database/SIS-DATABASE-PHASE-3C.5-RANKING-ARCHITECTURE.md` | Ranking authority |
| `docs/sis/exams/FEATURE-CONTRACT.md` | Exams BC contract |
| `database/migrations/2026_09_10_150000_*` … `160100_*` | DDL/RLS |
| `app/Domain/Exams/**` | Lifecycles/rules |
| `app/Application/Exams/**` | Grade CQRS only |
| `.cursor/architecture/database-blueprint.md` | Blueprint (incl. stale ranks) |
