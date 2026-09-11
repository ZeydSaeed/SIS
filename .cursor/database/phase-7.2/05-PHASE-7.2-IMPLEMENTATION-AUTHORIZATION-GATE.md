# MASTER PHASE 7 — PHASE 7.2 — HUMAN IMPLEMENTATION AUTHORIZATION GATE

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle — Implementation Authorization Gate |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Document Type** | IMPLEMENTATION AUTHORIZATION GATE ONLY |
| **Date** | 2026-09-12 |
| **Design Lock** | `.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md` — **APPROVED / LOCKED** |
| **Mode** | Gate creation only — **no implementation** |

```text
THIS DOCUMENT = authorization gate readiness record
≠ Implementation Authorization (human still PENDING)
≠ Design Lock (already APPROVED / LOCKED)
≠ permission registration
≠ migration / RLS / HTTP / application change
```

---

## 2. Gate vs Implementation Distinction

| Concept | State |
|---------|-------|
| **A. Implementation Authorization Gate** | **CREATED** (this artifact) |
| **B. Actual Implementation** | **NOT AUTHORIZED** |
| **Human Implementation Authorization** | **PENDING HUMAN APPROVAL** |

```text
Design Lock approval ≠ Implementation authorization
Human decision approval ≠ Implementation authorization
This gate ≠ Implementation authorization

Only an explicit human implementation authorization may authorize implementation.
```

```text
Implementation Authorization Gate = CREATED
Implementation = NOT AUTHORIZED
Human Implementation Authorization = PENDING
```

---

## 3. Design Lock Verification

| Check | Expected | Evidenced |
|-------|----------|-----------|
| Design Lock status | APPROVED / LOCKED | **PASS** (`04-PHASE-7.2-DESIGN-LOCK.md`) |
| Human decisions | 15/15 resolved | **PASS** (04B / Design Lock §4) |
| Critical | 5/5 resolved | **PASS** |
| High | 6/6 resolved | **PASS** |
| Medium | 4/4 resolved | **PASS** |
| Conflicts | 0 | **PASS** |
| Dependency conflicts | 0 | **PASS** |
| Phase 7.1 conflicts | 0 | **PASS** |

```text
DESIGN LOCK VERIFICATION = PASS
Gate may be created.
```

---

## 4. No Unauthorized Implementation Verification

Inspection date: **2026-09-12**.

| Area | Expected | Evidenced |
|------|----------|-----------|
| Database schema (Phase 7.2 writers) | UNCHANGED for 7.2 lifecycle | **PASS** — no new 7.2 schema work |
| Migrations | NOT AUTHORIZED / none for 7.2 writers | **PASS** |
| Application Phase 7.2 writers | ABSENT | **PASS** — no Create/Update/Open/Close/CancelExamSession or Create/Update/CancelExamEnrollment / Present handlers |
| Permission catalog | UNCHANGED | **PASS** — no `exam.session.*` / `exam.enrollment.*` in `config/security.php` |
| RLS policies | UNCHANGED | **PASS** — no 7.2 RLS mutations |
| HTTP lifecycle routes | NOT AUTHORIZED | **PASS** — only pre-existing grade GETs on exam-sessions/enrollments |
| Grade mutation guards | Not modified for 7.2 policies | **PASS** — `StudentGradeWriteGuard` not changed by 7.2 work |
| CancelExam | Phase 7.1 CLOSED surface | **PASS** — not redesigned for 7.2 |
| CancelExamSession command | ABSENT | **PASS** — only cascade event `ExamSessionCancelled` from CancelExam |

```text
Database: UNCHANGED
Application: UNCHANGED (no Phase 7.2 writer implementation)
Permissions: UNCHANGED
RLS: UNCHANGED
HTTP: NOT AUTHORIZED
Migrations: NOT AUTHORIZED
Implementation: NOT AUTHORIZED

UNAUTHORIZED IMPLEMENTATION DISCOVERED = NO
```

Pre-existing (not Phase 7.2 bypass): Grade read queries/routes; Phase 7.1 CancelExam cascade events with `cause: exam_cancel`; session/enrollment enums and schema from Phase 3A.

---

## 5. Binding Implementation Contract (from Design Lock)

Future implementation (only after human AuthZ) MUST obey the frozen Design Lock. Summary binding clauses:

### 5.1 Seven administration permissions

```text
exam.session.create
exam.session.update
exam.session.open
exam.session.close
exam.enrollment.create
exam.enrollment.update
exam.enrollment.cancel

Owner: grades_manager
exam.session.cancel = FORBIDDEN
CancelExamSession uses exam.session.update
```

### 5.2 Dedicated Present permission

```text
exam.enrollment.present
Owner: grades_manager

NOT counted among the seven administration permissions
Do NOT collapse into exam.enrollment.update
```

### 5.3 Cancellation

```text
Already Cancelled → idempotent no-op (HD-7.2-002A)
Active seats → Withdrawn atomically (HD-7.2-002B)
CancelExam skips already Cancelled sessions (HD-7.2-002D)
ANY CURRENT grade on session → CancelExamSession FAIL CLOSED (HD-7.2-003)
No grade deletion / voiding / conversion / automatic mutation on cancel
CancelExam remains authoritative (Phase 7.1)
```

### 5.4 Present lifecycle

```text
Confirmed → Present only if session = InProgress
Scheduled → Present = FORBIDDEN
Present → Absent = ALLOWED
Present ≠ student_grades.is_absent
Present MUST NOT mutate attendance.mark SSOT
Present MUST NOT automatically mutate grades
```

### 5.5 Multiple sessions / Move

```text
Multiple exam_sessions for same Exam + Subject = ALLOWED
No UNIQUE(exam_id, subject_id)
Move/Reassign = FORBIDDEN
Update ≠ Move; no exam_session_id reinterpretation as Move
```

### 5.6 Grade Entry / Correct policies (design)

```text
Enter ALLOW: InProgress | Completed
Enter DENY: Scheduled | Cancelled
Correct FAIL CLOSED if session OR exam Cancelled
Policy ≠ automatic guard modification without AuthZ
```

### 5.7 Room / seat / time / overlap

```text
room_id set → room.branch.school_id == current school_id (FAIL CLOSED)
No composite FK / migration / RLS for room isolation unless separately authorized
seat_number = optional free-text; no uniqueness; no auto-generation
school-local civil date/time; no UTC/timezone/calendar redesign
Hard room/time overlap = DEFERRED
```

### 5.8 Events / idempotency / RLS verification

```text
Shared event classes where applicable + mandatory cause + outbox command_name
Causes: exam_cancel | exam_session_cancel | exam_enrollment_cancel
event identity ≠ idempotency identity
same-COMMIT mutation + outbox + idempotency + fingerprint
No RLS mutation; writer-path PostgreSQL tests required before future closure
```

### 5.9 Phase 7.1 inherited locks

```text
HD-001, HD-005, HD-006, DR-001, DR-002, DR-004, DR-006
Phase 7.1 Final Closure, Master Phase 7 Design Lock
No hard-delete of protected academic facts
Fail-closed security / tenant boundaries preserved
```

---

## 6. Explicitly NOT AUTHORIZED (Out of Scope)

```text
HTTP exposure for Phase 7.2 writers
Query AuthZ
Bulk assignment
Capacity enforcement
Scheduled → Completed skip
Teacher/invigilator conflict scheduling
CompleteExam
Restore
exam.session.cancel
Hard room/time overlap enforcement
Move/Reassign
Composite room FK / uniqueness migrations
UTC / timezone / calendar redesign
Permission/role registration in this gate task
RLS mutation in this gate task
Any implementation before explicit human implementation authorization
```

---

## 7. Implementation Units Matrix

Authorization status for every unit below:

```text
NOT AUTHORIZED — pending explicit human implementation authorization
```

| Unit ID | Domain capability | Expected command/handler/service area | DB impact | Permission impact | RLS impact | Event/outbox impact | Expected tests | Dependencies | Forbidden behavior | AuthZ status |
|---------|-------------------|----------------------------------------|-----------|-------------------|------------|---------------------|----------------|--------------|--------------------|--------------|
| **7.2-U01** | Session create | `CreateExamSession` + handler + repo | Insert `exam_sessions` | `exam.session.create` | Subject to FORCE RLS | SessionCreated + same-COMMIT idemp | Feature AuthZ/isolation; schema checks | Parent exam not Cancelled; year via exam | Cross-school; unique subject constraint | **NOT AUTHORIZED** |
| **7.2-U02** | Session update | `UpdateExamSession` + DR-003 allowlist | Update allowlisted cols | `exam.session.update` | FORCE RLS | SessionUpdated | Allowlist/immutability tests | Scheduled-only fields; CURRENT grade freezes max/pass | Status via Update; Move | **NOT AUTHORIZED** |
| **7.2-U03** | Session open | `OpenExamSession` | status → InProgress | `exam.session.open` | FORCE RLS | SessionOpened | Transition tests | Scheduled only; parent not Cancelled | Open from terminal | **NOT AUTHORIZED** |
| **7.2-U04** | Session close | `CloseExamSession` | status → Completed | `exam.session.close` | FORCE RLS | SessionClosed | Transition tests | InProgress only | Scheduled→Completed skip; auto CompleteExam | **NOT AUTHORIZED** |
| **7.2-U05** | Session cancel | `CancelExamSession` | status → Cancelled; withdraw seats | `exam.session.update` | FORCE RLS | SessionCancelled cause=`exam_session_cancel` + enrollment cancel events | Idempotent cancel; CURRENT-grade fail-closed; seat withdraw atomic | 002A/B; 003 | `exam.session.cancel`; grade mutation; restore | **NOT AUTHORIZED** |
| **7.2-U06** | Enrollment create | `CreateExamEnrollment` | Insert seat | `exam.enrollment.create` | FORCE RLS | EnrollmentCreated | Year-match fail-closed; unique race | Year match; session not Cancelled | Cross-year; Move | **NOT AUTHORIZED** |
| **7.2-U07** | Enrollment update | `UpdateExamEnrollment` narrow | Status/fields allowlist | `exam.enrollment.update` | FORCE RLS | EnrollmentUpdated | Transition matrix | Session not Cancelled for confirm | Present via update; Move session_id | **NOT AUTHORIZED** |
| **7.2-U08** | Enrollment cancel | `CancelExamEnrollment` | → Withdrawn | `exam.enrollment.cancel` | FORCE RLS | EnrollmentCancelled cause=`exam_enrollment_cancel` | DR-002 CURRENT-grade fail-closed | DR-002 | Grade mutation; Restore | **NOT AUTHORIZED** |
| **7.2-U09** | Present transition | Present command/path | Confirmed→Present | `exam.enrollment.present` | FORCE RLS | EnrollmentUpdated or dedicated present event (design) | InProgress required; Scheduled forbidden | HD-7.2-005 | attendance.mark; auto grade; Scheduled Present | **NOT AUTHORIZED** |
| **7.2-U10** | CancelExam cascade coexistence | Integration with Phase 7.1 CancelExam | Skip already Cancelled; cascade remainder | `exam.cancel` (7.1) | Existing | cause=`exam_cancel` | D1 skip; no double semantics | Phase 7.1 CLOSED | Redesign CancelExam; grade mutation | **NOT AUTHORIZED** |
| **7.2-U11** | Grade entry timing policy | Enter path enforcement | None (guard) | existing grades.* | Existing | N/A | Enter allow InProgress\|Completed; deny Scheduled\|Cancelled | HD-7.2-009 | Broaden to all non-cancelled | **NOT AUTHORIZED** |
| **7.2-U12** | Grade correction policy | Correct path enforcement | None (guard) | existing grades.correct | Existing | N/A | Correct fail-closed if session/exam Cancelled | HD-7.2-008 | Silent guard change without AuthZ | **NOT AUTHORIZED** |
| **7.2-U13** | Room school isolation | Create/Update session validation | None (app check) | session create/update | Existing | N/A | Cross-school room fail-closed | HD-7.2-013 | Composite FK without AuthZ | **NOT AUTHORIZED** |
| **7.2-U14** | Event causation verification | Outbox payloads | None | N/A | N/A | cause + command_name | Distinguish cascade vs direct | HD-7.2-011; DR-006 | Merge event id with idempotency id | **NOT AUTHORIZED** |
| **7.2-U15** | RLS writer-path verification | PG tests | None | N/A | Verify only | N/A | same/cross school; missing GUC; FORCE | DD-019 | RLS policy mutation | **NOT AUTHORIZED** |
| **7.2-U16** | Permission/role registration | `config/security.php` + catalog | None | Register 7+present → grades_manager | N/A | N/A | security:validate; AuthZ negatives | HD-7.2-001/004/005 | `exam.session.cancel` | **NOT AUTHORIZED** |

---

## 8. Database Change Matrix

| Area | Change Required | Authorized Now |
|------|-----------------|----------------|
| Tables | Derived from locked design only (existing `exam_sessions` / `exam_enrollments`) — no new tables required by lock | **NO** |
| Columns | No `updated_at` / year denorm / capacity required by lock | **NO** |
| Constraints | No UNIQUE(exam_id, subject_id); no room overlap exclusion | **NO** |
| Indexes | Measure-first only if later authorized | **NO** |
| Migrations | Implementation phase only (after human AuthZ) | **NO** |
| RLS | No mutation; verification tests later | **NO** |
| Permissions | Implementation phase only (after human AuthZ) | **NO** |

```text
This gate itself must not change the database.
```

---

## 9. Security / Authorization Matrix

| Class | Names | Owner | Catalog now | Gate AuthZ |
|-------|-------|-------|-------------|------------|
| Seven administration | `exam.session.create\|update\|open\|close`, `exam.enrollment.create\|update\|cancel` | `grades_manager` | **ABSENT** | Registration **NOT AUTHORIZED** |
| Dedicated Present | `exam.enrollment.present` | `grades_manager` | **ABSENT** | Registration **NOT AUTHORIZED** |
| Forbidden | `exam.session.cancel` | — | Must never be introduced | **FORBIDDEN** |
| Phase 7.1 (unchanged) | `exam.create\|update\|cancel` | `grades_manager` | Present | Closed surface |

```text
No permission registration occurs in this task.
```

---

## 10. Human Authorization Requirement

```text
This document does NOT authorize implementation.

Implementation Authorization:
PENDING HUMAN APPROVAL
```

Do **not** write `IMPLEMENTATION AUTHORIZED`.

Do **not** infer authorization from Design Lock, human decisions, this gate, Cursor instructions, or prior phase authorization.

Only an **explicit human implementation authorization** may authorize implementation.

---

## 11. STOP Conditions

**STOP** (do not implement / do not treat as authorized) if:

| # | Condition |
|---|-----------|
| 1 | Design Lock is not APPROVED / LOCKED |
| 2 | Any human decision unresolved |
| 3 | Any conflict exists |
| 4 | Phase 7.1 conflict exists |
| 5 | Unauthorized implementation discovered |
| 6 | Proposed implementation contradicts Design Lock |
| 7 | Required dependency unresolved |
| 8 | Permission model ambiguous |
| 9 | `exam.session.cancel` introduced |
| 10 | Move/Reassign introduced |
| 11 | Present coupled to attendance SSOT |
| 12 | Present automatically mutates grades |
| 13 | Cancellation mutates grades |
| 14 | Grade guard silently changed without AuthZ |
| 15 | RLS modified without authorization |
| 16 | Migration created during this gate task |

```text
Current STOP evaluation for GATE CREATION:
Design Lock PASS · Decisions PASS · Conflicts 0 · No unauthorized implementation
→ Gate CREATED successfully

STOP BEFORE IMPLEMENTATION = YES
Human Implementation Authorization = PENDING
```

---

## 12. Artifact Integrity

```text
This task creates/modifies ONLY:
.cursor/database/phase-7.2/05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md

Does NOT modify:
01, 02, 03, 04A, 04B, 04 Design Lock
application / database / config / routes / tests behavior
```

---

## 13. Final Gate State

```text
Design Lock: APPROVED / LOCKED
Implementation Authorization Gate: CREATED
Human Implementation Authorization: PENDING
Implementation: NOT AUTHORIZED

Database: UNCHANGED
Application: UNCHANGED
Permissions: UNCHANGED
RLS: UNCHANGED
HTTP: NOT AUTHORIZED
Migrations: NOT AUTHORIZED

STOP BEFORE IMPLEMENTATION: YES
```

```text
Do not implement Phase 7.2.
Do not create migrations.
Do not register permissions.
Do not modify code or RLS.
Do not create HTTP endpoints.
Await explicit human implementation authorization.
```
