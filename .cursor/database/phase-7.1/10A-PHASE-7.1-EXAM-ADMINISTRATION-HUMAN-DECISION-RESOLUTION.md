# MASTER PHASE 7 — PHASE 7.1 — EXAM ADMINISTRATION

# 10A-PHASE-7.1-EXAM-ADMINISTRATION-HUMAN-DECISION-RESOLUTION

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Document Type** | FORMAL HUMAN DECISION RESOLUTION |
| **Date** | 2026-09-11 |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.1 — Exam Administration |
| **Follows** | [`10-PHASE-7.1-EXAM-ADMINISTRATION-DESIGN-DECISION-LOCK-AMENDMENT.md`](./10-PHASE-7.1-EXAM-ADMINISTRATION-DESIGN-DECISION-LOCK-AMENDMENT.md) |
| **Upstream audits** | [`09-PHASE-7.1-EXAM-ADMINISTRATION-DECISION-RESOLUTION-AUDIT.md`](./09-PHASE-7.1-EXAM-ADMINISTRATION-DECISION-RESOLUTION-AUDIT.md), [`08-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-RE-READINESS-AUDIT.md`](./08-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-RE-READINESS-AUDIT.md) |
| **Master Lock** | [`../phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md`](../phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md) |
| **Prior amendment** | [`../phase-7/06-PHASE-7.1-DESIGN-LOCK-AMENDMENT.md`](../phase-7/06-PHASE-7.1-DESIGN-LOCK-AMENDMENT.md) |
| **Mode** | READ-ONLY GOVERNANCE / HUMAN DECISION RECORD |
| **Implementation Authorization** | **NOT GRANTED** |

```text
THIS DOCUMENT = formal human decision resolution record only
≠ HUMAN IMPLEMENTATION AUTHORIZATION
≠ permission catalog change
≠ role assignment
≠ CQRS / DDL / route / policy / test / UI implementation
≠ silent conversion of recommendations into approvals
```

---

## 2. Purpose

Formally record approved, deferred, unresolved, and prerequisite decisions after Design Decision Lock Amendment `10`, distinguishing:

1. Explicitly approved human decisions
2. Deferred decisions
3. Unresolved decisions
4. Implementation prerequisites
5. Implementation authorization

```text
Cursor MUST NOT turn a recommendation into a human approval.
```

---

## 3. Authorization Boundary

**Allowed:** create this Markdown artifact only.

**Forbidden (and not performed):**

```text
PHP · Laravel · CQRS handlers · commands · queries · DTOs · repositories
controllers · routes · policies · middleware · roles · permissions
config/security.php · migrations · SQL · tables · indexes · constraints
triggers · RLS · outbox · events · idempotency implementation
tests · seeders · jobs · schedulers · UI
modification of Master Lock · modification of document 10
```

---

## 4. Evidence Basis (read-only)

| Source | Finding relevant to this record |
|--------|----------------------------------|
| Document `10` (Design Decision Lock Amendment) | Scope partition, HD register, implementation NOT GRANTED |
| Document `09` (Decision Resolution Audit) | HD-001 still HUMAN APPROVAL REQUIRED; candidates ≠ approvals |
| Document `08` (CQRS Re-Readiness) | Prior BLOCKED |
| Master Phase 7 Design Lock | Authoritative locked design vocabulary |
| Phase 7.1 Design Lock Amendment (`06`) | DR-001…DR-006 preserved |
| `config/security.php` | **No `exam.*` permissions**; no Examiner/Registrar/Exam Officer roles |
| `docs/sis/exams/SECURITY-CONTRACT.md` | Grades roles only (`grades_manager`, `grades_teacher`, `grades_viewer`) |

**HD-001 evidence rule:** The repository does not provide sufficient authoritative evidence that an existing role owns the new `exam.*` permissions. This record does not invent a role or invent an approval.

---

## 5. HD-001 — SECURITY ROLE → PERMISSION MAPPING

### Status

**UNRESOLVED — HUMAN / SECURITY APPROVAL REQUIRED**

This decision is **NOT** approved by this artifact.

Do **NOT** invent a role.  
Do **NOT** invent an approval.  
Do **NOT** silently approve:

```text
grades_manager → exam.*
```

### Candidate mapping (candidate only — not approved)

| Permission | Candidate | Status |
|------------|-----------|--------|
| `exam.create` | `grades_manager` | **HUMAN APPROVAL REQUIRED** |
| `exam.update` | `grades_manager` | **HUMAN APPROVAL REQUIRED** |
| `exam.cancel` | `grades_manager` | **HUMAN APPROVAL REQUIRED** |

No candidate role is approved for:

* `exam.session.create`
* `exam.session.update`
* `exam.session.open`
* `exam.session.close`
* `exam.enrollment.create`
* `exam.enrollment.update`
* `exam.enrollment.cancel`

Those belong to Phase 7.2 and require future security approval.

### HD-001 Blocking Rule

Until explicit security approval exists:

```text
Phase 7.1 HTTP exposure = BLOCKED
```

The absence of approved role mapping MUST NOT be worked around using:

* wildcard permission
* super-admin assumption
* temporary role
* direct controller authorization
* hardcoded role
* bypass policy
* CLI-only hidden bypass

---

## 6. HD-002 — QUERY SCOPE

### Human Decision

**APPROVED**

Phase 7.1 is:

```text
COMMANDS ONLY
```

Exam Administration queries are deferred.

Do not create:

* query handlers
* query DTOs
* query routes
* query permissions
* query policies

No `exam.view` vocabulary is introduced.

---

## 7. HD-003 — QUERY AUTHORIZATION

### Human Decision

**DEFERRED**

Because HD-002 explicitly limits Phase 7.1 to commands, exam-administration query authorization is deferred with the future query phase.

Do not create:

* `exam.view`
* `exam.session.view`
* `exam.enrollment.view`

No query authorization implementation is permitted.

---

## 8. HD-004 — PHASE 7.1 / PHASE 7.2 BOUNDARY

### Human Decision

**APPROVED**

The implementation ownership is:

### Phase 7.1

```text
CreateExam
UpdateExam
CancelExam
```

### Phase 7.2

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

This is an explicit implementation-scope partition.

The commands remain part of the overall Phase 7 design vocabulary but are owned by different implementation phases.

No command may be implemented under the wrong phase.

---

## 9. HD-005 — CANCEL EXAM SESSION PERMISSION

### Human Decision

**APPROVED**

Do not introduce:

```text
exam.session.cancel
```

Use:

```text
exam.session.update
```

for `CancelExamSession`.

This applies when Phase 7.2 implements that command.

No permission catalog modification is authorized by this document.

---

## 10. HD-006 — CONFIRMED → PRESENT

### Human Decision

**APPROVED AS DEFERRED**

The:

```text
Confirmed → Present
```

transition is deferred to:

**PHASE 7.2 DESIGN LOCK**

Phase 7.1 MUST NOT implement it.

Phase 7.2 must later explicitly determine:

1. actor/role
2. permission
3. required session status
4. Scheduled behavior
5. Open/InProgress behavior
6. background/CLI restrictions
7. concurrency behavior

Do not invent any of these now.

---

## 11. HD-007 — IMPLEMENTATION AUTHORIZATION

### Human Decision

**NOT GRANTED**

This document does not authorize implementation.

The following remain forbidden:

* PHP implementation
* database implementation
* permission creation
* role assignment
* route exposure
* policy implementation
* test implementation

A separate implementation authorization gate is mandatory.

---

## 12. PRESERVED DESIGN DECISIONS

The following remain unchanged:

### DR-001

Current-grade cancellation guard:

```text
student_grades.is_current = true
```

CancelExam must not mutate grades.

### DR-002

CancelExamEnrollment must not mutate grades.

### DR-003

Update mutability allowlists remain authoritative.

### DR-004

Zero sessions:

```text
Completed = FAIL CLOSED
```

and completion requires at least one session and all applicable sessions completed.

### DR-005a

`exam.cancel` remains the approved exam cancellation vocabulary.

### DR-006

Event identity and idempotency identity remain distinct.

---

## 13. IDEMPOTENCY DECISION

Preserve:

```text
business mutation
+
outbox
+
idempotency persistence
=
SAME TRANSACTION / SAME COMMIT
```

This is an implementation prerequisite.

It does **NOT** authorize implementation.

The existing:

```text
audit.idempotency_keys
```

infrastructure must be reused unless a later authorized design change explicitly says otherwise.

Do not redesign the schema.

---

## 14. RLS DECISION

Preserve:

* SchoolContext requirement
* school ownership
* FORCE RLS
* cross-school fail-closed behavior
* no RLS bypass
* no service-account assumption
* no background/CLI authority unless separately designed and authorized

Do not modify RLS.

---

## 15. OUTBOX / EVENT DECISION

Preserve the established event vocabulary.

For Phase 7.1:

```text
ExamCreated
ExamUpdated
ExamCancelled
```

Session and enrollment events belong to Phase 7.2.

Event identity must remain distinct from idempotency identity.

No event implementation is authorized.

---

## 16. CONCURRENCY

Preserve the mandatory concurrency obligations.

At minimum, future implementation must test:

* concurrent CancelExam
* cancellation vs current-grade change
* duplicate idempotency submission
* same key with different payload
* school mismatch
* zero-row state transition

Phase 7.2 must additionally test session/enrollment lifecycle races.

No tests are to be implemented now.

---

## 17. FINAL DECISION MATRIX

| Decision | Result |
|----------|--------|
| HD-001 Security mapping | **UNRESOLVED — HUMAN APPROVAL REQUIRED** |
| HD-002 Query scope | **APPROVED — COMMANDS ONLY** |
| HD-003 Query AuthZ | **DEFERRED** |
| HD-004 7.1/7.2 boundary | **APPROVED** |
| HD-005 CancelExamSession permission | **APPROVED — `exam.session.update`** |
| HD-006 Confirmed → Present | **DEFERRED TO 7.2** |
| HD-007 Implementation authorization | **NOT GRANTED** |

---

## 18. PHASE 7.1 FINAL DESIGN SCOPE

After these decisions:

## Phase 7.1 owns

```text
CreateExam
UpdateExam
CancelExam
```

## Phase 7.1 does NOT own

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

## Phase 7.1 does NOT include

```text
Exam Administration Queries
Query DTOs
Query permissions
Confirmed → Present
New roles
New security bypasses
UI
Background exam administration
```

---

## 19. SECURITY BLOCK

```text
HD-001 remains the sole unresolved P0 security decision.

Therefore:

PHASE 7.1 DESIGN = SUBSTANTIALLY LOCKED
PHASE 7.1 IMPLEMENTATION = BLOCKED FOR HTTP EXPOSURE
```

This condition is not weakened by this artifact.

---

## 20. NEXT GATE

The next gate is:

```text
PHASE 7.1 — EXAM ADMINISTRATION CQRS RE-READINESS AUDIT
```

It must be READ-ONLY.

It must verify:

1. HD decision consistency
2. Master Lock consistency
3. Phase 7.1 command scope
4. 7.2 boundary
5. HD-001 security block
6. RLS
7. idempotency
8. outbox
9. concurrency
10. DR-001
11. DR-004
12. DR-006
13. absence of query implementation
14. absence of unauthorized implementation
15. implementation authorization remains NOT GRANTED

---

## 21. FINAL VERDICT

```text
HUMAN DECISIONS RECORDED — DESIGN SUBSTANTIALLY LOCKED
IMPLEMENTATION BLOCKED PENDING HD-001 SECURITY APPROVAL
```

```text
NEXT GATE:
PHASE 7.1 — EXAM ADMINISTRATION CQRS RE-READINESS AUDIT
```

---

## 22. FILE SAFETY

```text
Only this file was created by this task:
.cursor/database/phase-7.1/10A-PHASE-7.1-EXAM-ADMINISTRATION-HUMAN-DECISION-RESOLUTION.md

NO IMPLEMENTATION WAS PERFORMED.
No PHP, SQL, migration, DDL, RLS, permission, role mapping,
route, policy, configuration, seed, test, Master Lock, or
document-10 changes were made by this task.
```
