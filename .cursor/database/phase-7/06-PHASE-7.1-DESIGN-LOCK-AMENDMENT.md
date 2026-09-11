# MASTER PHASE 7 — PHASE 7.1 — DESIGN LOCK AMENDMENT

**Document Type:** HUMAN-DECISION-DRIVEN DESIGN LOCK AMENDMENT TRACE  
**Date:** 2026-09-11  
**Phase:** MASTER PHASE 7 — Assessment / Exams / Grades  
**Subphase:** PHASE 7.1 — Exam Administration CQRS  
**Authorization:** DESIGN LOCK AMENDMENT ONLY  
**Implementation Authorization:** **NOT GRANTED**  
**Migration / DDL / Code / Route / API Authorization:** **NOT GRANTED**

```text
DESIGN CHANGE REQUEST
→ HUMAN DECISION          ← executed by this amendment
→ MASTER DESIGN LOCK AMENDMENT
→ DESIGN LOCK AMENDMENT GATE
→ PHASE 7.1 RE-READINESS AUDIT
→ HUMAN IMPLEMENTATION AUTHORIZATION
→ IMPLEMENTATION

THIS DOCUMENT ≠ IMPLEMENTATION AUTHORIZATION
```

---

## 1. Authorization

| Item | Status |
|------|--------|
| Design Lock amendment | **AUTHORIZED** by human decision prompt |
| Master Lock rewrite of unrelated sections | **NOT AUTHORIZED** |
| Implementation | **NOT GRANTED** |
| Migrations / DDL / indexes / RLS | **NOT GRANTED** |
| Permissions creation / role assignment | **NOT GRANTED** |
| Routes / controllers / handlers / events (code) | **NOT GRANTED** |
| Grade CQRS / Attendance / Graduation / Certificates changes | **NOT GRANTED** |

**Permitted outputs of this stage:**

1. Approved Design Lock amendment (applied to `02-MASTER-PHASE-7-DESIGN-LOCK.md`)
2. This amendment decision trace
3. Amendment gate (`07-…`)
4. Internal consistency validation

---

## 2. DCR Reference

| Document | Role |
|----------|------|
| [`05-PHASE-7.1-DESIGN-CHANGE-REQUEST.md`](./05-PHASE-7.1-DESIGN-CHANGE-REQUEST.md) | Source Design Change Request (proposals) |
| [`04-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-READINESS-GATE.md`](./04-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-READINESS-GATE.md) | Prior readiness gate — **BLOCKED** (design decisions pending) |
| [`02-MASTER-PHASE-7-DESIGN-LOCK.md`](./02-MASTER-PHASE-7-DESIGN-LOCK.md) | Authoritative Master Design Lock — **AMENDED** |
| [`07-PHASE-7.1-DESIGN-LOCK-AMENDMENT-GATE.md`](./07-PHASE-7.1-DESIGN-LOCK-AMENDMENT-GATE.md) | Amendment verification gate |

---

## 3. Human Decision Record

| Decision ID | Topic | Human verdict | Applied to Master Lock? |
|-------------|--------|---------------|-------------------------|
| **DR-001** | CancelExam | **APPROVED** | **YES** |
| **DR-002** | CancelExamEnrollment | **APPROVED** | **YES** |
| **DR-003** | Update mutability allowlists | **APPROVED** | **YES** |
| **DR-004** | Exam completion predicates | **APPROVED** | **YES** |
| **DR-005** | Security / role mapping | **MODIFY — DO NOT AUTO-MAP** | **YES** (vocabulary locked; mapping **UNRESOLVED**) |
| **DR-005a** | Dedicated `exam.cancel` | **APPROVED** | **YES** (vocabulary only) |
| **DR-006** | Event catalog | **APPROVED** | **YES** |
| Event identity ≠ idempotency | Distinction | **LOCKED** | **YES** |
| Idempotency contract | Phase 7.1 mutators | **LOCKED** | **YES** |
| Concurrency contract + race tests | Acceptance criteria | **LOCKED** (not implemented) | **YES** |
| Academic-year contract | Retain / clarify | **RETAINED** | **YES** |
| Grade partition (P7-D9) | Retain | **UNCHANGED** | **YES** (explicit retain) |
| Grade SSOT (P7-D1/D6) | Retain | **UNCHANGED** | **YES** (explicit retain) |
| Hard-delete policy | Retain | **UNCHANGED** | **YES** (explicit retain) |

---

## 4. Every Approved Decision (Locked Content)

### 4.1 DR-001 — CancelExam — APPROVED

1. `CancelExam` MUST NOT directly mutate `exams.student_grades`.
2. If ANY CURRENT grade exists for any `exam_enrollment` belonging to the target exam → **FAIL CLOSED**.
3. CURRENT grade = `student_grades.is_current = true` for any enrollment belonging to the target exam.
4. Historical non-current VOIDED grades do NOT independently block cancellation.
5. If ANY `ExamSession` is `Completed` → **FAIL CLOSED**.
6. Exam already `Completed` or `Cancelled` → **FAIL CLOSED**.
7. If cancellation is permitted (atomic): Exam → Cancelled; Scheduled sessions → Cancelled; InProgress sessions → Cancelled; active enrollments → Withdrawn.
8. No grade automatically voided / corrected / deleted.
9. No exam / session / enrollment row deleted.
10. Entire business mutation MUST be atomic.
11. Outbox and idempotency persistence MUST participate in the same transaction once implemented.
12. Historical VOIDED grade rows remain immutable history.

**Clarification locked:** `CorrectStudentGrade` creates a new CURRENT grade and does **not** make an exam cancellable. `VoidStudentGrade` may remove the CURRENT-grade condition. Cancellation is eligible only when the CURRENT-grade guard and all other guards pass.

### 4.2 DR-002 — CancelExamEnrollment — APPROVED

1. MUST NOT mutate `student_grades`.
2. CURRENT grade for target `exam_enrollment_id` → **FAIL CLOSED**.
3. Actor must use Grade CQRS if a grade operation is required.
4. Historical non-current VOIDED grades remain immutable history.
5. If no CURRENT grade → ExamEnrollment → Withdrawn.
6. No hard delete; no automatic grade void / correct / finalize; no direct grade mutation from Exam Administration.

### 4.3 DR-003 — Update Mutability — APPROVED

Update commands are **NOT** generic CRUD PATCH operations.

**UpdateExam**

* Mutable Draft/Scheduled only: `name`, `start_date`, `end_date`
* Mutable Draft only: `exam_type_id`, `term_id`
* `status`: lifecycle transition only
* Immutable: `id`, `school_id`, `academic_year_id`, `created_at`

**UpdateExamSession**

* Mutable while Scheduled only: `session_date`, `start_time`, `end_time`, `room_id`, `max_grade`, `pass_grade`
* Immutable: `id`, `exam_id`, `subject_id`, `school_id`
* `status` MUST NOT change through generic Update
* If ANY CURRENT grade exists for the session → `max_grade` / `pass_grade` immutable
* Historical non-current grades do not independently add immutability

**UpdateExamEnrollment**

* Allowlisted lifecycle status transitions only
* `seat_number` only where lifecycle permits; immutable once Present **OR** CURRENT grade exists
* Identity / relationship columns immutable
* Future implementation MUST use explicit transition matrices and allowlists

### 4.4 DR-004 — Exam Completion — APPROVED

`InProgress → Completed` ONLY when:

1. At least one ExamSession exists
2. No session is Scheduled
3. No session is InProgress
4. Every non-cancelled session is Completed
5. Cancelled sessions remain Cancelled
6. No automatic session closing
7. Unmet predicate → **FAIL CLOSED**

**Zero-session rule:** Exam with zero sessions MUST NOT transition to Completed.

**Do not** add `CompleteExam` as part of this amendment. Future dedicated command requires a separate DCR.

### 4.5 DR-005 — Security / Role Mapping — MODIFY — DO NOT AUTO-MAP

Permission vocabulary **approved as design vocabulary**:

```text
exam.create / exam.update / exam.cancel
exam.session.create / exam.session.update / exam.session.open / exam.session.close
exam.enrollment.create / exam.enrollment.update / exam.enrollment.cancel
```

**NO ROLE MAPPING IS APPROVED.** Do not invent Examiner / Registrar / Exam Officer; do not auto-assign to `grades_manager`, `attendance_manager`, `enrollment_manager`, teachers, or viewers. Existing roles = evidence only. Role mapping requires a separate explicit human security decision.

### 4.6 DR-005a — Dedicated Cancel Permission — APPROVED

`exam.cancel` is the dedicated permission for `CancelExam`. Do not infer from `exam.update` / `grades.*` / `attendance.*` / `enrollment.*`. Role assignment remains unresolved.

### 4.7 DR-006 — Event Catalog — APPROVED

```text
ExamCreated, ExamUpdated, ExamCancelled,
ExamSessionCreated, ExamSessionUpdated, ExamSessionOpened,
ExamSessionClosed, ExamSessionCancelled,
ExamEnrollmentCreated, ExamEnrollmentUpdated, ExamEnrollmentCancelled
```

Design-level names only. Do **not** create event classes.

### 4.8 Event Identity ≠ Idempotency Identity — LOCKED

* Idempotency identity = `X-Idempotency-Key` (command replay)
* Event identity = outbox event/message identity
* Not interchangeable; may be correlated

### 4.9 Idempotency Contract — LOCKED (design)

For externally exposed mutating Phase 7.1 commands:

1. Key REQUIRED
2. Same key + same command + same payload → replay
3. Same key + different payload → FAIL CLOSED
4. School mismatch → FAIL CLOSED
5. Mutation + outbox + idempotency → SAME TRANSACTION / SAME COMMIT
6. Do not redesign `audit.idempotency_keys`

### 4.10 Concurrency Contract — LOCKED (acceptance criteria only)

Enforce atomic transitions, state predicates, unique constraints, UnitOfW boundaries, zero-row = FAIL CLOSED.

Mandatory race tests before Phase 7.1 final gate (not implemented now):

1. CancelExam vs CreateExamSession
2. CancelExam vs CreateExamEnrollment
3. CancelExam vs OpenExamSession
4. CancelExam vs CloseExamSession
5. CancelExamEnrollment vs EnterStudentGrade
6. CancelExamEnrollment vs FinalizeStudentGrade

### 4.11 Academic Year / Partition / SSOT / Hard Delete — RETAINED

* Academic year: Exam owns; session inherits; enrollment year must match exam — FAIL CLOSED on mismatch; no denormalized year columns via this amendment
* P7-D9 unchanged (LIST by year; no DEFAULT; missing partition FAIL CLOSED for grade writes)
* Grade SSOT = `exams.student_grades` (P7-D1/P7-D6)
* No hard delete for exams / sessions / enrollments / grades

---

## 5. Every Unresolved Decision

| Item | Status | Blocks |
|------|--------|--------|
| **DR-005 role-to-permission mapping** | **UNRESOLVED** — separate human security decision required | Secure Phase 7.1 AuthZ implementation |
| P7-D2 Results/GPA/Ranking/Transcript ownership | **DEFERRED** (unchanged) | Phase 7.4 / 7.5 |
| P7-D7 enrollment date-window | **DEFERRED** (unchanged) | Related seating/grade policy |
| Student/guardian grade reads (P7-D10) | **DEFERRED** (unchanged) | Phase 7.6 |
| Submitted grade workflow | **DEFERRED** (unchanged) | Grade hardening |
| Dedicated `CompleteExam` command | **NOT ADDED** — requires future DCR | N/A until requested |
| Phase 7.1 implementation authorization | **NOT GRANTED** | All code/DDL/routes |

---

## 6. Exact Master Lock Sections Changed

| Section | Change summary |
|---------|----------------|
| Document header / status banner | Marked AMENDED; linked DCR / amendment / gate; clarified no role mapping / no 7.1 implementation |
| §9.3 UpdateExam | Locked mutable/immutable allowlists (DR-003) |
| §9.3 CancelExam | Replaced deferred cascade/grade TBD with DR-001 policy |
| §9.3 CreateExamSession / UpdateExamSession | Academic-year inherit; Update allowlists + CURRENT-grade immutability (DR-003) |
| §9.3 CreateExamEnrollment | Academic-year match FAIL CLOSED |
| §9.3 UpdateExamEnrollment | Narrow allowlists + seat_number immutability (DR-003) |
| §9.3 CancelExamEnrollment | Replaced KNOWN DESIGN RISK with DR-002 |
| §9.4–§9.8 (new) | Amendment decision table; hard-delete; academic-year; P7-D9 retain; SSOT retain |
| §10.1 Exam lifecycle table | Completed predicates (DR-004); Cancel uses `exam.cancel` + DR-001 |
| §10.3 Seat Withdrawn row | DR-002 preconditions |
| §14.3–§14.5 | School mismatch; no idempotency table redesign; Phase 7.1 contract; event≠idempotency |
| §21.2–§21.3 | Added `exam.cancel`; DR-005 no auto-map / unresolved |
| §22.2–§22.4 | Locked event catalog; event≠idempotency |
| §23.1–§23.2 | Concurrency contract + mandatory race tests |
| §35 Open Risks | Removed Cancel* as open design risk; added DR-005 unresolved; idempotency same-COMMIT note |
| §36 Final Statements + Evidence Index | Amendment lifecycle; linked 05/06/07 |

---

## 7. Exact Master Lock Sections Intentionally Unchanged

| Section / Decision | Intentional disposition |
|--------------------|-------------------------|
| **P7-D1** Option B scope | Unchanged |
| **P7-D2** Results/GPA/Ranking/Transcript ownership | Remains DEFERRED |
| **P7-D3** Graduation boundary | Unchanged |
| **P7-D8** Ranking blueprint conflict | Unchanged |
| **P7-D9** Partition policy | Explicitly retained / unchanged |
| **P7-D10** Grade read-model boundary | Unchanged (student/guardian still deferred) |
| §6 Foundations retained (3A/3B/3B.1) | Unchanged |
| §8 Graduation | Unchanged |
| §11–§13 Grade lifecycle / score / vocab | Unchanged (except cross-refs via Cancel policies) |
| §15 P7-D7 date-window | Remains DEFERRED |
| §16 P7-D8 | Unchanged |
| §17 P7-D9 body | Unchanged (reinforced in §9.7) |
| §18 P7-D10 | Unchanged |
| §19 Generic Assessment | Unchanged |
| §20 RLS contract | Unchanged |
| §21.1 Existing grade permissions | Unchanged |
| §24–§29 Normalization / Reporting / Attendance / Phase reconciliation / Findings / Non-goals | Unchanged in substance |
| §30–§34 Sub-phases / gates / DB safety / change control / acceptance baseline | Unchanged (acceptance still design-only) |
| Attendance / Graduation / Certificates boundaries | Unchanged — not reopened |

---

## 8. Security Decision Boundary

```text
APPROVED BY THIS AMENDMENT:
  - exam.* permission vocabulary (design-only)
  - dedicated exam.cancel vocabulary (DR-005a)

NOT APPROVED BY THIS AMENDMENT:
  - creating permissions in config/security.php
  - assigning permissions to any role
  - inventing Examiner / Registrar / Exam Officer
  - auto-mapping exam.* → grades_manager (or any existing role)
```

Role-to-permission mapping remains an **explicit separate human security decision**.

---

## 9. Implementation Boundary

```text
THIS AMENDMENT DOES NOT AUTHORIZE:

- CancelExam / CancelExamEnrollment / Update* / Exam completion implementation
- migrations / DDL / indexes / constraints / RLS
- CQRS handlers / services / commands / queries
- routes / controllers
- event classes
- outbox / idempotency implementation changes
- Grade CQRS modification
- Attendance / Graduation / Certificates modification
- permission or role creation / assignment
- concurrency race test implementation (criteria only)
- Phase 7.2
```

---

## 10. Explicit Stop Conditions

STOP and do **not** proceed if asked to:

1. Implement Phase 7.1 without a separate human implementation authorization
2. Create migrations / DDL / RLS for this amendment
3. Create or assign `exam.*` permissions / roles
4. Treat amendment PASS as implementation-ready
5. Auto-map roles under DR-005
6. Add `CompleteExam` without a new DCR
7. Mutate grades from Exam Administration cancel paths
8. Reopen Attendance / Graduation / Certificates / P7-D2
9. Treat event identity as idempotency identity
10. Skip Phase 7.1 re-readiness audit after this amendment gate

```text
STOP AFTER AMENDMENT + GATE.

Wait for:
  DESIGN LOCK AMENDMENT GATE verdict
  → PHASE 7.1 RE-READINESS AUDIT
  → explicit HUMAN IMPLEMENTATION AUTHORIZATION
```

---

## 11. Consistency Notes

* CURRENT-grade definition is identical for DR-001 (exam graph) and DR-002 (single enrollment): `is_current = true`.
* `CorrectStudentGrade` does not satisfy CancelExam CURRENT-grade clearance (creates new CURRENT).
* `exam.cancel` ≠ `exam.update` for CancelExam authorization vocabulary.
* Completed-session guard on CancelExam is compatible with DR-004 Completed predicates (Completed sessions are terminal session state).
* Zero-session exams cannot Complete; they may still Cancel subject to DR-001 (no Completed sessions; no CURRENT grades).
* P7-D9 remains grade-write scoped — not expanded to block CreateExam without grade writes.
* Event catalog names match prior Master Lock design names; identity clarification is additive.

---

## 12. Final Amendment Statement

```text
STATUS: MASTER DESIGN LOCK AMENDED FOR PHASE 7.1 APPROVED DECISIONS
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
DR-005 ROLE MAPPING: UNRESOLVED
P7-D2: REMAINS DEFERRED
```
