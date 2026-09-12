# MASTER PHASE 7 — PHASE 7.2 — BATCH 6 — U09 DESIGN / AUTHORIZATION GATE

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Batch 6 — U09 Present Transition — Design / Authorization Gate |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Batch** | BATCH 6 (OPEN) |
| **Unit** | **U09 — Present transition (Confirmed → Present)** |
| **Document Type** | DESIGN / DISCOVERY / DECISION / AUTHORIZATION GATE ONLY |
| **Date** | 2026-09-12 |
| **Mode** | PRE-IMPLEMENTATION — NO CODE |
| **Design Lock** | `.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md` (§7, §10 HD-7.2-005) |
| **Gate matrix** | `.cursor/database/phase-7.2/05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` row **7.2-U09** |

```text
THIS DOCUMENT = U09 design discovery + decision gate
≠ Implementation Authorization
≠ Implementation executed
≠ Design Lock rewrite (references locked Present semantics; packaging resolved in 11)
≠ U10–U15
≠ Batch 6 closure

HUMAN DECISION RESOLUTION (authoritative for HD-U09-001..005):
.cursor/database/phase-7.2/11-PHASE-7.2-BATCH-6-U09-HUMAN-DECISION-RESOLUTION.md
Status: 5/5 HUMAN APPROVED — Implementation still NOT AUTHORIZED

FINAL DESIGN LOCK:
.cursor/database/phase-7.2/12-PHASE-7.2-BATCH-6-U09-FINAL-DESIGN-LOCK.md
Status: FINAL LOCKED — Implementation still NOT AUTHORIZED

HUMAN IMPLEMENTATION AUTHORIZATION REQUEST:
.cursor/database/phase-7.2/13-PHASE-7.2-BATCH-6-U09-HUMAN-IMPLEMENTATION-AUTHORIZATION-REQUEST.md
Status: READY FOR HUMAN DECISION — NOT YET AUTHORIZED
```

---

## 2. Authorization State

```text
Phase Authorization ≠ Batch Authorization ≠ Unit Authorization ≠ Implementation Authorization
```

| Scope | State |
|-------|-------|
| MASTER PHASE 7 | AUTHORIZED (program) |
| PHASE 7.2 Design Lock | APPROVED / AMENDED / LOCKED |
| PHASE 7.2 framework AuthZ (`06`) | APPROVED (framework ≠ U09 unit AuthZ) |
| BATCH 6 | **OPEN** (not closed; not a blank check for U09) |
| U08 | IMPLEMENTED + AUDITED — **PASS WITH CONDITIONS** |
| **U09** | **NOT AUTHORIZED** (Gate `7.2-U09` = NOT AUTHORIZED) |
| U10–U15 | **NOT AUTHORIZED** |
| U09 Implementation | **NOT GRANTED** by this document |

```text
Do NOT infer U09 AuthZ from Phase / Batch / U08 success / permission registration (U16) / exception text.
```

---

## 3. U09 Identity

```text
Unit:              U09 — Present transition (Confirmed → Present)
Phase:             7.2
Batch:             6
Domain:            Exams / Exam Enrollment lifecycle
Lifecycle object:  exams.exam_enrollments (seat)
Primary operation: Mark Confirmed seat as Present
Permission:        exam.enrollment.present → grades_manager
Current status:    DESIGN LOCKED (business semantics) / IMPLEMENTATION NOT STARTED
```

### Naming evidence (no identity conflict)

| Source | Name used |
|--------|-----------|
| Gate `7.2-U09` | Present transition / Present command/path |
| Design Lock §7 | Present transition; Confirmed → Present via `exam.enrollment.present` |
| Design Lock §10 | HD-7.2-005 Present Semantics |
| U07 exception | references command name **`PresentExamEnrollment`** (design hint only) |
| Master Lock (older) | Confirmed→Present via `exam.enrollment.update` — **SUPERSEDED** by Phase 7.2 HD-7.2-005 |

```text
U09 IDENTITY CONFLICT: NONE
Authoritative business identity = Present transition (Confirmed → Present)
Exact CQRS command class name: PresentExamEnrollmentCommand (HD-U09-001 HUMAN APPROVED)
```

---

## 4. Evidence Reviewed

| Artifact | Role |
|----------|------|
| `04-PHASE-7.2-DESIGN-LOCK.md` | Present semantics LOCKED; enrollment matrix |
| `05-…IMPLEMENTATION-AUTHORIZATION-GATE.md` | Unit matrix row **7.2-U09** NOT AUTHORIZED |
| `06-…HUMAN-IMPLEMENTATION-AUTHORIZATION-RECORD.md` | Present rules restated; ≠ U09 AuthZ |
| `04A` / `04B` | HD-7.2-005 APPROVED |
| `03-…HUMAN-DECISION-RESOLUTION.md` | HD-7.2-005 framing (historical) |
| `02` / `01` | Discovery / register (superseded where conflict) |
| `phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md` | Enums; older Present-via-update wording superseded |
| U07 `UpdateExamEnrollmentGuard` | Rejects Confirmed→Present; points to PresentExamEnrollment |
| U08 CancelExamEnrollment | Present ∈ active; Present→Withdrawn via U08 |
| `Permission.php` / `config/security.php` | `exam.enrollment.present` EXISTS (U16) |
| Application search | **No** PresentExamEnrollment Handler/Command implemented |

---

## 5. U01–U08 Context (concise)

| Unit | Operation | Entity | Transition | Permission | Event / cause (where locked) |
|------|-----------|--------|------------|------------|------------------------------|
| U01 | CreateExamSession | session | → Scheduled | session.create | SessionCreated |
| U02 | UpdateExamSession | session | metadata | session.update | SessionUpdated |
| U03 | OpenExamSession | session | Scheduled→InProgress | session.open | SessionOpened |
| U04 | CloseExamSession | session | InProgress→Completed | session.close | SessionClosed |
| U05 | CancelExamSession | session+seats | →Cancelled; actives→Withdrawn | session.update | SessionCancelled / EnrollmentCancelled `exam_session_cancel` |
| U06 | CreateExamEnrollment | seat | →Registered | enrollment.create | EnrollmentCreated `exam_enrollment_create` |
| U07 | UpdateExamEnrollment | seat | Confirm/Absent/seat_number | enrollment.update | EnrollmentUpdated `exam_enrollment_update` |
| U08 | CancelExamEnrollment | seat | active→Withdrawn | enrollment.cancel | EnrollmentCancelled `exam_enrollment_cancel` |
| **U09** | **Present** | **seat** | **Confirmed→Present** | **enrollment.present** | **ExamEnrollmentUpdated** / `exam_enrollment_present` (HD-U09-002/003) |

U08 condition retained (not a U09 blocker by itself):

```text
PARALLEL RACE VERIFICATION: NOT FULLY VERIFIED
```

---

## 6. Existing Locked Decisions (U09-relevant)

| Topic | Classification | Rule |
|-------|----------------|------|
| Transition | **EXISTING LOCKED DECISION** | Confirmed → Present only |
| Permission | **EXISTING LOCKED DECISION** | `exam.enrollment.present` / `grades_manager` |
| Not via Update | **EXISTING LOCKED DECISION** | Do NOT collapse into `exam.enrollment.update` (U07 enforces) |
| Session precondition | **EXISTING LOCKED DECISION** | Session **must be InProgress** |
| Scheduled → Present | **EXISTING LOCKED DECISION** | **FORBIDDEN** |
| Present ≠ is_absent | **EXISTING LOCKED DECISION** | Distinct facts |
| No attendance mutation | **EXISTING LOCKED DECISION** | Present does NOT mutate attendance.mark |
| No auto grade mutation | **EXISTING LOCKED DECISION** | Present does NOT automatically mutate grades |
| Present → Absent | **EXISTING LOCKED DECISION** | ALLOWED via **U07** (not U09) |
| Present → Withdrawn | **EXISTING LOCKED DECISION** | ALLOWED via **U08** / cascade |
| Restore | **EXISTING LOCKED DECISION** | Absent\|Withdrawn → Present **FORBIDDEN** |
| HTTP | **EXISTING LOCKED DECISION** | Phase 7.2 writers HTTP **OUT OF SCOPE** / deferred |
| DB migration | **EXISTING LOCKED DECISION** | Reuse `exam_enrollments.status`; no new schema for Present |
| RLS | **EXISTING LOCKED DECISION** | No RLS mutation; FORCE RLS reuse |
| Same-COMMIT writer pattern | **EXISTING LOCKED DECISION** | Key + fingerprint + outbox + idemp (DD-016 / §20) |
| Event ≠ idemp identity | **EXISTING LOCKED DECISION** | DR-006 |

---

## 7. Transition Matrix (U09)

| Source (enrollment) | Session | U09 | Classification |
|---------------------|---------|-----|----------------|
| Confirmed | InProgress | → Present **ALLOW** | **LOCKED** |
| Confirmed | Scheduled | DENY | **LOCKED** (Scheduled→Present forbidden) |
| Confirmed | Completed | DENY | **LOCKED** (InProgress required) |
| Confirmed | Cancelled | DENY | **LOCKED** (InProgress required; ≠ U08 Cancelled exception) |
| Registered | * | DENY | **LOCKED** (only Confirmed→Present) |
| Present | InProgress | IDEMPOTENT NO-OP | **LOCKED** (HD-U09-004 HUMAN APPROVED) |
| Absent | * | DENY | **LOCKED** (Restore forbidden) |
| Withdrawn | * | DENY | **LOCKED** (Restore forbidden) |

```text
U09 does NOT own Present → Absent (U07).
U09 does NOT own Present → Withdrawn (U08).
U09 does NOT reopen seats.
```

---

## 8. Grade Safety

| Grade state | U09 | Classification |
|-------------|-----|----------------|
| No grade | ALLOW (if Confirmed + InProgress) | **LOCKED** (no grade gate in HD-7.2-005) |
| Historical only | ALLOW (same) | **LOCKED** by omission of historical block |
| CURRENT grade | ALLOW (does not block) | **LOCKED** (HD-U09-005 HUMAN APPROVED) — still no grade mutation |
| Grade lookup failure | FAIL CLOSED | **LOCKED** posture (HD-U09-005) — do not treat as “no grade” |

```text
Present MUST NOT mutate / void / correct grades.
Present ≠ student_grades.is_absent.
EnterStudentGrade does NOT require Present (existing Enter rules unchanged by U09).
```

DR-002 (CURRENT grade blocks **CancelExamEnrollment**) does **NOT** automatically apply to U09 — different operation. Do not invent DR-002 for Present without HD.

---

## 9. Session-State Rules

```text
OPEN / InProgress:   REQUIRED for Confirmed → Present
CLOSED / Completed:  DENY Present
CANCELLED:           DENY Present
Scheduled:           DENY Present
```

```text
Do NOT copy U08 HD-U08-003 (Cancelled + active allowed) onto U09.
U09 requires InProgress exclusively (HD-7.2-005).
```

---

## 10. Security Model

| Element | Value | Status |
|---------|-------|--------|
| Permission | `exam.enrollment.present` | **LOCKED** + **EXISTS** (U16) |
| Role | `grades_manager` | **LOCKED** |
| Action enum | `PresentEnrollment` | **LOCKED** design (HD resolution §12; absent in code until AuthZ) |
| Authority port | ExamAdministrationAuthorityPort | **LOCKED pattern** |
| New permission | NOT REQUIRED | Do not recreate |
| `exam.session.cancel` | FORBIDDEN | unchanged |

School isolation (**LOCKED pattern** / **PROPOSED for U09**):

```text
same-school: ALLOW
cross-school: DENY FAIL CLOSED
missing SchoolContext: DENY FAIL CLOSED
school-scoped enrollment + session lock/find
```

---

## 11. Idempotency / Event / Outbox (design only)

### Idempotency

| Topic | Status |
|-------|--------|
| Required key + fingerprint + same-COMMIT | **LOCKED** (Phase 7.2 writer pattern) |
| same key + same payload → REPLAY | **LOCKED pattern** |
| same key + different payload → CONFLICT | **LOCKED pattern** |
| Fingerprint fields | **PROPOSED** reuse enrollment-writer: schema_version, exam_session_id, enrollment_id, exam_enrollment_id |
| Present → Present business no-op | **LOCKED** — IDEMPOTENT NO-OP (HD-U09-004) |

### Event / Outbox

| Topic | Status |
|-------|--------|
| Must emit on real Confirmed→Present | **LOCKED** intent (Gate lists event impact) |
| Event class | **LOCKED** — `ExamEnrollmentUpdated` (HD-U09-002) |
| Cause | **LOCKED** — `exam_enrollment_present` (HD-U09-003) |
| No emit on DENY / AuthZ fail / business no-op | **LOCKED** (HD-U09-004) |
| event identity ≠ idempotency identity | **LOCKED** (DR-006) |
| Reuse ExamEnrollmentCancelled | **NOT APPLICABLE** |
| Dedicated ExamEnrollmentPresented | **REJECTED** (HD-U09-002) |

Gate open choice *“Updated or dedicated”* — **RESOLVED** by `11` / HD-U09-002.

---

## 12. Concurrency (design)

| Pair | Expected (design) |
|------|-------------------|
| U09 + U09 | Row lock; one Present; second → no-op or conflict per HD-U09-004 / idempotency |
| U09 then U07 Absent | Present→Absent via U07 allowed after Present |
| U09 then U08 | Present→Withdrawn via U08 allowed |
| U05 then U09 | Session Cancelled / seats Withdrawn → U09 DENY (not InProgress / not Confirmed) |
| U08 then U09 | Withdrawn → U09 DENY |
| U07 Confirm then U09 | Happy path |

```text
deterministic sequential: designable with existing locks
true parallel race proof: NOT a locked release requirement for U09 gate
PARALLEL RACE VERIFICATION: NOT FULLY VERIFIED (inherited platform limitation; not invented as U09 blocker)
```

---

## 13. Database / RLS / HTTP / Architecture

| Area | Assessment |
|------|------------|
| Database | **DATABASE CHANGE NOT REQUIRED** — update `status` to Present |
| RLS | No change; reuse FORCE RLS |
| HTTP | **HTTP intentionally deferred** / OUT OF SCOPE for Phase 7.2 writers |
| CQRS | Command → Handler → Guard → MutationService → Repo → Outbox/Idemp (PROPOSED structure) |
| Capacity | **NOT APPLICABLE** / deferred |

---

## 14. Legacy / Alternate Write Paths

| Finding | Result |
|---------|--------|
| PresentExamEnrollment Handler | **ABSENT** |
| Confirmed→Present via U07 | **BLOCKED** in guard (correct) |
| Direct model Present writes in app | **None found** for Phase 7.2 Present command |
| Permission already registered | **EXISTS** — not an alternate write path |

```text
SECURITY / ARCHITECTURE BLOCKER for alternate Present write path: NONE detected
```

---

## 15. Conflicts

| ID | Sources | Conflict | Impact | Recommended HD |
|----|---------|----------|--------|----------------|
| C-U09-001 | Gate U09 vs Design Lock packaging | Event = Updated **or** dedicated | Blocks event implementation choice | HD-U09-002 |
| C-U09-002 | Master Lock §10.3 vs HD-7.2-005 | Present via `exam.enrollment.update` vs dedicated present | **RESOLVED** — Phase 7.2 supersedes | None |
| C-U09-003 | Exception text `PresentExamEnrollment` vs Gate “Present command/path” | Naming only | Low | HD-U09-001 |

---

## 16. Human Decision Register

**Resolution artifact:** `11-PHASE-7.2-BATCH-6-U09-HUMAN-DECISION-RESOLUTION.md`

| Decision ID | Priority | Decision | Status |
|-------------|----------|----------|--------|
| **HD-U09-001** | MEDIUM | Command = `PresentExamEnrollmentCommand` | **HUMAN APPROVED** |
| **HD-U09-002** | HIGH | Reuse `ExamEnrollmentUpdated` | **HUMAN APPROVED** |
| **HD-U09-003** | HIGH | Cause = `exam_enrollment_present` | **HUMAN APPROVED** |
| **HD-U09-004** | MEDIUM | Present → Present = idempotent no-op | **HUMAN APPROVED** |
| **HD-U09-005** | LOW | CURRENT grade does not block Present | **HUMAN APPROVED** |

```text
Human Decisions Required Before Implementation: 0
Resolved: 5/5
Unresolved: 0
```

---

## 17. Design Lock Proposal (U09 packaging)

| Item | Status |
|------|--------|
| Identity: Confirmed→Present via dedicated present permission | **LOCKED** |
| Session InProgress required | **LOCKED** |
| Scheduled/Completed/Cancelled Present | **LOCKED DENY** |
| No attendance / grade auto-mutation | **LOCKED** |
| Permission exists | **LOCKED** |
| Command name | **LOCKED** (HD-U09-001 HUMAN APPROVED) |
| Event class + cause | **LOCKED** (HD-U09-002 / HD-U09-003 HUMAN APPROVED) |
| Present→Present no-op | **LOCKED** (HD-U09-004 HUMAN APPROVED) |
| CURRENT grade does not block | **LOCKED** (HD-U09-005 HUMAN APPROVED) |
| CQRS shape | **LOCKED** pattern (follow U06–U08; AuthZ still required to implement) |
| HTTP | **LOCKED** deferred |
| DB | **LOCKED** none |
| U09 Implementation AuthZ | **NOT GRANTED** |

---

## 18. Design Readiness

```text
DESIGN READINESS: READY

Business Present semantics (HD-7.2-005): LOCKED
Implementation packaging (HD-U09-001..005): LOCKED / HUMAN APPROVED
Unresolved human decisions: 0

Eligible for: FINAL DESIGN LOCK / HUMAN IMPLEMENTATION AUTHORIZATION REQUEST
NOT eligible for: Implementation (AuthZ NOT GRANTED)
```

```text
Design Lock (Present business + packaging): LOCKED

U09 IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 19. Risk Register

| Risk | Severity |
|------|----------|
| Implementing Present via U07 update | CRITICAL if attempted — forbidden |
| Wrong event/cause conflating U07 update vs Present | HIGH |
| Allowing Present on Cancelled/Completed | HIGH |
| Attendance/grade side effects | HIGH |
| Treating U08 Cancelled-session allow as U09 allow | HIGH |
| Parallel race not proven | LOW (condition inherited; not U09 design blocker) |

---

## 20. Mandatory Safety Check (this task)

```text
Files modified outside .cursor/database/phase-7.2/: NONE (only this artifact created)
PHP files changed: NONE
Migration / RLS / Permissions / Routes / Tests / Events / Outbox / Database: NONE
```

---

## 21. Final Status Block

```text
U09 DESIGN / AUTHORIZATION GATE

Design Status:
READY
(packaging HD-U09-001..005 HUMAN APPROVED via artifact 11)

Design Lock:
LOCKED

Human Decisions Required:
0 (5/5 HUMAN APPROVED — see 11-PHASE-7.2-BATCH-6-U09-HUMAN-DECISION-RESOLUTION.md)

Implementation Authorization:
NOT GRANTED

Implementation:
NONE

Database Changes:
NONE

RLS Changes:
NONE

Permission Changes:
NONE

HTTP Changes:
NONE

Tests Added/Modified:
NONE

U10–U15:
NOT AUTHORIZED

Batch 6:
OPEN

NEXT REQUIRED GATE:
U09 FINAL DESIGN LOCK / HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

STOP — U09 DESIGN GATE + HUMAN DECISION RESOLUTION COMPLETE.
U09 IMPLEMENTATION NOT AUTHORIZED.
U10–U15 NOT AUTHORIZED.
BATCH 6 REMAINS OPEN.
```
