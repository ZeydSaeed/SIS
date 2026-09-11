# MASTER PHASE 7 — PHASE 7.1 — EXAM ADMINISTRATION

# 10-PHASE-7.1-EXAM-ADMINISTRATION-DESIGN-DECISION-LOCK-AMENDMENT

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Document Type** | DESIGN DECISION LOCK AMENDMENT |
| **Date** | 2026-09-11 |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.1 — Exam Administration |
| **Amends** | [`../phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md`](../phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md) |
| **Resolves gaps from** | [`09-PHASE-7.1-EXAM-ADMINISTRATION-DECISION-RESOLUTION-AUDIT.md`](./09-PHASE-7.1-EXAM-ADMINISTRATION-DECISION-RESOLUTION-AUDIT.md) |
| **Prior re-readiness** | [`08-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-RE-READINESS-AUDIT.md`](./08-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-RE-READINESS-AUDIT.md) — BLOCKED |
| **Status** | DESIGN LOCK AMENDED — **IMPLEMENTATION BLOCKED PENDING SECURITY DECISION** |
| **Implementation Authorization** | **NOT GRANTED** |

```text
THIS DOCUMENT = design decision lock amendment only
≠ HUMAN IMPLEMENTATION AUTHORIZATION
≠ permission catalog change
≠ role assignment
≠ CQRS / DDL / route / policy implementation
```

---

## 2. Authorization Boundary

**Allowed by this task:** create this Markdown artifact only.

**Forbidden (and not performed):**

```text
PHP · CQRS handlers · commands · queries · DTOs · repositories
controllers · routes · policies · permissions · roles
migrations · SQL · tables · indexes · constraints · triggers · RLS
events · outbox behavior · idempotency implementation
tests · configuration · seeders · jobs · UI
modification of Master Lock file / other docs (except this artifact)
```

---

## 3. Purpose

Establish a single authoritative Phase 7.1 design-decision record that:

1. Preserves already-locked decisions (DR-001, DR-002, DR-003, DR-004, DR-005a vocabulary, DR-006).
2. Resolves outstanding HD decisions from decision-resolution audit `09` where recommended and evidence-safe.
3. Explicitly leaves **HD-001 (role→permission mapping)** as **HUMAN APPROVAL REQUIRED** — no fabricated security approval.
4. Narrows Phase 7.1 command ownership relative to the prior P7-D4 full surface (scope partition amendment).
5. Does **not** grant implementation authorization.

---

## 4. Source Documents

| Priority | Document | Role |
|----------|----------|------|
| 1 | `phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md` (AMENDED) | Authoritative Master Lock |
| 2 | `phase-7/06-PHASE-7.1-DESIGN-LOCK-AMENDMENT.md` | Prior DCR human decisions (DR-001…DR-006) |
| 3 | `phase-7/07-PHASE-7.1-DESIGN-LOCK-AMENDMENT-GATE.md` | Amendment gate PASS |
| 4 | `phase-7.1/09-…DECISION-RESOLUTION-AUDIT.md` | Outstanding HD register |
| 5 | `phase-7.1/08-…RE-READINESS-AUDIT.md` | Prior BLOCKED re-readiness |
| 6 | `phase-7/05-…DESIGN-CHANGE-REQUEST.md` | DCR history |
| 7 | `config/security.php` | Authoritative roles/permissions — **no `exam.*`** |
| 8 | `docs/sis/exams/SECURITY-CONTRACT.md` | Grades roles only |
| 9 | Idempotency / UnitOfW / Outbox infra | Implementation prerequisite evidence |
| 10 | Grade / Attendance CQRS handlers | Pattern evidence only (post-commit store ≠ Phase 7.1 target) |

**Evidence for HD-001:** No repository document or `config/security.php` entry assigns `exam.*` to any role. No Examiner/Registrar/Exam Officer roles exist.

---

## 5. Existing Locked Decisions Preserved

### 5.1 DR-001 — Current-grade guard (CancelExam)

**PRESERVED — DO NOT REOPEN**

* `CURRENT = student_grades.is_current = true` for any enrollment under the target exam.
* CancelExam MUST NOT mutate grades.
* Historical VOIDED grades do not independently block.
* `CorrectStudentGrade` does not clear the CancelExam CURRENT guard.
* `VoidStudentGrade` may remove CURRENT condition.
* No DB cancel-guard trigger required; application + transaction predicates.
* Concurrency race tests remain mandatory at implementation.

### 5.2 DR-002 — CancelExamEnrollment vs grades

**PRESERVED** (lifecycle ownership moves to Phase 7.2 under HD-004, but the **rule** remains locked for when that command is implemented).

* MUST NOT mutate `student_grades`.
* CURRENT for target `exam_enrollment_id` → FAIL CLOSED.
* Grade CQRS only for grade actions.

### 5.3 DR-003 — Update mutability allowlists

**PRESERVED** for Exam-level UpdateExam in Phase 7.1; Session/Enrollment Update* preserved for Phase 7.2.

### 5.4 DR-004 — Zero-session / Completed

**PRESERVED — DO NOT REOPEN**

* Zero sessions → Completed FAIL CLOSED.
* Completed only when ≥1 session; no Scheduled; no InProgress; every non-cancelled session Completed.
* Cancelled sessions remain Cancelled; no automatic session closing.
* No `CompleteExam` command.

### 5.5 DR-005a — Dedicated `exam.cancel` vocabulary

**PRESERVED** (vocabulary only — not role assignment).

### 5.6 DR-006 — Event identity ≠ idempotency identity

**PRESERVED — DO NOT REOPEN**

Command idempotency key, command name, aggregate id, outbox message id, event type/payload, correlation id, causation id MUST NOT be collapsed.

### 5.7 P7-D1 / P7-D2 / P7-D3 / P7-D8 / P7-D9 / Grade SSOT / Hard-delete policy

**PRESERVED** unchanged.

---

## 6. Amendment Decisions

This amendment **explicitly partitions** the previously frozen P7-D4 command surface between Phase 7.1 and Phase 7.2 (HD-004), locks query deferral (HD-002), locks CancelExamSession permission vocabulary choice (HD-005), and defers Present (HD-006).

It does **not** approve role→permission assignments (HD-001).

| Kind | Treatment in this document |
|------|----------------------------|
| Already locked | Preserved (§5) |
| Human security mapping | **HUMAN APPROVAL REQUIRED** (§7) |
| Scope / phase / query / session-cancel vocab | **LOCKED BY THIS AMENDMENT** where stated |
| Same-COMMIT idempotency | **IMPLEMENTATION PREREQUISITE** (§13) |
| Deferred | Explicitly marked (§9, §12, §23) |
| Implementation authorization | **NOT GRANTED** (§22) |

---

## 7. HD-001 Security Role → Permission Decision

### Governance

```text
Do NOT create new roles in Phase 7.1.
Do NOT invent Examiner / Exam Officer / Assessment Officer / Registrar / Invigilator.
Do NOT auto-map every exam.* permission to grades_manager, grades_teacher,
attendance_manager, enrollment_manager, or any other role.
```

### Authoritative roles present (`config/security.php`)

`student_manager`, `student_viewer`, `enrollment_manager`, `enrollment_viewer`, `grades_manager`, `grades_teacher`, `grades_viewer`, `attendance_viewer`, `attendance_teacher`, `attendance_manager`.

### Candidate note (NOT approval)

`grades_manager` is an **EVIDENCE-SUPPORTED CANDIDATE** for exam-level administration only if humans accept that its business meaning covers academic assessment administration. Repository evidence does **not** establish that semantic authorization today.

`grades_teacher`, `attendance_*`, `enrollment_*` must **not** be inferred as exam administrators.

### HD-001 — Approved Role → Permission Mapping

| Permission | Proposed role (candidate only) | Status |
|------------|--------------------------------|--------|
| `exam.create` | `grades_manager` | **HUMAN APPROVAL REQUIRED** |
| `exam.update` | `grades_manager` | **HUMAN APPROVAL REQUIRED** |
| `exam.cancel` | `grades_manager` | **HUMAN APPROVAL REQUIRED** |
| `exam.session.create` | TBD | **HUMAN APPROVAL REQUIRED** |
| `exam.session.update` | TBD | **HUMAN APPROVAL REQUIRED** |
| `exam.session.open` | TBD | **HUMAN APPROVAL REQUIRED** |
| `exam.session.close` | TBD | **HUMAN APPROVAL REQUIRED** |
| `exam.enrollment.create` | TBD | **HUMAN APPROVAL REQUIRED** |
| `exam.enrollment.update` | TBD | **HUMAN APPROVAL REQUIRED** |
| `exam.enrollment.cancel` | TBD | **HUMAN APPROVAL REQUIRED** |

```text
HD-001 STATUS: HUMAN APPROVAL REQUIRED — NOT LOCKED AS APPROVED MAPPING
No recommendation above is converted into an approved security decision.
```

**Phase 7.1 HTTP exposure implication:** blocked until exam-level permissions (`exam.create` / `exam.update` / `exam.cancel` at minimum) receive explicit human/security approval for role ownership. Session/enrollment mapping may wait for Phase 7.2 but must also be approved before those commands are exposed.

---

## 8. HD-002 Query Scope Decision

### Decision

**OPTION B — COMMANDS ONLY**

```text
Phase 7.1 CQRS implementation scope is COMMANDS ONLY.
```

### Rationale (locked by this amendment)

* Master Lock freezes the command surface; admin query contracts are not locked.
* Query DTOs and view-permission vocabulary (`exam.view`, etc.) are not locked and must not be invented.
* Phase 7.6 targets student/guardian **grade** reads — must not be overloaded with exam-admin operational reads.
* Separating writers from admin read models reduces scope and permission expansion risk.

### Deferral statement

```text
Exam Administration queries = DEFERRED —
FUTURE EXAM ADMINISTRATION QUERY PHASE TO BE EXPLICITLY NAMED
BY A FUTURE MASTER PHASE DECISION
```

No new phase number is invented by this amendment. Phase 7.6 is **not** designated as that home.

**Status:** **LOCKED BY AMENDMENT**

---

## 9. HD-003 Query Authorization Decision

Because HD-002 = Commands Only:

```text
HD-003 = NOT APPLICABLE TO PHASE 7.1 COMMAND IMPLEMENTATION
Status = DEFERRED (with Exam Administration queries)
```

**Forbidden by this amendment:**

* Creating `exam.view` / `exam.session.view` / `exam.enrollment.view`
* Inventing query DTOs
* Implementing query authorization policies for exam admin reads

---

## 10. HD-004 Phase 7.1 / 7.2 Boundary

### Scope partition amendment (authoritative)

This **explicitly amends** the prior undifferentiated P7-D4 “INCLUDE” surface for **implementation phasing**. The commands remain design-valid in the Master Lock catalog; **ownership for implementation** is partitioned as follows.

### PHASE 7.1 — EXAM-LEVEL COMMANDS ONLY

| Command | Ownership |
|---------|-----------|
| `CreateExam` | **Phase 7.1** |
| `UpdateExam` | **Phase 7.1** |
| `CancelExam` | **Phase 7.1** |

### PHASE 7.2 — SESSION + ENROLLMENT LIFECYCLE

| Command | Ownership |
|---------|-----------|
| `CreateExamSession` | **Phase 7.2** |
| `UpdateExamSession` | **Phase 7.2** |
| `OpenExamSession` | **Phase 7.2** |
| `CloseExamSession` | **Phase 7.2** |
| `CancelExamSession` | **Phase 7.2** |
| `CreateExamEnrollment` | **Phase 7.2** |
| `UpdateExamEnrollment` | **Phase 7.2** |
| `CancelExamEnrollment` | **Phase 7.2** |

```text
Do not duplicate ownership across 7.1 and 7.2.
Do not implement 7.2 work under Phase 7.1.
```

**Status:** **LOCKED BY AMENDMENT**

---

## 11. HD-005 CancelExamSession Permission

### Decision

Do **NOT** create `exam.session.cancel` under this amendment.

`CancelExamSession` remains authorized under design vocabulary:

```text
exam.session.update
```

unless a future Security Change Control decision adds a dedicated cancel permission.

Permission catalog is **not** modified by this document.

**Status:** **LOCKED** (vocabulary decision for Phase 7.2 when implemented)

---

## 12. HD-006 Confirmed → Present

### Decision

**DEFER** `Confirmed → Present` from Phase 7.1 (and from any 7.1 slice).

Under HD-004, enrollment lifecycle belongs to **Phase 7.2**.

```text
Confirmed → Present = DEFERRED TO PHASE 7.2 DESIGN LOCK
```

**Forbidden now:** invent actor; invent Invigilator/Examiner; invent permission; decide Scheduled vs Open/InProgress Present rules; implement Present under 7.1.

### Phase 7.2 must later lock

1. Authorized actor/role  
2. Permission  
3. Required session status  
4. Whether Scheduled permits Present  
5. Whether only Open/InProgress permits Present  
6. Whether background/CLI actors are prohibited  
7. Concurrency behavior  

**Status:** **DEFERRED**

---

## 13. Same-Commit Idempotency Contract

### Implementation design obligation (not a new product decision)

```text
business mutation + outbox + idempotency persistence
→ SAME DATABASE TRANSACTION / SAME COMMIT
```

* `audit.idempotency_keys` exists — **do not redesign**.
* `EloquentIdempotencyStore` uses `DB::table` and **can** participate in ambient transaction when called inside UnitOfW.
* `EloquentUnitOfWork` uses `DB::transaction`.
* **Do not** copy Grade/Attendance **post-commit** `store` placement into Phase 7.1.

### Conceptual ordering

1. Resolve/reject idempotency replay (`find` + payload/school conflict checks).  
2. Begin UnitOfWork transaction.  
3. Validate and perform business mutation.  
4. Stage outbox event.  
5. Persist idempotency record.  
6. Commit atomically.

Replay of a successful key MUST NOT emit another business event.  
Same key + different payload → conflict / FAIL CLOSED.  
School mismatch → FAIL CLOSED.  
Event identity ≠ idempotency identity (DR-006).

**Do not implement now.**

---

## 14. RLS / School Isolation

**PRESERVED**

* `exams.exams`, `exams.exam_sessions`, `exams.exam_enrollments` — school ownership + FORCE RLS as established.
* SchoolContext mandatory for normal application requests.
* Composite ownership checks required.
* Cross-school mutation FAIL CLOSED.
* Background/CLI exam administration **not** authorized merely because a job/service exists.
* No RLS bypass; no DISABLE / NO FORCE RLS; no service-account bypass assumption.

**Do not change RLS DDL.**

---

## 15. Historical Integrity / Cancellation / Delete Policy

**PRESERVED**

* Hard delete of exam business records forbidden by policy.
* Cancellation = lifecycle state transition, not deletion.
* Grade / seat historical integrity protected (grades reject-DELETE **PROVEN**; foundation reject-DELETE triggers = **OPTIONAL FUTURE DB HARDENING**, not a blocker for this amendment).
* No destructive deletion in implementation.

**Do not add triggers now.**

---

## 16. Concurrency Obligations

Implementation (when authorized) MUST test at least:

* concurrent exam cancellation;
* concurrent exam enrollment cancellation (Phase 7.2);
* cancellation vs CURRENT-grade state change;
* concurrent session lifecycle transitions (Phase 7.2);
* concurrent enrollment lifecycle transitions (Phase 7.2);
* duplicate idempotency submission;
* same idempotency key with conflicting payload;

Plus the previously locked race set involving CancelExam / CancelExamEnrollment vs session/seat/grade writers.

All transitions: zero-row / predicate miss → **FAIL CLOSED**.

**Do not implement tests now.**

---

## 17. Outbox / Event Identity

**PRESERVED** Phase 7 event catalog (design names only):

```text
ExamCreated, ExamUpdated, ExamCancelled
ExamSessionCreated, ExamSessionUpdated, ExamSessionOpened,
ExamSessionClosed, ExamSessionCancelled
ExamEnrollmentCreated, ExamEnrollmentUpdated, ExamEnrollmentCancelled
```

* Event identity independent of idempotency identity.
* `correlation_id` remains envelope field.
* If `causation_id` lacks a dedicated DB column, representation follows approved event architecture (e.g. payload) — implementation detail.
* Unlocked payload field lists remain implementation details.

**Do not alter outbox schema. Do not create event classes now.**

---

## 18. Authoritative Phase 7.1 Command Surface

### IN Phase 7.1 (after this amendment)

1. `CreateExam`  
2. `UpdateExam`  
3. `CancelExam`  

### NOT IN Phase 7.1 implementation

* `CreateExamSession`, `UpdateExamSession`, `OpenExamSession`, `CloseExamSession`, `CancelExamSession`  
* `CreateExamEnrollment`, `UpdateExamEnrollment`, `CancelExamEnrollment`  

Those are **Phase 7.2**.

```text
This is an explicit scope partition / amendment to implementation ownership.
It does not delete the commands from the Master Lock catalog.
```

---

## 19. In-Scope / Out-of-Scope

### In Scope (Phase 7.1 design after amendment)

* Exam-level CQRS **commands** only (`CreateExam`, `UpdateExam`, `CancelExam`).
* Security mapping **only after** explicit approved role→permission decisions (HD-001).
* Existing RLS boundaries; SchoolContext; fail-closed cross-school.
* DR-001 current-grade guard on CancelExam.
* DR-004 where Exam→Completed is concerned (session predicates; sessions themselves created in 7.2).
* Same-COMMIT idempotency + outbox + concurrency obligations.
* Audit/outbox requirements; cancellation as state transition.
* UpdateExam mutability allowlists (DR-003).

### Out of Scope (Phase 7.1)

* Session lifecycle implementation  
* Exam enrollment lifecycle implementation  
* Confirmed → Present  
* Exam admin queries / query DTOs / query-specific permissions  
* UI  
* New roles  
* New security bypasses  
* Background exam administration  
* Automatic schema changes  
* Results / GPA / Ranking / Transcript / Graduation / Certificates / Attendance / Generic Assessment  
* `CompleteExam` / Publish / Bulk import  

---

## 20. Human Decision Status Matrix

| Decision | Subject | Decision | Status |
|----------|---------|----------|--------|
| **HD-001** | Role → permission mapping | Explicit per-permission mapping; no invented roles; candidates only | **HUMAN APPROVAL REQUIRED** |
| **HD-002** | Query scope | Commands only; admin queries deferred | **LOCKED BY AMENDMENT** |
| **HD-003** | Query AuthZ | Deferred with queries; N/A to 7.1 commands | **DEFERRED** |
| **HD-004** | 7.1 / 7.2 ownership | 7.1 Exam commands; 7.2 Session + Enrollment | **LOCKED BY AMENDMENT** |
| **HD-005** | Session cancel permission | `CancelExamSession` → `exam.session.update`; no `exam.session.cancel` | **LOCKED** |
| **HD-006** | Confirmed → Present | Deferred to Phase 7.2 Design Lock | **DEFERRED** |
| **HD-007** | Implementation authorization | Not granted | **NOT AUTHORIZED** |

---

## 21. Security Change Control

```text
Phase 7.1 implementation remains blocked from HTTP exposure
until the role→permission mapping (HD-001) has received
explicit human / security approval.
```

**Forbidden until that approval + separate implementation authorization:**

* Creating permissions merely to pass a gate  
* Assigning permissions merely to pass architecture validators  
* Modifying `config/security.php`  
* Creating temporary roles  
* Wildcard authorization  

Permission vocabulary remains design-locked; catalog/role assignment requires Security Change Control + implementation auth.

---

## 22. Implementation Authorization

## HUMAN IMPLEMENTATION AUTHORIZATION

```text
NOT GRANTED
```

This document resolves/amends **design** only.

It does **not** authorize migrations, PHP/CQRS implementation, permission changes, route exposure, tests, or UI.

A future explicit authorization gate is required after re-readiness.

---

## 23. Remaining Risks / Deferred Decisions

| Item | Status |
|------|--------|
| HD-001 role→permission mapping | **HUMAN APPROVAL REQUIRED** — blocks HTTP AuthZ |
| Exam Administration queries | **DEFERRED** — future named phase |
| Query AuthZ / `exam.view*` | **DEFERRED** |
| Confirmed → Present | **DEFERRED** to Phase 7.2 Design Lock |
| Phase 7.2 full Design Lock | **NOT STARTED** |
| Foundation reject-DELETE triggers | **OPTIONAL FUTURE DB HARDENING** |
| Outbox `causation_id` column | **P2** mapping detail |
| `exam.*` catalog rows | **IMPLEMENTATION PREREQUISITE** after HD-001 + impl auth |
| Same-COMMIT handler placement | **IMPLEMENTATION PREREQUISITE** |
| Document `04` stale Cancel TBD | **P3** hygiene |

---

## 24. Next Gate

After this amendment is accepted:

```text
NEXT STEP:
PHASE 7.1 — EXAM ADMINISTRATION CQRS RE-READINESS AUDIT
(READ-ONLY successor to 08)
```

That audit must verify:

1. Master Lock amendment consistency with this document  
2. Phase 7.1 command scope (Exam-level only)  
3. Security mapping status (HD-001 still open unless separately approved)  
4. RLS readiness  
5. Same-COMMIT idempotency design  
6. Outbox design  
7. Concurrency obligations  
8. DR-001 / DR-004 / DR-006 preservation  
9. 7.1 / 7.2 boundary  
10. Absence of unauthorized query scope  
11. Absence of unauthorized implementation  
12. Implementation authorization status = NOT GRANTED  

Only after that audit reaches the required readiness verdict may a separate **HUMAN IMPLEMENTATION AUTHORIZATION** be considered — and only if HD-001 (at least for `exam.create` / `exam.update` / `exam.cancel`) is approved.

---

## 25. Final Verdict

```text
DESIGN LOCK AMENDED — IMPLEMENTATION BLOCKED PENDING SECURITY DECISION
```

| Claim | Status |
|-------|--------|
| Design scope (7.1 commands / queries / 7.2 boundary) amended | **YES** |
| HD-001 security mapping approved | **NO** — HUMAN APPROVAL REQUIRED |
| READY FOR IMPLEMENTATION | **NO** |
| HUMAN IMPLEMENTATION AUTHORIZATION | **NOT GRANTED** |

```text
NO IMPLEMENTATION WAS PERFORMED.

No PHP, SQL, migration, DDL, RLS, permission,
role mapping, route, policy, configuration, seed,
test, or Master Lock file changes were made by this task.
Only this amendment artifact was created.
```
