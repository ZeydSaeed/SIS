# MASTER PHASE 7
# PHASE 7.2
# BATCH 6
# U09 — HUMAN DECISION RESOLUTION

---

```text
Document Type:
HUMAN DECISION RESOLUTION

Unit:
U09 — PresentExamEnrollment / Present transition

Phase:
7.2

Batch:
6

Authorization:
HUMAN DESIGN DECISION RESOLUTION

Implementation:
NOT AUTHORIZED

Database Changes:
NONE

RLS Changes:
NONE

Permission Changes:
NONE

HTTP Changes:
NONE
```

| Field | Value |
|-------|-------|
| **Source gate** | `.cursor/database/phase-7.2/10-PHASE-7.2-BATCH-6-U09-DESIGN-AUTHORIZATION-GATE.md` |
| **Design Lock** | `.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md` (§7, §10 HD-7.2-005) |
| **Date** | 2026-09-12 |
| **Mode** | DESIGN DECISION ONLY — NO IMPLEMENTATION |

```text
Human Decision Resolution ≠ Implementation Authorization
```

---

## 1. Authorization State (recorded)

```text
MASTER PHASE 7:
AUTHORIZED

PHASE 7.2:
DESIGN LOCK APPROVED

BATCH 6:
OPEN

U08:
IMPLEMENTED + AUDITED
PASS WITH CONDITIONS

U09:
DESIGN READY WITH CONDITIONS → DESIGN DECISIONS RESOLVED (this document)
IMPLEMENTATION NOT AUTHORIZED

U10–U15:
NOT AUTHORIZED
```

```text
U09 IMPLEMENTATION AUTHORIZATION:
NOT GRANTED

Reason:
Human Decision Resolution ≠ Implementation Authorization
```

---

## 2. Conflict Check

| Check | Result |
|-------|--------|
| HD-U09-001 vs Design Lock Present transition | **NO CONFLICT** — names the dedicated Present command |
| HD-U09-001 vs U07 exception `PresentExamEnrollment` | **NO CONFLICT** — `PresentExamEnrollmentCommand` is the CQRS class form |
| HD-U09-002 vs Gate “Updated or dedicated” | **RESOLVES** open packaging choice → reuse Updated |
| HD-U09-003 vs U07/U08 causes | **NO CONFLICT** — distinct from `exam_enrollment_update` / `exam_enrollment_cancel` / `exam_session_cancel` |
| HD-U09-004 vs HD-U08-001 / HD-7.2-002A spirit | **NO CONFLICT** — terminal/repeat no-op pattern |
| HD-U09-005 vs DR-002 | **NO CONFLICT** — DR-002 is CancelExamEnrollment only; not extended to Present |
| HD-U09-005 vs “no grade mutation” | **NO CONFLICT** — allow Present does not authorize grade writes |
| `ExamAdministrationAction` today | **NO Present case yet** — design action `PresentEnrollment` confirmed; case not implemented (AuthZ not granted) |

```text
GENUINE CONTRADICTION REQUIRING STOP: NONE
```

---

## 3. HD-U09-001 — COMMAND NAME

```text
Decision ID:
HD-U09-001

Priority:
MEDIUM

Topic:
Exact command name

Human Decision:
APPROVED

Status:
HUMAN APPROVED
```

### Approved Decision

The command identity shall be:

```text
PresentExamEnrollmentCommand
```

The operation shall represent:

```text
Confirmed → Present
```

for an exam enrollment.

### Rationale

The name explicitly identifies:

* the aggregate/business object: ExamEnrollment
* the lifecycle operation: Present

It avoids ambiguity with:

* generic Update
* attendance
* exam session state
* U07 enrollment update

### Constraints

```text
The command name decision does NOT authorize creation of the command class.
Implementation remains unauthorized.
```

---

## 4. HD-U09-002 — EVENT PACKAGING

```text
Decision ID:
HD-U09-002

Priority:
HIGH

Topic:
Event type

Human Decision:
APPROVED

Status:
HUMAN APPROVED
```

### Approved Decision

U09 shall reuse the existing event:

```text
ExamEnrollmentUpdated
```

rather than introducing a dedicated:

```text
ExamEnrollmentPresented
```

event.

The U09 semantic distinction shall be represented by the event cause.

### Approved Event

```text
ExamEnrollmentUpdated
```

### Approved Cause (paired with HD-U09-003)

```text
exam_enrollment_present
```

### Rationale

This preserves the existing ExamEnrollment event contract while retaining a precise causal identity for the Present operation.

```text
Do not create a new event class.
Do not modify event implementation in this task.
This is a design decision only.
```

---

## 5. HD-U09-003 — EVENT CAUSE

```text
Decision ID:
HD-U09-003

Priority:
HIGH

Topic:
Cause string

Human Decision:
APPROVED

Status:
HUMAN APPROVED
```

### Approved Cause

```text
exam_enrollment_present
```

The cause must distinguish U09 from:

```text
exam_enrollment_update
exam_enrollment_cancel
exam_session_cancel
```

The cause represents the business operation, not the idempotency key.

### Identity Rule

```text
Event identity ≠ Idempotency identity
```

Do not implement this rule in this task.

---

## 6. HD-U09-004 — ALREADY PRESENT

```text
Decision ID:
HD-U09-004

Priority:
MEDIUM

Topic:
Present → Present

Human Decision:
APPROVED

Status:
HUMAN APPROVED
```

### Approved Behavior

When:

```text
Enrollment = Present
Session = InProgress
```

a repeated U09 request shall be:

```text
IDEMPOTENT NO-OP
```

Expected result:

```text
NO STATE CHANGE
NO SECOND BUSINESS MUTATION
NO ExamEnrollmentUpdated EVENT
NO DUPLICATE OUTBOX MESSAGE
```

Idempotency behavior must remain consistent with the existing Phase 7.2 writer pattern.

A business-level no-op using a new valid idempotency request must NOT generate a second Present event.

### Important Distinction

This does not change:

```text
same idempotency key + same payload → REPLAY
same idempotency key + different payload → CONFLICT
```

---

## 7. HD-U09-005 — CURRENT GRADE

```text
Decision ID:
HD-U09-005

Priority:
LOW

Topic:
Does CURRENT grade block Present?

Human Decision:
APPROVED

Status:
HUMAN APPROVED
```

### Approved Decision

A CURRENT grade does:

```text
NOT BLOCK
```

the U09 transition:

```text
Confirmed → Present
```

provided all other U09 guards pass.

### Explicit Safety Rules

U09:

```text
MUST NOT mutate grades
MUST NOT create grades
MUST NOT update grades
MUST NOT delete grades
MUST NOT convert Present into a grade operation
```

Present remains an enrollment lifecycle state.

### Fail-Closed Rule

If the grade lookup/state needed by the U09 guard cannot be reliably determined, the operation must fail closed according to the approved security/design semantics.

```text
Do not interpret lookup failure as “no grade exists”.
Do not implement this in the current task.
```

Historical grades also do **not** block Present (consolidated semantics below).

---

## 8. Consolidated Human Decision Register

| Decision ID | Priority | Decision                                 | Status         |
| ----------- | -------- | ---------------------------------------- | -------------- |
| HD-U09-001  | MEDIUM   | Command = `PresentExamEnrollmentCommand` | HUMAN APPROVED |
| HD-U09-002  | HIGH     | Reuse `ExamEnrollmentUpdated`            | HUMAN APPROVED |
| HD-U09-003  | HIGH     | Cause = `exam_enrollment_present`        | HUMAN APPROVED |
| HD-U09-004  | MEDIUM   | Present → Present = idempotent no-op     | HUMAN APPROVED |
| HD-U09-005  | LOW      | CURRENT grade does not block Present     | HUMAN APPROVED |

```text
Human Decisions Required Before Implementation:
0

Resolved:
5/5

Unresolved:
0
```

---

## 9. U09 Business Semantics After Resolution

```text
U09:
Confirmed → Present

Permission:
exam.enrollment.present

Authority:
grades_manager

Required Session State:
InProgress

Scheduled:
DENY

Completed:
DENY

Cancelled:
DENY

Registered:
DENY

Absent:
DENY

Withdrawn:
DENY

Present:
IDEMPOTENT NO-OP

Current Grade:
DOES NOT BLOCK

Historical Grade:
DOES NOT BLOCK

Grade Mutation:
NONE

Attendance Mutation:
NONE

Event:
ExamEnrollmentUpdated

Cause:
exam_enrollment_present

No-op Event:
NONE

Database Schema Change:
NONE

RLS Change:
NONE

HTTP:
DEFERRED

School Isolation:
FAIL CLOSED

Idempotency:
REQUIRED

Implementation:
NOT AUTHORIZED
```

---

## 10. Preserved Locked Decisions (unchanged)

```text
Confirmed → Present only
Present is NOT implemented through U07 generic update
InProgress session required
Scheduled → Present forbidden
Present ≠ is_absent
No attendance mutation
No grade mutation
Present → Absent belongs to U07
Present → Withdrawn belongs to U08
Restore is forbidden
HTTP deferred
No DB schema change required
Same-commit writer pattern
Event identity ≠ idempotency identity
```

---

## 11. U05 / U06 / U07 / U08 Boundary

### U05

```text
Session cancellation → U09 Present is DENIED
```

### U06

```text
U06 creates Registered
U06 is not modified by U09
```

### U07

```text
U07 owns Confirm / Absent
U07 must continue rejecting Confirmed → Present
U09 is the dedicated Present operation
```

### U08

```text
U08 owns Present → Withdrawn
U09 does not withdraw
```

Do not alter U05–U08.

---

## 12. Security Decision Finalization

```text
Action (design):
PresentEnrollment

Permission:
exam.enrollment.present

Authority:
grades_manager
```

### Repository mapping note

| Layer | Current state | Design mapping |
|-------|---------------|----------------|
| `Permission::EXAM_ENROLLMENT_PRESENT` | **EXISTS** (`exam.enrollment.present`) | Reuse — do not add |
| `config/security.php` | Registered to `grades_manager` | Unchanged |
| `ExamAdministrationAction` | **No** Present case yet | Design case name: `PresentEnrollment` (string value expected at impl: `present_enrollment`, matching sibling pattern) |
| `PermissionCatalogExamAuthority` | No Present mapping yet | Map `PresentEnrollment` → `exam.enrollment.present` only when AuthZ grants implementation |

```text
Do NOT change PermissionCatalogExamAuthority in this task.
Do NOT add a permission.
Do NOT modify config/security.php.
```

---

## 13. Design Lock Status After Resolution

```text
Business Semantics:
LOCKED

Packaging Decisions:
LOCKED

Human Decisions:
5/5 APPROVED

Unresolved Human Decisions:
0
```

```text
U09 is eligible for:
FINAL DESIGN LOCK / IMPLEMENTATION AUTHORIZATION REVIEW

U09 is NOT eligible for:
Implementation (AuthZ NOT GRANTED)
```

---

## 14. Implementation Authorization

```text
U09 IMPLEMENTATION AUTHORIZATION:
NOT GRANTED

Reason:
Human Decision Resolution ≠ Implementation Authorization
```

```text
NEXT REQUIRED GATE:
U09 FINAL DESIGN LOCK / HUMAN IMPLEMENTATION AUTHORIZATION REQUEST
```

Do not implement U09 in this task.  
Do not prepare U10.  
Do not close Batch 6.

---

## 15. Safety Verification

```text
Expected worktree change:
.cursor/database/phase-7.2/11-PHASE-7.2-BATCH-6-U09-HUMAN-DECISION-RESOLUTION.md

Optional reference update:
.cursor/database/phase-7.2/10-PHASE-7.2-BATCH-6-U09-DESIGN-AUTHORIZATION-GATE.md

PHP / tests / migrations / DB / RLS / permissions / routes / controllers /
events / outbox / idempotency implementation: NONE
```
