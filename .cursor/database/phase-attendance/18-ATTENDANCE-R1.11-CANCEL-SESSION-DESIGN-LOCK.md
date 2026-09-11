# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.11 — CANCEL SESSION DESIGN LOCK

**Document Type:** DESIGN LOCK + AUDIT ONLY  
**Date:** 2026-09-11  
**Implementation Authorization:** **NOT GRANTED**

**Upstream:** R1.3–R1.10 · ATT-D1/D2/D3 Design Lock · R1.8A Integrity · R1.9 Option B quarantine

```text
NO MIGRATIONS · NO DDL · NO CODE · NO PERMISSIONS · NO ROUTES · NO UI
CancelAttendanceSession MUST NOT be implemented in this phase.
```

---

## 1. Executive Summary

```text
STATUS: READY FOR HUMAN IMPLEMENTATION AUTHORIZATION

Cancel Attendance Session is fully design-locked below.
All required lifecycle, preservation, authz, concurrency, idempotency,
outbox, query, API, and R1.8A interaction decisions are LOCKED from
repository evidence + ATT-D1 semantics.

This document does NOT authorize implementation.
```

**Canonical capability name:** `CancelAttendanceSession`  
**Permission (design only):** `attendance.session.cancel`  
**Target status:** `3 = CANCELLED`  
**Event (design only):** `AttendanceSessionCancelled`

### One-line policy

```text
Cancel voids the SESSION for operations (terminal status=CANCELLED).
It does NOT delete attendance RECORDS or summaries.
It is irreversible on the same session row.
A new OPEN session with the same natural key MAY be created afterward
(R1.8A OPEN-only unique excludes CANCELLED).
```

---

## 2. Evidence Inventory

| Evidence | Source | Confidence |
|----------|--------|------------|
| Status vocabulary `{1,2,3}` | `SessionStatus` VO; R1.8A CHECK | **PROVEN** |
| No writer sets status=3 today | Create/Close/Mark/Correct handlers | **PROVEN** |
| Correct rejects CANCELLED | `CorrectAttendanceRecordHandler` + `SessionCancelledException` | **PROVEN** |
| Mark requires OPEN | `MarkSectionAttendanceHandler` | **PROVEN** |
| ATT-D1 CANCELLED meaning | Design Lock §2.1 — void ops; exclude operational reports; keep row/records | **PROVEN** (design) |
| ATT-D3 permission name reserved | `attendance.session.cancel` DEFERRED | **PROVEN** (design) |
| Close optimistic pattern | `closeSessionIfOpen` `WHERE status=OPEN` | **PROVEN** |
| R1.8A OPEN partial UNIQUE | `WHERE status=1` only | **PROVEN** |
| CLOSED + new OPEN allowed | R1.8A gate + tests | **PROVEN** |
| List supports optional `status` filter | `ListAttendanceSessionsQuery` / read repo | **PROVEN** |
| Live permissions | `config/security.php` — teacher: create/mark/close; manager: +correct | **PROVEN** |
| No cancel route | `routes/api.php` | **PROVEN** |
| No hard-delete academic facts | Constitution + RLS no DELETE policy | **PROVEN** |
| Correct requires non-empty reason | Handler + FormRequest pattern | **PROVEN** |
| Close idempotency optional | `CloseAttendanceSessionCommand` nullable key | **PROVEN** |
| Correct/Mark idempotency required | Handlers require key | **PROVEN** |

---

## 3. Lifecycle Decision Matrix

### 3.1 Authoritative transitions for Cancel

| From | To | Allowed | Reason |
|------|----|---------|--------|
| **OPEN** | **CANCELLED** | **YES** | Primary void path for mistaken/abandoned OPEN session (ATT-D1) |
| **CLOSED** | **CANCELLED** | **YES** | Void erroneous session after accidental Close; Cancel ≠ Correct; records preserved |
| **CANCELLED** | OPEN | **NO** | Terminal; no restore of same row; Reopen remains deferred/out of scope |
| **CANCELLED** | CLOSED | **NO** | Terminal; no reverse transition |
| CANCELLED | CANCELLED | **NO** (mutation) | Already cancelled — conflict unless idempotent replay |

### 3.2 Related transitions (unchanged / out of scope)

| From | To | Status |
|------|----|--------|
| OPEN | CLOSED | Remains authorized (`CloseAttendanceSession`) |
| CLOSED | OPEN | **Still deferred** (Reopen) — **not** unlocked by Cancel |
| — | new OPEN same natural key | **Allowed** via `CreateAttendanceSession` when no other OPEN exists (R1.8A) |

### 3.3 Irreversibility

| Question | Lock |
|----------|------|
| Is Cancel irreversible on the same session row? | **YES** |
| Can CANCELLED be restored to OPEN/CLOSED? | **NO** |
| Is a new OPEN session with same natural key allowed after Cancel? | **YES** (Create + R1.8A; distinct row) |

```text
Cancel voids THIS session identity.
Recreation = new CreateAttendanceSession (new id), not status restore.
```

---

## 4. Record Preservation Policy

### 4.1 Absolute rule

```text
Cancel SESSION ≠ delete RECORDS.
Hard-delete of attendance.records / sessions / summaries is FORBIDDEN.
```

### 4.2 Per-artifact policy

| Artifact | On Cancel | Lock |
|----------|-----------|------|
| `attendance.sessions` row | Remains; `status → 3` | **LOCKED** |
| Session metadata (`school_id`, year, section, subject, date, period, teacher) | **Immutable** (no rewrite) | **LOCKED** |
| `attendance.records` | **Preserved unchanged** | **LOCKED** |
| Record queryability (by session id / student history) | **Remain queryable** | **LOCKED** |
| `daily_section_summary` | **Not deleted; not zeroed by Cancel** | **LOCKED** |
| Summary meaning | Historical projection of records that still exist | **LOCKED** |
| Mark after Cancel | **Prohibited** (already: not OPEN) | **LOCKED** |
| Correct after Cancel | **Prohibited** (already coded) | **LOCKED** |
| Close after Cancel | **Prohibited** (Close only OPEN→CLOSED) | **LOCKED** |
| Outbox/audit history | Prior events retained; new cancel event appended | **LOCKED** |

### 4.3 Semantic distinction

| Operation | Effect |
|-----------|--------|
| **Cancel session** | Terminal ops void of marking context |
| **Correct record** | Audited change of a student’s mark |
| **Delete records** | **Never** |

---

## 5. Authorization / SoD Design

### 5.1 Permission (design-lock only — do not create now)

| Item | Lock |
|------|------|
| Name | `attendance.session.cancel` |
| Catalog status today | Named in ATT-D3 as DEFERRED — **not in** `Permission.php` / live grants yet |
| Sensitivity | **Yes** (voids session ops) |
| School isolation | Same as Close/Correct: `command.schoolId` must match Strategy A `sessions.school_id` (+ SchoolContext / FORCE RLS) |

### 5.2 Role eligibility (design-lock)

| Role class (live Option B pattern) | Cancel? |
|------------------------------------|---------|
| Viewer (`attendance.view` only) | **NO** |
| Teacher (`create`/`mark`/`close`) | **NO** |
| Manager (`+ attendance.correct`) | **YES** |

```text
SoD LOCK:
  cancel ⊄ mark
  cancel ⊄ close alone
  cancel granted with manager-class roles that already hold attendance.correct
  Creator-of-session is NOT sufficient without cancel permission
```

**Rationale:** Cancel is closer to Correct (elevated, reason-bearing, irreversible ops effect) than to Close (teacher-allowed soft terminal for marking).

### 5.3 Reason requirement

| Item | Lock |
|------|------|
| Cancel reason | **REQUIRED**, non-empty trimmed string |
| Mirror | Same posture as `CorrectAttendanceRecord` reason |

### 5.4 RLS

| Item | Lock |
|------|------|
| RLS / FORCE RLS change | **NOT REQUIRED** |
| Write | UPDATE `sessions` under existing school policy + GUC |
| DELETE policy | Remains absent |

---

## 6. Domain Command Contract

### 6.1 Command (conceptual — do not implement)

```text
CancelAttendanceSession
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `sessionId` | int | Yes | Target session |
| `schoolId` | int | Yes | SchoolContext / Strategy A match |
| `reason` | string | Yes | Non-empty after trim |
| `cancelledBy` | ?int | Yes (actor id; nullable only if system) | Audit |
| `idempotencyKey` | string | **Yes** | Sensitive write — required |

### 6.2 Expected preconditions

```text
session exists (visible under school)
session.school_id == schoolId
session.status IN (OPEN, CLOSED)
reason valid
actor authorized (policy + permission)
```

### 6.3 Result (conceptual)

```text
CancelAttendanceSessionResult
  sessionId: int
  previousStatus: int   # 1 or 2
  newStatus: int        # always 3
  fromIdempotencyCache: bool
```

### 6.4 Domain errors (conceptual codes)

| Condition | Error code (design) | HTTP (via existing renderer) |
|-----------|---------------------|------------------------------|
| Not found | `attendance.session_not_found` | 404 |
| Cross-school | `attendance.cross_school` (existing family) | 422 |
| Empty reason | `attendance.invalid_cancellation_reason` | 422 |
| Already CANCELLED (non-idempotent) | `attendance.session_cancel_conflict` | 409 |
| Status not OPEN/CLOSED | `attendance.session_cancel_conflict` | 409 |
| Unauthorized | framework 403 | 403 |

Reuse existing `SessionNotFoundException` / cross-school exceptions where possible; introduce cancel-specific conflict/reason exceptions only at implementation time.

---

## 7. Concurrency Contract

### 7.1 Authoritative write predicate

```text
UPDATE attendance.sessions
SET status = 3
WHERE id = :sessionId
  AND status IN (1, 2)          -- OPEN or CLOSED only
RETURNING id, status_before...  -- or app reads previous status under lock first
```

School match is enforced in Application **before** mutation (mirror Close), with FORCE RLS as defense-in-depth.

```text
FORBIDDEN as sole authority:
  UPDATE ... SET status = 3 WHERE id = ?   -- without status predicate
```

### 7.2 Outcomes

| Scenario | Behavior |
|----------|----------|
| OPEN → Cancel wins | status=3; outbox staged; success |
| CLOSED → Cancel wins | status=3; outbox staged; success |
| Already CANCELLED | 0 rows updated → **409** `session_cancel_conflict` (unless idempotent replay) |
| Concurrent Close vs Cancel on OPEN | Exactly one succeeds; loser gets conflict (Close needs OPEN; Cancel needs OPEN\|CLOSED) |
| Concurrent Mark vs Cancel on OPEN | Mark may commit if still OPEN at Mark lock time; after Cancel, further Marks fail `session_not_open` |
| Concurrent Correct vs Cancel on CLOSED | Correct may win if before Cancel; after Cancel, Correct fails `session_cancelled` |
| Stale client (believes OPEN, actually CANCELLED) | 409 conflict |

### 7.3 Locking posture

Mirror Mark/Correct: re-read/lock session inside UnitOfWork; then conditional status update; stage outbox atomically.

---

## 8. Idempotency Contract

| Case | Behavior |
|------|----------|
| 1. First Cancel (valid) | Persist status=3 + outbox; store idempotency payload |
| 2. Exact retry (same key + same command identity) | Return cached `CancelAttendanceSessionResult` (**200/success**), no second mutation |
| 3. Retry after already CANCELLED **without** matching cache | **409** cancel conflict |
| 4. Same key, different payload (e.g. different reason/session) | **Reject** per existing idempotency conflict conventions (do not silently apply) |
| 5. Concurrent Cancel same session | One commit; peer conflict or idempotent winner |

```text
Idempotency key: REQUIRED (sensitive void).
Command name constant: CancelAttendanceSession
Cache payload minimally: session_id, school_id, previous_status, new_status
```

---

## 9. Outbox / Event Contract

### 9.1 Event name (LOCKED)

```text
AttendanceSessionCancelled
```

Mandatory: **YES** (stage inside same UnitOfWork as status update).

### 9.2 Payload fields (LOCKED)

| Field | Required |
|-------|----------|
| `session_id` | Yes |
| `school_id` | Yes |
| `academic_year_id` | Yes |
| `previous_status` | Yes (1 or 2) |
| `new_status` | Yes (always 3) |
| `reason` | Yes |
| `cancelled_by` | Yes (nullable only if system actor allowed) |
| `occurred_at` | Yes |
| Correlation | Via platform CorrelationContext / outbox infra (existing) |

Idempotency key is stored in idempotency table, not necessarily duplicated in event payload (mirror Close/Correct practice).

---

## 10. Query / Reporting Semantics

### 10.1 Preserve current contracts (no silent break)

| Query | CANCELLED behavior (LOCK) |
|-------|---------------------------|
| `GetAttendanceSession` | **Returns** cancelled session by id (status visible in DTO) |
| `ListAttendanceSessions` | **No silent default exclude** — if `status` omitted, cancelled rows may appear (current behavior) |
| `ListAttendanceSessions?status=3` | Explicit include CANCELLED |
| `ListAttendanceSessions?status=1\|2` | Operational filter (client responsibility today) |
| `GetSectionAttendance` | Includes records tied to cancelled sessions for that section/date (**historical facts**) |
| `GetStudentAttendance` | Includes student marks from cancelled sessions (**historical facts**) |
| `GetDailySectionSummary` | Unchanged; summaries not rewritten by Cancel |

### 10.2 Operational reporting guidance (non-breaking)

```text
ATT-D1 intent: exclude CANCELLED from *operational* reports.
Implementation of default-exclude on List is a FUTURE optional API evolution
and requires explicit implementation authorization + consumer notice.
NOT part of the minimum Cancel command ship.
```

### 10.3 UI (deferred)

UI may hide Cancelled in operational lists while still allowing drill-down by id — **UI phase**, not this lock’s implementation.

---

## 11. API Contract (design only)

| Item | Lock |
|------|------|
| Method | `POST` |
| Route | `/api/attendance/sessions/{session}/cancel` |
| Auth | `auth:sanctum` + SchoolContext + `AttendancePolicy::cancelSession` (new method at implement time) |
| Permission | `attendance.session.cancel` |
| Body | `{ "reason": "<non-empty string>" }` |
| Prohibited body fields | `status`, `school_id`, identity fields (mirror Close FormRequest) |
| Header | `X-Idempotency-Key` **required** |
| Success (first) | `200` `{ data: { session_id, previous_status, new_status: 3 }, meta: { from_idempotency_cache: false, correlation_id } }` |
| Success (replay) | `200` + `from_idempotency_cache: true` |
| Already cancelled / invalid state | `409` + `attendance.session_cancel_conflict` |
| Empty reason | `422` |
| Unauthorized | `403` |
| Not found / cross-school | `404` / `422` per existing patterns |

**Do not create the route in R1.11.**

---

## 12. Database Impact

| Change type | Required for Cancel? |
|-------------|----------------------|
| Migration / DDL | **NO** |
| CHECK change | **NO** (already `status IN (1,2,3)`) |
| New index | **NO** (not justified) |
| New constraint | **NO** |
| Trigger | **NO** (transitions stay Application-owned) |
| RLS / FORCE RLS | **NO** |

```text
NO DATABASE DDL CHANGE REQUIRED
```

Application conditional UPDATE + existing CHECK + FORCE RLS are sufficient.

---

## 13. R1.8A Interaction

### 13.1 Sequences

| Sequence | Allowed? | Why safe |
|----------|----------|----------|
| OPEN → CANCELLED → **new OPEN** (same natural key) | **YES** | Partial UNIQUE applies only `WHERE status=1`; CANCELLED does not occupy OPEN slot |
| CLOSED → CANCELLED → **new OPEN** | **YES** | Same reason; CLOSED also outside OPEN unique |
| OPEN → CLOSED → **new OPEN** (no cancel) | **YES** | Already R1.8A locked |
| Two OPEN same key | **NO** | R1.8A rejects |

### 13.2 Rules preserved

```text
Do NOT alter R1.8A CHECK or OPEN unique index.
Cancel must not rewrite school_id / academic_year_id.
Cancel must not implement Reopen.
```

---

## 14. Future Test Matrix

### Domain

- OPEN → CANCELLED succeeds  
- CLOSED → CANCELLED succeeds  
- CANCELLED → Cancel again → conflict  
- Empty reason → reject  
- Mark on CANCELLED → reject  
- Correct on CANCELLED → reject  

### Authorization

- Manager with `attendance.session.cancel` succeeds  
- Teacher without cancel → 403  
- Viewer → 403  

### RLS

- Same-school cancel succeeds under GUC  
- Cross-school session id → deny / not found  

### Concurrency

- Stale CANCELLED → 409  
- Concurrent Close vs Cancel on OPEN → single winner  
- Concurrent Mark then Cancel → Mark only if still OPEN  
- Duplicate Cancel without idempotency → one success, one conflict  

### Idempotency

- First request success + store  
- Exact retry cached  
- Key reuse different payload → reject  

### Persistence

- `status=3`  
- Records row count unchanged  
- No DELETE  
- Outbox `AttendanceSessionCancelled` in same transaction  

### API

- 200 success shape  
- 403 unauthorized  
- 409 invalid/already cancelled  
- 422 empty reason  
- Cross-school denial  

**Do not write these tests in R1.11** (design only).

---

## 15. Final Decision Table

| Decision | Final Answer | Evidence | Status |
|----------|--------------|----------|--------|
| OPEN → CANCELLED | **Allowed** | ATT-D1 void semantics; primary use case | **LOCKED** |
| CLOSED → CANCELLED | **Allowed** | Void after mistaken Close; Cancel≠Correct; R1.8 optional matrix | **LOCKED** |
| CANCELLED → OPEN/CLOSED | **Forbidden** | Terminal; Reopen deferred | **LOCKED** |
| Cancel irreversible (same row) | **Yes** | ATT-D1 terminal | **LOCKED** |
| New OPEN after Cancel | **Allowed** via Create | R1.8A OPEN-only unique | **LOCKED** |
| Records preserved | **Yes** | Constitution; ATT-D1; FK RESTRICT | **LOCKED** |
| Summary preserved (not zeroed) | **Yes** | Historical projection; no delete | **LOCKED** |
| Mark/Correct after Cancel | **Forbidden** | Already coded for Correct; Mark OPEN-only | **LOCKED** |
| Cancel reason | **Required non-empty** | Mirror Correct | **LOCKED** |
| Permission | `attendance.session.cancel` | ATT-D3 name | **LOCKED** |
| SoD / roles | Manager-class (+correct); not teacher-only | Live security mapping pattern | **LOCKED** |
| Creator-only cancel | **No** | Elevated void | **LOCKED** |
| Event | `AttendanceSessionCancelled` mandatory | Outbox pattern Close/Correct | **LOCKED** |
| Idempotency | **Required** key | Sensitive write | **LOCKED** |
| Concurrency | `UPDATE … WHERE status IN (1,2)` | Close optimistic pattern | **LOCKED** |
| Query visibility | No silent List default change; by-id visible; history keeps records | Current read repo + ATT-D1 guidance | **LOCKED** |
| API | `POST …/sessions/{id}/cancel` + reason + idempotency | Close route analogy | **LOCKED** |
| DB DDL required | **NO** | CHECK already allows 3 | **LOCKED** |
| RLS change required | **NO** | Strategy A intact | **LOCKED** |
| Reopen unlocked? | **NO** | Explicitly out of scope | **LOCKED** |

### Residual human items (not TBDs in design — implement-gate checklist)

These are **authorization/catalog actions**, not open design questions:

1. Explicit phrase to implement Cancel only.  
2. Add permission constant + `config/security.php` manager grants at implement time.  
3. Optionally schedule a later API decision for List default-exclude CANCELLED (not required to ship Cancel).

---

## 16. Implementation Readiness

```text
READY FOR HUMAN IMPLEMENTATION AUTHORIZATION
```

All design decisions required to implement Cancel without ambiguity are locked.

```text
HUMAN IMPLEMENTATION AUTHORIZATION REQUIRED

Even though design is locked, R1.11 does NOT authorize:
  - creating CancelAttendanceSession
  - permission catalog changes
  - routes/controllers
  - events
  - tests beyond inspection
```

Suggested future authorization phrase (for humans, not executed now):

```text
APPROVED — IMPLEMENT ATTENDANCE CANCEL SESSION ONLY
```

---

## 17. Governance State

```text
AUDIT/DESIGN LOCK COMPLETE.

NO CANCEL IMPLEMENTATION AUTHORIZED.

NO DATABASE CHANGES AUTHORIZED.

NO API CHANGES AUTHORIZED.

NO PERMISSION CHANGES AUTHORIZED.

NO UI CHANGES AUTHORIZED.

NO LEGACY CLASS DELETION AUTHORIZED.

HUMAN IMPLEMENTATION AUTHORIZATION REQUIRED.

DO NOT AUTOMATICALLY START R1.12 IMPLEMENTATION.
```

---

## SIS CHANGE REPORT (R1.11 Design Lock)

```text
Status: PASS (design lock deliverable)
Risk: N/A

Application / Database / API / UI / Permissions Modified: NO

Files Added:
  .cursor/database/phase-attendance/18-ATTENDANCE-R1.11-CANCEL-SESSION-DESIGN-LOCK.md

Final Gate Status: READY FOR HUMAN IMPLEMENTATION AUTHORIZATION
Implementation authorized: NO
```
