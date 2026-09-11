# MASTER PHASE 7 — PHASE 7.2 — HUMAN DECISION APPROVAL RECORD

---

## Section 1 — Document Control

| Field | Value |
|-------|-------|
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Document Type** | HUMAN DECISION APPROVAL RECORD ONLY |
| **Date** | 2026-09-12 |
| **Source ballot** | `.cursor/database/phase-7.2/04A-PHASE-7.2-HUMAN-APPROVAL-BALLOT.md` |
| **Predecessor resolution** | `.cursor/database/phase-7.2/03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md` |

```text
THIS DOCUMENT = human approval evidence record after completed ballot
≠ Design Lock (not created by this task)
≠ Implementation Authorization
≠ permission / role / RLS / migration / application change
```

### Authorization state (unchanged)

```text
Implementation = NOT AUTHORIZED
Database changes = NOT AUTHORIZED
Application changes = NOT AUTHORIZED
Permission registration = NOT AUTHORIZED
Role changes = NOT AUTHORIZED
RLS changes = NOT AUTHORIZED
HTTP = NOT AUTHORIZED
Grade handler / StudentGradeWriteGuard changes = NOT AUTHORIZED
CancelExam / CancelExamSession implementation changes = NOT AUTHORIZED
Migration = NOT AUTHORIZED
Design Lock artifact = NOT CREATED BY THIS TASK
```

---

## Section 2 — Human Authority

| Role | Recorded identity |
|------|-------------------|
| Business owner | **NOT SUPPLIED** |
| Security owner | **NOT SUPPLIED** |
| Project owner | **NOT SUPPLIED** |

```text
Named signer identity was not supplied in the ballot.
Approvals are recorded from the explicit human decision owner supply dated 2026-09-12.
No person name was invented.
```

---

## Section 3 — Approval Evidence Standard

A decision is recorded as **HUMAN APPROVED** only when `04A` contains an explicit selection matching the human decision supply.

| Non-evidence | Status |
|--------------|--------|
| Recommendation text | **NOT APPROVAL** |
| Empty checkbox | **NOT APPROVAL** |
| Cursor inference | **NOT APPROVAL** |
| Phase 7.1 HD-001 alone | **NOT** Phase 7.2 permission ownership |

### Ballot inspection (2026-09-12 after update)

```text
Human decision groups reviewed = 13
Human decision items resolved = 15
Human approvals evidenced = 15
PENDING HUMAN RESPONSE remaining on ballot items = 0
```

### Counting rule

```text
HD-7.2-002 contains three independent sub-decisions:
  002A, 002B, 002D
Therefore items = 15, groups = 13
Do not report “13 decisions resolved” without this distinction.
```

---

## Section 4 — Decision-by-Decision Record

### HD-7.2-001 — Permission Ownership

| Field | Value |
|-------|-------|
| **Question** | Who owns the seven Phase 7.2 session/enrollment permissions? |
| **Selected option** | **A** |
| **Human rationale** | Centralize ownership on `grades_manager` for the seven approved permissions |
| **Approval evidence** | Human decision supply 2026-09-12 → recorded in `04A` |
| **Date** | 2026-09-12 |
| **Dependencies** | HD-7.2-004; HD-7.2-005 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
grades_manager owns:
exam.session.create
exam.session.update
exam.session.open
exam.session.close
exam.enrollment.create
exam.enrollment.update
exam.enrollment.cancel

exam.session.cancel = FORBIDDEN (HD-005)
```

---

### HD-7.2-002A — Already Cancelled session

| Field | Value |
|-------|-------|
| **Selected option** | **A1** — idempotent no-op |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

---

### HD-7.2-002B — Active seats on CancelExamSession

| Field | Value |
|-------|-------|
| **Selected option** | **B1** — withdraw active seats atomically |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

---

### HD-7.2-002D — CancelExam after partial session cancels

| Field | Value |
|-------|-------|
| **Selected option** | **D1** — skip already Cancelled sessions |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
HD-7.2-002 package (A+B+D) = RESOLVED
Immutable: no grade mutation / void / delete / conversion as cancel side effect; no Restore
```

---

### HD-7.2-003 — CancelExamSession CURRENT-grade guard

| Field | Value |
|-------|-------|
| **Selected option** | **A** — FAIL CLOSED if ANY CURRENT grade exists for the session |
| **Date** | 2026-09-12 |
| **Dependencies** | HD-7.2-002; HD-7.2-008 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

---

### HD-7.2-004 — Permission catalog registration

| Field | Value |
|-------|-------|
| **Selected option** | **A** — register exactly the seven names (after ownership) |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
Do NOT register exam.session.cancel
Catalog mutation itself remains NOT AUTHORIZED by this approval record
```

**Dependency note (not a conflict):** HD-7.2-005 additionally authorizes dedicated permission name `exam.enrollment.present` (owned by `grades_manager`). That name is **not** `exam.session.cancel` and is **not** one of the seven Master Lock session/enrollment admin names. Future catalog work must include both the seven (HD-7.2-004) and `exam.enrollment.present` (HD-7.2-005) when permission registration is separately authorized.

---

### HD-7.2-005 — Confirmed → Present

| Field | Value |
|-------|-------|
| **Selected option** | **OTHER** (explicit values) — dedicated Present permission owned by `grades_manager` |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
Actor / Role:
grades_manager

Permission:
exam.enrollment.present

Present != student_grades.is_absent
Present does NOT mutate attendance.mark SSOT
Present does NOT mutate student grades automatically
Present is an explicit exam-enrollment/session lifecycle fact

Session precondition retained:
Confirmed → Present requires session InProgress
Scheduled → Present = NOT allowed
Present → Absent = allowed (Master Lock matrix retained)
Present does not by itself redefine HD-7.2-009 grade-entry timing
```

---

### HD-7.2-006 — Multiple sessions per subject

| Field | Value |
|-------|-------|
| **Selected option** | **A** — ALLOW MULTIPLE |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
No UNIQUE(exam_id, subject_id) in Phase 7.2
No uniqueness migration
```

---

### HD-7.2-007 — Move / Reassign

| Field | Value |
|-------|-------|
| **Selected option** | **A** — FORBIDDEN in Phase 7.2 |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
No Move/Reassign command
No Update reinterpretation of exam_session_id
```

---

### HD-7.2-008 — Grade correction after cancellation

| Field | Value |
|-------|-------|
| **Selected option** | **A** — Correct FAIL CLOSED if session OR exam Cancelled |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
Policy only — StudentGradeWriteGuard NOT modified by this task
No automatic grade mutation
```

---

### HD-7.2-009 — Grade Entry session timing

| Field | Value |
|-------|-------|
| **Selected option** | **C** with explicit status set |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
GRADE ENTRY ALLOWED:
InProgress
Completed

GRADE ENTRY DENIED:
Scheduled
Cancelled
```

Do not reinterpret or broaden. Do not modify enter guards in this task.

---

### HD-7.2-010 — Room / time overlap

| Field | Value |
|-------|-------|
| **Selected option** | **A** — hard overlap enforcement DEFERRED |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

---

### HD-7.2-012 — Seat number

| Field | Value |
|-------|-------|
| **Selected option** | **A** — optional free-text; no uniqueness; no auto-generation |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

---

### HD-7.2-013 — Room school isolation

| Field | Value |
|-------|-------|
| **Selected option** | **A** — fail-closed same-school validation when `room_id` set |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
If room_id != NULL:
  room.branch.school_id == current school_id
Failure = FAIL CLOSED (application/domain validation policy)

Do NOT create composite FK / migration / RLS change
Policy ≠ implementation authorization
```

---

### HD-7.2-014 — Timezone / session date semantics

| Field | Value |
|-------|-------|
| **Selected option** | **A** — school-local civil date/time |
| **Date** | 2026-09-12 |
| **Final status** | **HUMAN APPROVED / RESOLVED** |

```text
No UTC conversion / calendar redesign / timezone migration in Phase 7.2
```

---

## Section 5 — Critical Resolution

| Decision | Status |
|----------|--------|
| HD-7.2-001 | **RESOLVED** |
| HD-7.2-002 A | **RESOLVED** |
| HD-7.2-002 B | **RESOLVED** |
| HD-7.2-002 D | **RESOLVED** |
| HD-7.2-003 | **RESOLVED** |

```text
Critical package = RESOLVED (5 items)
```

---

## Section 6 — High Resolution

| Decision | Status |
|----------|--------|
| HD-7.2-004 | **RESOLVED** |
| HD-7.2-005 | **RESOLVED** |
| HD-7.2-006 | **RESOLVED** |
| HD-7.2-007 | **RESOLVED** |
| HD-7.2-008 | **RESOLVED** |
| HD-7.2-009 | **RESOLVED** |

```text
High package = RESOLVED (6 items)
```

---

## Section 7 — Medium Resolution

| Decision | Status |
|----------|--------|
| HD-7.2-010 | **RESOLVED** |
| HD-7.2-012 | **RESOLVED** |
| HD-7.2-013 | **RESOLVED** |
| HD-7.2-014 | **RESOLVED** |

```text
Medium package = RESOLVED (4 items)
```

---

## Section 8 — Architecture Resolutions

| ID | Status |
|----|--------|
| HD-7.2-011 (DD-017 Event causation) | **ARCHITECTURE RESOLUTION** — not converted to human approval |
| DD-019 (RLS verification) | **ARCHITECTURE RESOLUTION** — writer-path PostgreSQL tests required before future closure; **no RLS change authorized** |

---

## Section 9 — Conflict / Dependency Audit

### Counts

| Finding | Count |
|---------|------:|
| Conflicts | **0** |
| Partial decisions | **0** |
| Unauthorized inference | **0** |
| Dependency conflicts | **0** |
| Phase 7.1 conflicts | **0** |
| Missing approval | **0** |

### Permission chain

```text
HD-7.2-001 → grades_manager owns seven
HD-7.2-004 → register exactly seven (no exam.session.cancel)
HD-7.2-005 → grades_manager + exam.enrollment.present

Result: PASS
exam.session.cancel remains FORBIDDEN
```

### Cancellation chain

```text
002A=A1 + 002B=B1 + 002D=D1
    → 003 = FAIL CLOSED on CURRENT grade
    → 008 = Correct FAIL CLOSED on Cancelled session/exam

Result: PASS — no contradiction; no grade mutation on cancel
```

### Session / grade timing chain

```text
HD-7.2-005 Present ≠ is_absent; Present does not auto-mutate grades
HD-7.2-009 Enter only InProgress|Completed; deny Scheduled|Cancelled

Result: PASS — Present and Grade Entry not conflated
```

### Multiplicity / movement

```text
HD-7.2-006 ALLOW MULTIPLE sessions per subject
HD-7.2-007 Move/Reassign FORBIDDEN

Result: PASS — multiplicity does not authorize Move
```

### Room isolation

```text
HD-7.2-013 = fail-closed app/domain validation only
No composite FK authorized

Result: PASS
```

### Phase 7.1 / Master Lock reconciliation

| Constraint | Result |
|------------|--------|
| HD-001 does not auto-grant 7.2 perms | **PASS** — 7.2 ownership decided explicitly |
| HD-005 `exam.session.cancel` FORBIDDEN | **PASS** |
| HD-006 Present deferred → now resolved in 7.2 | **PASS** |
| DR-001 CancelExam no grade mutation | **PASS** |
| DR-002 CancelExamEnrollment vs CURRENT grade | **PASS** (aligned with 003) |
| DR-004 / no CompleteExam | **PASS** — not introduced |
| DR-006 event ≠ idempotency identity | **PASS** — architecture resolution retained |
| Phase 7.1 Final Closure | **PASS** — not reopened |
| Restore FORBIDDEN | **PASS** |
| CancelExam authoritative cascade | **PASS** — 002D D1 compatible |

### Previously deferred / out of scope (not reopened)

```text
HTTP
Query AuthZ
Bulk assignment
Capacity
Scheduled → Completed skip
Teacher/invigilator conflict scheduling
CompleteExam
```

---

## Section 10 — Design Lock Readiness

| Metric | Count |
|--------|------:|
| Critical unresolved | **0** |
| High unresolved | **0** |
| Medium unresolved | **0** |
| Material blockers | **0** |
| Human approvals evidenced | **15** |

```text
DESIGN LOCK:
ELIGIBLE TO BE REQUESTED
```

```text
IMPORTANT:
Design Lock is NOT automatically created or approved by this task.
Do NOT create 04-PHASE-7.2-DESIGN-LOCK.md in this task.
Implementation remains NOT AUTHORIZED.
```

---

## Section 11 — Authorization Boundary

```text
Implementation = NOT AUTHORIZED
Database changes = NOT AUTHORIZED
Application changes = NOT AUTHORIZED
Permissions = NOT AUTHORIZED
RLS = NOT AUTHORIZED
HTTP = NOT AUTHORIZED
```

```text
Human decision approval ≠ Implementation authorization
Human decision approval ≠ Design Lock creation
```

---

## Section 12 — Final Gate

```text
FINAL VERDICT:
HUMAN DECISIONS RESOLVED — DESIGN LOCK MAY BE REQUESTED

Design Lock artifact:
NOT CREATED
NOT AUTO-APPROVED

Implementation:
NOT AUTHORIZED

STOP (auto-create Design Lock / implement):
YES — do not proceed into Design Lock creation or implementation in this task

STOP (human-decision recording task completeness):
NO — recording + reconciliation completed without conflict
```

### Concise selection summary

| HD | Final recorded selection |
|----|--------------------------|
| HD-7.2-001 | Option A — `grades_manager` owns seven permissions |
| HD-7.2-002A | A1 — already Cancelled = idempotent no-op |
| HD-7.2-002B | B1 — withdraw active seats atomically |
| HD-7.2-002D | D1 — CancelExam skips already Cancelled |
| HD-7.2-003 | Option A — FAIL CLOSED on ANY CURRENT grade |
| HD-7.2-004 | Option A — register seven names; no `exam.session.cancel` |
| HD-7.2-005 | `grades_manager` + `exam.enrollment.present` (+ Present semantics) |
| HD-7.2-006 | Option A — ALLOW MULTIPLE sessions per subject |
| HD-7.2-007 | Option A — Move/Reassign FORBIDDEN |
| HD-7.2-008 | Option A — Correct FAIL CLOSED if session/exam Cancelled |
| HD-7.2-009 | Option C — Enter ALLOW InProgress\|Completed; DENY Scheduled\|Cancelled |
| HD-7.2-010 | Option A — hard overlap DEFERRED |
| HD-7.2-012 | Option A — optional free-text seat number |
| HD-7.2-013 | Option A — fail-closed room→branch→school match |
| HD-7.2-014 | Option A — school-local civil date/time |

```text
Next authorized human/architecture step (separate task):
request / author 04-PHASE-7.2-DESIGN-LOCK.md
ONLY after explicit Design Lock authorization request.

Do not implement Phase 7.2.
Do not register permissions yet.
Do not modify RLS / migrations / Grade guards / CancelExam.
```
