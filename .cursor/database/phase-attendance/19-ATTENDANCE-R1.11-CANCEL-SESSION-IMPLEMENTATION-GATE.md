# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.11 — CANCEL SESSION IMPLEMENTATION GATE

**Document Type:** IMPLEMENTATION GATE  
**Date:** 2026-09-11  
**Authoritative design lock:** `18-ATTENDANCE-R1.11-CANCEL-SESSION-DESIGN-LOCK.md`

---

## 1. Authorization Confirmation

```text
APPROVED — IMPLEMENT ATTENDANCE CANCEL SESSION ONLY.

R1.11 DESIGN LOCK IS AUTHORITATIVE.

IMPLEMENTATION IS AUTHORIZED.

R1.12 IS NOT AUTHORIZED.
REOPEN IS NOT AUTHORIZED.
UNRELATED CHANGES ARE NOT AUTHORIZED.
```

Authorization applied **only** to Cancel Attendance Session (R1.11).

---

## A. Scope

### Implemented

| Capability | Status |
|------------|--------|
| `CancelAttendanceSession` command + handler + result | ✅ |
| Lifecycle `OPEN(1)→CANCELLED(3)` and `CLOSED(2)→CANCELLED(3)` | ✅ |
| Conditional session mutation (`status IN (1,2)` / previous-status predicate) | ✅ |
| Permission `attendance.session.cancel` (manager-class) | ✅ |
| `AttendancePolicy::cancelSession` | ✅ |
| Mandatory non-empty `reason` | ✅ |
| Required idempotency (`X-Idempotency-Key`) | ✅ |
| Outbox `AttendanceSessionCancelled` in same UoW as mutation | ✅ |
| HTTP `POST /api/v1/attendance/sessions/{session}/cancel` | ✅ |
| Record / summary / session-row preservation (status-only mutation) | ✅ |
| Focused unit / feature / PostgreSQL tests | ✅ |

### Explicitly NOT implemented

| Item | Status |
|------|--------|
| R1.12 | NOT IMPLEMENTED |
| Reopen Session | NOT IMPLEMENTED |
| `CANCELLED → OPEN` / `CANCELLED → CLOSED` | NOT IMPLEMENTED |
| UI | NOT IMPLEMENTED |
| ListAttendanceSessions default filter change | NOT CHANGED |
| Reporting semantics | NOT CHANGED |
| ATT-D1/D2/D3 | NOT CHANGED |
| R1.8A migration / unique / CHECK | NOT MODIFIED |
| Session status CHECK constraint | NOT CHANGED |
| New migrations / indexes / RLS / FORCE RLS | NONE |
| Legacy class deletion | NOT DONE |
| Unrelated permission / API / attendance changes | NOT DONE |

---

## B. Files changed (R1.11 Cancel Session)

### Domain

- `app/Domain/Attendance/Events/AttendanceSessionCancelled.php`
- `app/Domain/Attendance/Exceptions/InvalidCancellationReasonException.php`
- `app/Domain/Attendance/Exceptions/SessionCancelConflictException.php`
- `app/Domain/Attendance/Exceptions/IdempotencyPayloadConflictException.php`
- `app/Domain/Attendance/Repositories/AttendanceWriteRepositoryInterface.php` (+ `cancelSessionIfOpenOrClosed`)

### Application (CQRS)

- `app/Application/Attendance/Commands/CancelAttendanceSessionCommand.php`
- `app/Application/Attendance/Commands/CancelAttendanceSessionHandler.php`
- `app/Application/Attendance/Results/CancelAttendanceSessionResult.php`

### Infrastructure

- `app/Infrastructure/Persistence/Attendance/EloquentAttendanceWriteRepository.php` (`cancelSessionIfOpenOrClosed`)
- `app/Infrastructure/Persistence/Outbox/EloquentOutboxRepository.php` (stage/rehydrate `AttendanceSessionCancelled`)

### Security

- `app/Security/Authorization/Permission.php` (`ATTENDANCE_SESSION_CANCEL`)
- `config/security.php` (permission catalog + `attendance_manager` grant)
- `app/Security/Policies/AttendancePolicy.php` (`cancelSession`)

### HTTP

- `app/Http/Requests/Attendance/CancelAttendanceSessionRequest.php`
- `app/Http/Controllers/Api/AttendanceController.php` (`cancel`)
- `routes/api.php` (`api.attendance.sessions.cancel`)

### Tests

- `tests/Unit/Attendance/CancelAttendanceSessionHandlerTest.php`
- `tests/Unit/Security/AttendanceAuthorizationTest.php` (cancel assertions)
- `tests/Feature/Attendance/CancelAttendanceSessionHttpTest.php`
- `tests/Feature/Security/AttendanceApiAuthorizationTest.php` (teacher vs manager cancel)
- `tests/Feature/Database/PostgreSql/AttendanceCancelSessionPostgreSqlTest.php`

### Docs (SSOT touch)

- `.cursor/architecture/features/Attendance.md` (Cancel ✅; Reopen still deferred)

### Database

- **No migration files added or modified for R1.11.**

---

## C. Permission

```text
attendance.session.cancel
```

| Surface | Detail |
|---------|--------|
| Catalog | `config/security.php` → `permissions` |
| Constant | `Permission::ATTENDANCE_SESSION_CANCEL` |
| Role grant | **`attendance_manager` only** |
| Teacher | **not granted** |
| Viewer | **not granted** |
| Policy | `AttendancePolicy::cancelSession` |
| HTTP | `CancelAttendanceSessionRequest::authorize()` → `can('cancelSession', $session)` |

Intended manager posture (design lock): manager-class + `attendance.correct` + `attendance.session.cancel`.

---

## D. CQRS write path

```text
HTTP POST /api/v1/attendance/sessions/{session}/cancel
  ↓ CancelAttendanceSessionRequest (reason + X-Idempotency-Key; status/school_id prohibited)
  ↓ AttendancePolicy::cancelSession
  ↓ CancelAttendanceSessionCommand
       (sessionId, schoolId from SchoolContext, reason, cancelledBy, idempotencyKey)
  ↓ CancelAttendanceSessionHandler
  ↓ Idempotency find / payload match
  ↓ School ownership check (Strategy A)
  ↓ UnitOfWork.transaction
       → cancelSessionIfOpenOrClosed (conditional)
       → Outbox stage AttendanceSessionCancelled
  ↓ Idempotency store
  ↓ CancelAttendanceSessionResult
```

---

## E. Lifecycle

| Transition | Result |
|------------|--------|
| OPEN (1) → CANCELLED (3) | ✅ success |
| CLOSED (2) → CANCELLED (3) | ✅ success |
| CANCELLED (3) → CANCELLED (without matching idempotency cache) | ❌ `attendance.session_cancel_conflict` (409) |
| CANCELLED → OPEN / CLOSED | ❌ not implemented (terminal for existing row) |

After cancel, existing guards continue to reject Mark / Correct / Close on CANCELLED sessions (`SessionNotOpenException` / `SessionCloseConflictException` / cancelled session guards).

R1.8A OPEN-only partial unique still allows a **new** OPEN session with the same natural key after cancel (proven in PG test).

---

## F. Concurrency

Repository mutation (`EloquentAttendanceWriteRepository::cancelSessionIfOpenOrClosed`):

```text
1. SELECT … WHERE id = :id AND status IN (1,2) FOR UPDATE
2. UPDATE … WHERE id = :id AND status = :previousStatus SET status = 3
3. Return previousStatus or null if no row / race lost
```

Equivalent to the locked shape:

```sql
UPDATE attendance.sessions
SET status = 3
WHERE id = :sessionId
  AND status IN (1, 2)
-- with lockForUpdate + previous-status recheck
```

**Not** a blind `UPDATE … WHERE id = :sessionId`.

Evidence:

- Cancel then Close → Close conflict (cancel winner)
- Close then Cancel → Cancel succeeds (`previous_status = 2`)
- Stale second Cancel without cache → 409 / `SessionCancelConflictException`
- Exact idempotent replay → no second mutation

---

## G. Idempotency

Command identity: `CancelAttendanceSession`

| Scenario | Behavior | Evidence |
|----------|----------|----------|
| First request | mutate + store cache; `from_idempotency_cache=false` | Unit + HTTP + PG |
| Exact retry (same key + payload) | cached result; no UoW; `from_idempotency_cache=true` | Unit + HTTP |
| Same key / different payload | `attendance.idempotency_payload_conflict` (409) | Unit |
| Already CANCELLED without matching cache | `attendance.session_cancel_conflict` (409) | Unit + HTTP + PG |
| Required key missing / blank | 422 (`FormRequest` / `attendance.idempotency_key_required`) | Unit + HTTP |

Idempotency store follows existing attendance convention: **after** successful UoW (same as Close/Correct).

---

## H. Outbox

Event: `App\Domain\Attendance\Events\AttendanceSessionCancelled`

Staged **inside** the same `UnitOfWork` transaction as the conditional status update.

Payload fields:

```text
session_id
school_id
academic_year_id
previous_status   (1 or 2)
new_status        (3)
reason
cancelled_by
occurred_at
```

Correlation is persisted by existing outbox infrastructure (`correlation_id` column via `CorrelationContext` at stage time) — not imported into Application layer.

Rehydrate arm added in `EloquentOutboxRepository`.

PG evidence: `audit.outbox_messages` row with `event_type = AttendanceSessionCancelled` and matching `payload.session_id`.

---

## I. Preservation

Cancel mutates **only** `attendance.sessions.status` (1/2 → 3).

Verified (PG `cancel_preserves_records_and_blocks_mark`):

| Artifact | After cancel |
|----------|--------------|
| Session row | preserved (`status = 3`) |
| `attendance.records` count | unchanged |
| `daily_section_summary.present_count` | unchanged |
| DELETE | none performed by cancel path |

---

## J. API

```http
POST /api/v1/attendance/sessions/{session}/cancel
Authorization: auth:sanctum + SchoolContext
Permission: AttendancePolicy::cancelSession
Header: X-Idempotency-Key (required)
Body: { "reason": "..." }
```

Forbidden body fields: `status`, `school_id`, identity fields (`session_id`, `academic_year_id`, `recorded_by`, `cancelled_by`, …).

Success `200`:

```json
{
  "data": {
    "session_id": 123,
    "previous_status": 1,
    "new_status": 3
  },
  "meta": {
    "from_idempotency_cache": false,
    "correlation_id": "..."
  }
}
```

| Case | HTTP |
|------|------|
| Unauthorized (teacher/viewer) | 403 |
| Not found | 404 |
| Invalid / empty reason; missing key | 422 |
| Lifecycle / already cancelled (no cache) | 409 |
| Exact replay | 200 + `from_idempotency_cache=true` |
| Cross-school | denied via handler (`attendance.cross_school_access` → 422) or not-found under RLS |

Route name: `api.attendance.sessions.cancel`

---

## K. Database

```text
NO DDL
NO MIGRATION
NO INDEX
NO CHECK CHANGE
NO RLS CHANGE
NO FORCE RLS CHANGE
```

Existing R1.8A CHECK already permits `status IN (1,2,3)`. Cancel uses vocabulary value `3` only.

---

## L. Tests (exact evidence)

### Unit + Feature (sqlite phpunit.xml)

```text
php artisan test --filter="CancelAttendanceSessionHandlerTest|CancelAttendanceSessionHttpTest|AttendanceAuthorizationTest|AttendanceApiAuthorizationTest"

result: passed
tests: 26
passed: 26
assertions: 109
```

Coverage includes:

- OPEN → CANCELLED; CLOSED → CANCELLED; already cancelled conflict
- empty / whitespace reason; required idempotency key
- exact replay; same-key different payload
- manager cancel permission; teacher/viewer deny
- HTTP 200 / replay / 403 / 409 / 422
- cross-school cancel denial

### PostgreSQL (`phpunit.database-pgsql.xml`)

```text
php artisan test -c phpunit.database-pgsql.xml --filter="AttendanceCancelSessionPostgreSqlTest"

result: passed
tests: 4
passed: 4
assertions: 13
```

Coverage includes:

- records + summary preservation; Mark blocked after cancel
- CLOSED → CANCELLED; second cancel conflict
- Cancel vs Close winner shapes
- new OPEN same natural key after cancel (R1.8A compatibility)

### Combined R1.11 focused totals

```text
30 tests passed / 122 assertions
```

### Quality gates

```text
php artisan architecture:validate --fitness
→ Architecture validation passed.

php artisan architecture:feature-check Attendance
→ Feature contract validation passed.

php artisan security:validate
→ Security validation passed.
```

---

## M. Scope audit

| Gate check | Result |
|------------|--------|
| R1.12 NOT IMPLEMENTED | ✅ |
| Reopen NOT IMPLEMENTED | ✅ |
| UI NOT IMPLEMENTED | ✅ |
| R1.8A NOT MODIFIED | ✅ |
| No unrelated schema changes | ✅ |
| No unrelated permission changes (only cancel added to manager) | ✅ |
| List default filtering unchanged | ✅ |
| Reporting semantics unchanged | ✅ |

---

## Final Gate Evaluation

| Criterion | Result |
|-----------|--------|
| DESIGN LOCK COMPLIANCE | PASS |
| SECURITY | PASS (`attendance.session.cancel` manager-only; policy before mutation) |
| SCHOOL ISOLATION | PASS (Strategy A ownership check; existing RLS unchanged) |
| LIFECYCLE CORRECTNESS | PASS (1/2→3 only; terminal; no restore) |
| CONCURRENCY | PASS (conditional mutation + conflict) |
| IDEMPOTENCY | PASS (required; replay; payload conflict; no silent already-cancelled success) |
| OUTBOX ATOMICITY | PASS (same UoW as status update) |
| FACT PRESERVATION | PASS (records/summaries/session row preserved) |
| API CONTRACT | PASS |
| TEST EVIDENCE | PASS (30/30 focused) |
| SCOPE CONTAINMENT | PASS |

### Verdict

```text
PASS
```

---

## STOP

```text
R1.11 CANCEL IMPLEMENTATION COMPLETE — GATE PASS.

R1.12 / Reopen / UI / reporting changes NOT AUTHORIZED.

STOP. Wait for human authorization before any next Attendance phase.
```

---

## SIS CHANGE REPORT

```text
Status: PASS
Risk: LOW

Summary: Implemented CancelAttendanceSession (OPEN|CLOSED→CANCELLED) with manager permission, required reason + idempotency, conditional concurrency-safe mutation, transactional outbox, and HTTP cancel endpoint. No DDL.

Scope: Attendance R1.11 Cancel Session only

Application Code Modified: YES
Database Modified: NO
API Modified: YES (cancel route only)
UI Modified: NO
Dependencies Modified: NO
Governance Modified: YES (Gate 19 + Attendance.md SSOT touch)

Changed: Permission catalog/manager grants; AttendancePolicy; write repository; outbox rehydrate; AttendanceController; routes
Added: Cancel command/handler/result; domain event/exceptions; FormRequest; focused tests; Gate 19
Removed: NONE

Database: NONE
API: POST /api/v1/attendance/sessions/{session}/cancel
UI: NONE

Security: attendance.session.cancel manager-only; SchoolContext; policy authorize before mutation
Authorization: AttendancePolicy::cancelSession

Tests: 26 sqlite + 4 PG = 30 passed
Validation: architecture:validate --fitness PASS; architecture:feature-check Attendance PASS; security:validate PASS

Regression: LOW
Technical Debt: NONE introduced for R1.11
Remaining Issues: NONE for R1.11 scope
Known Risks: Concurrent Close vs Cancel relies on conditional UPDATE semantics (validated); full multi-connection race stress not required by design lock

Human Approval Required: YES — for any R1.12 / Reopen / UI / reporting work
Recommended Next Step: STOP until human authorizes next phase
Final Gate Status: PASS
```
