# MASTER PHASE 7
# PHASE 7.2
# BATCH 6
# U14
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST → GRANTED

Unit:
U14 — Event causation verification

Gate:
7.2-U14

Phase:
7.2

Batch:
6

Mode:
IMPLEMENTATION AUTHORIZED BY HUMAN (2026-09-12)

Implementation:
AUTHORIZED (see audit artifact 25)

U13:
CLOSED / ACCEPTED WITH CONDITIONS
(see 23-PHASE-7.2-BATCH-6-U13-HUMAN-CLOSURE-REVIEW-RECORD.md)

U15:
NOT AUTHORIZED
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Gate row** | `05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` → **7.2-U14** |
| **Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` §19 **HD-7.2-011 / DD-017** |
| **HD classification** | **ARCHITECTURE RESOLUTION** (not a human ballot option set) |
| **Companion lock** | **DR-006** — event identity ≠ idempotency identity |
| **04B record** | HD-7.2-011 retained as Architecture Resolution |
| **Resolution source** | `03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md` — HD-7.2-011 |
| **This document** | Request only — ≠ authorization grant |

```text
Creating this request ≠ APPROVE U14
U13 closure ≠ U14 authorization
Phase / Batch authorization ≠ U14 unit authorization
Architecture Resolution of HD-7.2-011 ≠ U14 implementation authorization
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

U13: CLOSED / ACCEPTED WITH CONDITIONS
U14: IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
U14: Human decision: APPROVE U14 IMPLEMENTATION — HD-7.2-011 / DR-006
U15: NOT AUTHORIZED
```

---

## 2. Design Source

| Artifact | Role for U14 |
|----------|----------------|
| Gate **7.2-U14** | Unit definition — Event causation verification |
| **HD-7.2-011** / DD-017 | Governing architecture resolution |
| Design Lock §19 | Locked causation pattern |
| Design Lock §20 | Idempotency / outbox boundary (DR-006 / P7-D5) |
| **DR-006** | Event identity ≠ idempotency identity |
| 04B §8 | Confirms HD-7.2-011 as Architecture Resolution |
| U10 audit (`15-…`) | Cascade children already proven `cause=exam_cancel` (dependency evidence) |

### Selected option / resolution

```text
ARCHITECTURE RESOLUTION (Option A pattern — shared event classes):

Shared event classes where applicable
+ mandatory cause discriminator
+ outbox command_name

Conceptual causes (locked vocabulary):
  exam_cancel
  exam_session_cancel
  exam_enrollment_cancel

Do NOT create new event classes merely because of conceptual causes.
event identity != idempotency identity (DR-006)
```

```text
Competing U14 decisions: NONE
Human ballot options for HD-7.2-011: NOT APPLICABLE (Architecture Resolution)
Design ambiguity for verification scope: NONE
Note: DDR 7.2-DD-017 historical “UNRESOLVED” row is superseded by Design Lock §19
  + HD-7.2-011 Architecture Resolution + 04B — do not reopen.
```

---

## 3. Locked Scope

```text
REQUESTED AUTHORIZATION BOUNDARY (if later APPROVED):

MASTER PHASE 7
→ PHASE 7.2
→ BATCH 6
→ U14 — Event causation verification
   Outbox payload verification of cause + command_name
   Distinguish CancelExam cascade vs direct session/enrollment cancel causation
```

### Gate summary (authoritative)

| Field | Locked value |
|-------|----------------|
| Unit name | Event causation verification |
| Surface | Outbox payloads |
| DB / schema | None |
| Permission | N/A |
| RLS | N/A |
| Expected proof | `cause` + `command_name`; distinguish cascade vs direct |
| Governing | HD-7.2-011; DR-006 |
| Forbidden | Merge event id with idempotency id |

### In scope (only if later authorized)

```text
Verify (and gap-close only if locked discriminators are missing on already-authorized writers):

1. Outbox envelopes carry command_name consistent with existing Phase 7 pattern.
2. CancelExam cascade child events use cause = exam_cancel
   (preserve U10 / CancelExamHandler evidence; do not redesign CancelExam).
3. Direct CancelExamSession seat/session cancel path uses cause = exam_session_cancel
   (where that writer already exists under prior AuthZ).
4. Direct CancelExamEnrollment path uses cause = exam_enrollment_cancel
   (where that writer already exists under prior AuthZ).
5. Cascade vs direct are distinguishable by cause (not by inventing parallel event type trees).
6. event identity != idempotency identity (DR-006) — no merge of identities.
7. Shared event classes retained; do not create new event classes merely for causes.
```

### Explicitly out of scope

```text
U15 RLS writer-path verification
U13 room isolation redesign
U09 / U10 / U11 / U12 semantic changes
Redesign of audit.outbox_messages / audit.idempotency_keys
Post-commit idempotency store
New parallel event type trees (rejected unless Design Change Request)
Merging event identity with idempotency identity
New permissions
HTTP / routes / controllers
Database / RLS / schema / index / constraint / data migrations
Grade mutation
Attendance mutation
Implementing unfinished writers solely to invent causes
```

---

## 4. Required Implementation (IF human later grants AuthZ)

Exactly what Cursor would do after a separate explicit APPROVE:

```text
1. Inspect existing CancelExam / CancelExamSession / CancelExamEnrollment outbox staging
   against HD-7.2-011 cause vocabulary.
2. Add or extend Feature/integration tests that assert:
   — cascade children → cause exam_cancel
   — direct session cancel path → cause exam_session_cancel
   — direct enrollment cancel path → cause exam_enrollment_cancel
   — outbox command_name present / consistent with existing pattern
   — event identity ≠ idempotency key / identity
3. Gap-close payload discriminators ONLY where a locked writer already emits the event
   but omits the locked cause — without redesigning CancelExam or inventing new event classes.
4. Do not merge U14 into U10 redesign; consume U10 evidence; strengthen verification.
5. Run architecture:validate --fitness, security:validate, Pint on touched files.
6. Produce U14 implementation audit artifact.
7. STOP — do not open U15.
```

```text
Do NOT implement U14 until a separate human grant updates Authorization State.
```

---

## 5. Security

```text
Permission requirements:
  N/A for U14 as a verification unit (Gate: N/A)
  Do NOT add / rename / broaden permissions under U14

School isolation:
  U14 must not weaken school-scoped writer behavior
  Verification must not accept cross-school outbox staging as success

Fail-closed behavior:
  Causation ambiguity that would merge cascade with direct MUST fail verification
  Payload / identity conflicts remain fail-closed per existing DR-006 / idempotency locks

Authorization boundaries:
  Verification + minimal locked-cause gap-close only
  No new security model
  No RLS mutation

Grade safety:
  U14 MUST NOT mutate student_grades
  U14 MUST NOT void / enter / correct grades

Attendance safety:
  U14 MUST NOT mutate attendance

Event / outbox / idempotency:
  mandatory cause discriminator (locked vocabulary)
  outbox command_name (existing envelope pattern)
  event identity != idempotency identity (DR-006)
  Do not emit success events as part of “verification” side effects
  Do not create duplicate events for the same mutation
  Do not redesign outbox/idempotency infrastructure
```

---

## 6. Database

```text
Migrations: NOT AUTHORIZED
Schema changes: NOT AUTHORIZED
RLS changes: NOT AUTHORIZED
Index changes: NOT AUTHORIZED
Constraints: NOT AUTHORIZED
Data migrations: NOT AUTHORIZED
```

```text
U14 = outbox payload verification / discrimination proof only.
No database change may be introduced by assumption.
```

---

## 7. HTTP / API

```text
HTTP route creation: NOT AUTHORIZED
Controllers / API resources: NOT AUTHORIZED
HTTP contract changes: NOT AUTHORIZED
```

```text
U14 operates on Application/outbox verification of existing writers only
(if later authorized). Do not invent an HTTP surface for causation.
```

---

## 8. Testing

Minimum proof required under a future AuthZ grant:

```text
Causation distinction:
  — CancelExam cascade child ExamSessionCancelled / ExamEnrollmentCancelled
      → payload cause == exam_cancel
  — Direct CancelExamSession (or locked session-cancel writer) seat/session events
      → cause == exam_session_cancel (where applicable)
  — Direct CancelExamEnrollment
      → cause == exam_enrollment_cancel
  — Do NOT merge causes across cascade vs direct

Outbox envelope:
  — command_name present consistent with existing Phase 7 pattern

DR-006:
  — event identity ≠ idempotency identity (asserted; no merge)

Regression:
  — U10 CancelExam cascade coexistence unchanged
  — U13 room isolation unchanged
  — U09 Present / U11 Enter timing / U12 Correct Cancelled unchanged
  — No grade mutation introduced by U14
  — No attendance mutation introduced by U14

Validation commands (expected under AuthZ):
  — focused Feature tests for causation / outbox
  — php artisan architecture:validate --fitness
  — php artisan security:validate
  — vendor/bin/pint --dirty --test (or project equivalent)
```

Prior evidence U14 may consume (not substitute for AuthZ):

```text
15-PHASE-7.2-BATCH-6-U10-IMPLEMENTATION-AUDIT.md
  — cascade cause=exam_cancel proven
Design Lock §8 / §19
  — U05 cause=exam_session_cancel; U08 cause=exam_enrollment_cancel vocabulary locked
```

---

## 9. Dependencies

```text
U13: CLOSED / ACCEPTED WITH CONDITIONS — Batch 6 sequential handoff prerequisite
U14 functional dependency on U13 room isolation: NONE
U14 must not modify U13 semantics

Upstream causation writers (consume evidence; do not redesign):
  U10 / CancelExam cascade → exam_cancel
  U05 CancelExamSession path → exam_session_cancel (locked vocabulary)
  U08 CancelExamEnrollment → exam_enrollment_cancel (locked vocabulary)

Companion locks:
  HD-7.2-011 Architecture Resolution
  DR-006 event ≠ idempotency identity
  Design Lock §20 same-COMMIT outbox/idempotency pattern (verify; do not redesign)
```

---

## 10. Forbidden Scope

```text
FORBIDDEN under this request and under any future narrow AuthZ unless separately amended:

* implementing U14 without a separate human APPROVE grant
* implementing U15
* changing U13 semantics
* changing U09 / U10 / U11 / U12 semantics
* unrelated refactoring
* speculative database changes
* speculative RLS changes
* speculative permissions
* speculative HTTP changes
* grade mutation outside locked scope (U14 has none)
* attendance mutation outside locked scope (U14 has none)
* merging event identity with idempotency identity
* creating new event classes merely for conceptual causes
* redesigning CancelExam / outbox / idempotency infrastructure
```

---

## 11. STOP Conditions (pre-implementation)

If a future implementer discovers any of the following before coding, STOP and report — do not assume:

```text
* evidence conflicts with HD-7.2-011 / DR-006
* U14 design ambiguity / conflicting decisions
* need for design amendment
* need for unapproved DB / RLS / HTTP / permission change
* requirement to reopen U13
* product demand for separate event type trees (requires DCR — Option B rejected unless amended)
```

This authorization-request task found:

```text
U13 evidence: consistent — CLOSED WITH CONDITIONS
U14 design: LOCKED (HD-7.2-011 Architecture Resolution + DR-006) — DESIGN READY
Conflicting U14 decisions: NONE (DDR historical UNRESOLVED superseded by Design Lock)
Design amendment required: NO
Unapproved DB/RLS/HTTP required by lock: NO
```

---

## 12. Human Authorization Gate

```text
U14 IMPLEMENTATION AUTHORIZATION:

GRANTED (2026-09-12)

Human APPROVE for U14 ONLY — HD-7.2-011 / DR-006.
U15 remains NOT AUTHORIZED.
```

---

## 13. Post-Grant State

```text
U13: CLOSED / ACCEPTED WITH CONDITIONS
U14: AUTHORIZED → see 25-PHASE-7.2-BATCH-6-U14-IMPLEMENTATION-AUDIT.md
U15: NOT AUTHORIZED / NOT IMPLEMENTED
BATCH 6: OPEN
```
