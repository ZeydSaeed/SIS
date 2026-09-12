# MASTER PHASE 7
# PHASE 7.2
# BATCH 6
# U13
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST → GRANTED

Unit:
U13 — Room school isolation

Gate:
7.2-U13

Phase:
7.2

Batch:
6

Mode:
IMPLEMENTATION AUTHORIZED BY HUMAN (2026-09-12)

Implementation:
AUTHORIZED (see audit artifact 22)

U12:
CLOSED / ACCEPTED WITH CONDITIONS
(see 20-PHASE-7.2-BATCH-6-U12-HUMAN-CLOSURE-REVIEW-RECORD.md)

U14 / U15:
NOT AUTHORIZED
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Gate row** | `05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` → **7.2-U13** |
| **Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` §15 **HD-7.2-013** |
| **HD approval** | `04B-PHASE-7.2-HUMAN-DECISION-APPROVAL-RECORD.md` — Option A |
| **Ballot** | `04A-PHASE-7.2-HUMAN-APPROVAL-BALLOT.md` — HD-7.2-013 |
| **Resolution source** | `03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md` — HD-7.2-013 (DD-025) |
| **This document** | Request only — ≠ authorization grant |

```text
Creating this request ≠ APPROVE U13
U12 closure ≠ U13 authorization
Phase / Batch authorization ≠ U13 unit authorization
Policy approval of HD-7.2-013 ≠ implementation authorization
```

---

## 1. Authorization State

```text
MASTER PHASE 7:
AUTHORIZED

PHASE 7.2:
AUTHORIZED / DESIGN LOCK

BATCH 6:
OPEN

U12: CLOSED / ACCEPTED WITH CONDITIONS
U13: IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
U13: Human decision: APPROVE U13 IMPLEMENTATION — HD-7.2-013 Option A
U14: NOT AUTHORIZED
U15: NOT AUTHORIZED
```

---

## 2. Design Source (authoritative)

| Artifact | Role for U13 |
|----------|----------------|
| Gate **7.2-U13** | Unit definition — Room school isolation |
| **HD-7.2-013** | Governing human decision (DD-025) |
| Design Lock §15 | Locked behavior text |
| 04B approval record | Option A — fail-closed room→branch→school |
| 04A ballot | Human selected Option A; policy ≠ AuthZ |
| HD-7.2-010 | Hard room/time overlap = **DEFERRED** (must not be implemented under U13) |

### Locked behavior (verbatim intent)

```text
If room_id is supplied:
  room.branch.school_id MUST equal current school_id

Failure = FAIL CLOSED
```

```text
Does NOT authorize:
  composite FK
  migration
  RLS change
  schema redesign
```

```text
Governing HD: HD-7.2-013
Competing U13 decisions: NONE (Option A locked)
Design ambiguity: NONE for this unit scope
```

---

## 3. Locked Scope

```text
REQUESTED AUTHORIZATION BOUNDARY (if later APPROVED):

MASTER PHASE 7
→ PHASE 7.2
→ BATCH 6
→ U13 — Room school isolation
   CreateExamSession / UpdateExamSession application-domain validation
   when room_id is supplied
```

### In scope (only if later authorized)

```text
Enforce / verify fail-closed same-school room ownership:

  room_id == null  → no room ownership check required by HD-7.2-013
  room_id != null  → room.branch.school_id MUST equal command school_id
                     else DENY / FAIL CLOSED

Surfaces:
  — CreateExamSession path (CreateExamSessionGuard / repository check)
  — UpdateExamSession path (when room_id is provided / non-null)

Repository check shape (locked evidence path):
  organization.rooms → organization.branches.school_id

Permission:
  existing exam.session.create / exam.session.update (no new permission)

Events:
  N/A for isolation deny (no session create/update success / no new event type)

Database:
  NONE — application check only
```

### Explicitly out of scope

```text
Composite (room_id, school_id) FK
Any migration / schema / index / RLS change
Hard room/time overlap enforcement (HD-7.2-010 DEFERRED)
Teacher/invigilator conflict scheduling
U14 Event causation
U15 RLS writer-path verification
U12 Correct Cancelled redesign
U09 / U10 / U11 semantic changes
New permissions
Attendance mutation
Grade mutation
HTTP/API redesign beyond existing Create/Update session commands
```

---

## 4. Read-Only Discovery Note (not authorization)

Inspection of current sources (documentation only — no code change in this task):

| Surface | Observation |
|---------|-------------|
| `CreateExamSessionGuard` | Calls `roomBelongsToSchool` when `$roomId !== null` |
| `UpdateExamSessionMutationService` | Calls `roomBelongsToSchool` when `room_id` provided and non-null |
| `EloquentExamRepository::roomBelongsToSchool` | Joins rooms → branches; filters `b.school_id` |
| Create Feature test | `room_from_other_school_fails_closed` exists |
| Update Feature test | `cross_school_room_fails_closed` exists |

```text
Discovery implication for a FUTURE authorized U13 task:
  Verify alignment of existing Create/Update checks + tests to HD-7.2-013.
  Close only proven gaps under AuthZ.
  Do NOT redesign room isolation.
  Do NOT treat partial presence of checks as substitute for human AuthZ grant.
  Do NOT add composite FK because checks already exist.
```

---

## 5. Required Implementation (IF human later grants AuthZ)

Exactly what Cursor would implement after a separate explicit APPROVE:

```text
1. Confirm CreateExamSession and UpdateExamSession fail closed when room_id
   belongs to another school (room → branch → school mismatch).
2. Confirm null room_id does not invent a room ownership requirement.
3. Confirm same-school room_id remains allowed under existing session rules.
4. Close any proven gap only within HD-7.2-013 (no broader redesign).
5. Preserve existing session create/update permissions, idempotency, outbox
   success paths for allowed updates.
6. Add/adjust tests only as needed to prove HD-7.2-013 (see § Testing).
7. Run architecture:validate --fitness, security:validate, Pint on touched files.
8. Produce U13 implementation audit artifact.
9. STOP — do not open U14/U15.
```

```text
Do NOT implement U13 until a separate human grant updates Authorization State.
```

---

## 6. Security

```text
Permission requirements:
  exam.session.create (Create path)
  exam.session.update (Update path)
  — existing catalog/roles; U13 does NOT register new permissions
  — Gate 7.2-U13: “Existing” session create/update

School isolation:
  When room_id supplied, room.branch.school_id MUST equal command school_id
  Cross-school room → DENY / FAIL CLOSED

Fail-closed requirements:
  Foreign / unknown / unresolvable room for school → DENY
  Do not treat missing room→branch→school proof as ALLOW

Authorization boundaries:
  Application/domain validation only
  No composite FK as substitute for AuthZ
  No RLS policy mutation under U13

Grade safety:
  U13 MUST NOT mutate student_grades
  U13 MUST NOT enter/correct/void grades

Attendance safety:
  U13 MUST NOT mutate attendance

Event / outbox / idempotency:
  On DENY: no successful Create/Update outcome; no ExamSessionCreated/Updated
  On ALLOW: existing Create/Update outbox + idempotency behavior unchanged
  U13 does NOT invent new event cause codes
```

---

## 7. Database

```text
Migrations: NOT AUTHORIZED
Schema changes: NOT AUTHORIZED
RLS changes: NOT AUTHORIZED
Index changes: NOT AUTHORIZED
Data migrations: NOT AUTHORIZED
Composite room–school FK: NOT AUTHORIZED
```

```text
U13 = application/domain check only (HD-7.2-013 / Design Lock §15).
No database change may be introduced by assumption.
```

---

## 8. HTTP / API

```text
HTTP route creation: NOT AUTHORIZED
New controllers / API resources: NOT AUTHORIZED
Contract redesign of Create/Update ExamSession: NOT AUTHORIZED
```

```text
U13 operates on existing Application CreateExamSession / UpdateExamSession
command paths only (if later authorized).
Do not invent a new HTTP surface for room isolation.
```

---

## 9. Testing (if later authorized)

Minimum proof required under a future AuthZ grant:

```text
CreateExamSession:
  — room_id from another school → FAIL CLOSED
  — same-school room_id → allowed (subject to other existing guards)
  — null room_id → not rejected solely for room isolation

UpdateExamSession:
  — room_id from another school → FAIL CLOSED
  — same-school room_id update → allowed (subject to other existing guards)
  — omit / null room_id → no invented cross-school deny

Regression:
  — U12 Correct Cancelled behavior unchanged
  — U09 Present / U10 CancelExam cascade / U11 Enter timing unchanged
  — No attendance mutation assertions
  — No unauthorized grade mutation

Validation commands (expected under AuthZ):
  — focused Feature tests for room isolation + related session regression
  — php artisan architecture:validate --fitness
  — php artisan security:validate
  — vendor/bin/pint --dirty (or project equivalent)
```

Existing tests that already exercise cross-school room deny (discovery only):

```text
tests/Feature/Exams/CreateExamSessionCommandTest.php
  → room_from_other_school_fails_closed

tests/Feature/Exams/UpdateExamSessionCommandTest.php
  → cross_school_room_fails_closed
```

A future authorized U13 task may retain, extend, or gap-close these proofs — not delete isolation coverage.

---

## 10. Dependencies

```text
U12: CLOSED / ACCEPTED WITH CONDITIONS — prerequisite for Batch 6 sequential AuthZ handoff
U13 functional dependency on U12 Correct Cancelled: NONE
U13 must not modify U12 semantics
HD-7.2-010 overlap: DEFERRED — must remain untouched
```

---

## 11. Forbidden Scope

```text
FORBIDDEN under this request and under any future narrow AuthZ unless separately amended:

* implementing U13 without a separate human APPROVE grant
* implementing U14
* implementing U15
* redesigning U13 beyond HD-7.2-013
* modifying U12 Correct Cancelled semantics
* modifying U09 / U10 / U11 semantics
* unrelated refactoring
* speculative database changes
* speculative RLS changes
* speculative permission changes
* composite room–school FK / uniqueness migrations
* hard room/time overlap enforcement (HD-7.2-010)
* attendance mutation
* unauthorized grade mutation
* new HTTP routes/controllers for this unit
```

---

## 12. STOP Conditions (pre-implementation)

If a future implementer discovers any of the following before coding, STOP and report — do not assume:

```text
* evidence conflicts with HD-7.2-013
* U13 design ambiguity / conflicting decisions
* need for design amendment
* need for unapproved DB / RLS / HTTP / permission change
* requirement to reopen U12
```

This authorization-request task found:

```text
U12 evidence: consistent — CLOSED WITH CONDITIONS
U13 design: LOCKED (HD-7.2-013 Option A) — DESIGN READY
Conflicting U13 decisions: NONE
Design amendment required: NO
Unapproved DB/RLS/HTTP required by lock: NO
```

---

## 13. Human Authorization Gate

```text
U13 IMPLEMENTATION AUTHORIZATION:

GRANTED (2026-09-12)

Human APPROVE for U13 ONLY — HD-7.2-013 Option A.
U14 / U15 remain NOT AUTHORIZED.
```

---

## 14. Post-Grant State

```text
U12: CLOSED / ACCEPTED WITH CONDITIONS
U13: AUTHORIZED → see 22-PHASE-7.2-BATCH-6-U13-IMPLEMENTATION-AUDIT.md
U14: NOT AUTHORIZED / NOT IMPLEMENTED
U15: NOT AUTHORIZED / NOT IMPLEMENTED
BATCH 6: OPEN
```
