# SIS DATABASE — PHASE R1
# ATTENDANCE APPLICATION
# R1.5 — HTTP/API IMPLEMENTATION GATE

**Date:** 2026-09-11  
**Authorization phrase:** `APPROVED — IMPLEMENT ATTENDANCE R1.5 HTTP/API ONLY`  
**Upstream:** Gate 08 READY · Gate 07 CQRS PASS · Feature `Attendance.md`

```text
R1.6 / UI / DDL / RLS: NOT AUTHORIZED
DO NOT ADVANCE AUTOMATICALLY
```

---

## 1. Executive Verdict

```text
ATTENDANCE R1.5 HTTP/API IMPLEMENTATION GATE: PASS

Locked Attendance HTTP surface is implemented over R1.4 Application handlers only.
SchoolContext school_id is authoritative; client school_id prohibited.
Catalog permission codes used (no shorthand, no attendance.administer).
Legacy AttendanceBatchService is not called from HTTP.
DDL / migrations / RLS were not modified in this phase.
```

---

## 2. Implemented Routes

| Verb | Route | Controller action | Permission |
|------|-------|-------------------|------------|
| POST | `/api/v1/attendance/sessions` | `storeSession` | `attendance.session.create` |
| POST | `/api/v1/attendance/sessions/{session}/marks` | `mark` | `attendance.mark` |
| POST | `/api/v1/attendance/sessions/{session}/students/{student}/correct` | `correct` | `attendance.correct` |
| POST | `/api/v1/attendance/sessions/{session}/close` | `close` | `attendance.session.close` |
| GET | `/api/v1/attendance/sessions/{session}` | `showSession` | `attendance.view` |
| GET | `/api/v1/attendance/sessions` | `indexSessions` | `attendance.view` |
| GET | `/api/v1/attendance/sections/{section}` | `sectionAttendance` | `attendance.view` |
| GET | `/api/v1/attendance/students/{student}` | `studentAttendance` | `attendance.view` |
| GET | `/api/v1/attendance/sections/{section}/daily-summary` | `dailySummary` | `attendance.view` |

Middleware (peer Enrollment/Grades): `auth:sanctum` + `SchoolContextMiddleware` + `RequireSchoolContextMiddleware`.

---

## 3. Controllers / FormRequests / Policy

| Artifact | Status | Location |
|----------|--------|----------|
| `AttendanceController` | DONE | `app/Http/Controllers/Api/AttendanceController.php` |
| Create / Mark / Correct / Close FormRequests | DONE | `app/Http/Requests/Attendance/` |
| Thin Eloquent mapper for Gate | DONE | `app/Infrastructure/Persistence/Eloquent/AttendanceSessionRecord.php` |
| Policy Gate registration | DONE | `SecurityServiceProvider` → `AttendanceSessionRecord` + `AttendancePolicy` |
| Policy redesign | NOT DONE (preserved R1.3) | methods unchanged beyond optional session arg for Gate |

Controllers map HTTP → Application Command/Query only. No repository, Eloquent writes, ATT-D4, or legacy batch calls in the controller.

---

## 4. Permission Usage

Canonical catalog codes only:

```text
attendance.view
attendance.session.create
attendance.mark
attendance.correct
attendance.session.close
```

```text
attendance.administer: ABSENT
New permissions: NONE
New roles: NONE
```

---

## 5. School Isolation

| Rule | Implementation |
|------|----------------|
| Authoritative school | `SchoolContext::requireId()` into every Command/Query |
| Client `school_id` | Prohibited via `SecuritySensitiveFieldGuard` + FormRequest rules |
| Cross-school show | Application `SessionNotFound` → HTTP 404 |
| Cross-school create (wrong section) | Application `CrossSchoolAttendanceAccessException` → HTTP 422 (`attendance.cross_school_access`) |
| IDOR missing session (mark/correct/close) | FormRequest throws `SessionNotFoundException` → 404 |

---

## 6. Idempotency

| Operation | Header | Behavior |
|-----------|--------|----------|
| MarkSectionAttendance | **Required** `X-Idempotency-Key` | Existing `IdempotencyStore` via handler |
| CorrectAttendanceRecord | **Required** `X-Idempotency-Key` | Same |
| CreateAttendanceSession | Optional | 201 first / 200 replay + `meta.from_idempotency_cache` |
| CloseAttendanceSession | Optional | Same meta convention |

No new idempotency mechanism. No body hashing beyond peer convention.

---

## 7. Error Mapping

Uses existing `SisDomainException` renderer in `bootstrap/app.php`:

| Condition | Status |
|-----------|--------|
| Unauthenticated | 401 |
| Authorization / Policy deny | 403 |
| `*_not_found*` | 404 |
| `*_conflict*` (close conflict) | 409 |
| SessionNotOpen / validation / domain | 422 |

---

## 8. Response Boundary

HTTP returns Application Results/DTOs + pagination/idempotency meta only. No Eloquent models, repositories, or outbox payloads in responses.

---

## 9. Tests

| Suite | Result |
|-------|--------|
| `AttendanceApiAuthorizationTest` | PASS (viewer/teacher/manager/cross-school/unauth) |
| `AttendanceHttpApiTest` | PASS (happy path, idempotency, validation, SessionNotOpen→422, close conflict→409, 404) |
| `AttendanceAuthorizationTest` (R1.3 regression) | PASS |
| Unit `Tests\Unit\Attendance\*` | PASS (3 skipped when PG unavailable in filter run is unrelated) |
| `architecture:validate --fitness` | PASS |
| `architecture:feature-check Attendance` | PASS |

---

## 10. Architecture Validation

```text
architecture:validate --fitness → PASS
architecture:feature-check Attendance → PASS
```

Feature contract updated: HTTP Controllers ✅ R1.5.

---

## 11. Files Changed (R1.5 scope)

### Added

```text
app/Http/Controllers/Api/AttendanceController.php
app/Http/Requests/Attendance/CreateAttendanceSessionRequest.php
app/Http/Requests/Attendance/MarkSectionAttendanceRequest.php
app/Http/Requests/Attendance/CorrectAttendanceRecordRequest.php
app/Http/Requests/Attendance/CloseAttendanceSessionRequest.php
app/Infrastructure/Persistence/Eloquent/AttendanceSessionRecord.php
tests/Feature/Attendance/AttendanceHttpApiTest.php
tests/Feature/Security/AttendanceApiAuthorizationTest.php
tests/Support/Attendance/SeedsApplicationAttendanceGraph.php
.cursor/database/phase-attendance/09-ATTENDANCE-HTTP-API-IMPLEMENTATION-GATE.md
```

### Modified (R1.5)

```text
routes/api.php
app/Providers/SecurityServiceProvider.php
app/Security/Policies/AttendancePolicy.php
app/Security/Audit/SecurityEventType.php  (+ AttendanceDataAccess/Modified audit enums)
tests/Concerns/InteractsWithSecurity.php  (+ actingAsAttendance*)
.cursor/architecture/features/Attendance.md
```

### Explicitly NOT modified in R1.5

```text
database/migrations/**
RLS / FORCE RLS / indexes / partitions
Permission catalog codes / roles (beyond prior R1.3)
Application Command/Query contracts (R1.4 preserved)
AttendanceBatchService
UI / Inertia
```

---

## 12. Deferred Items

1. **Cross-school write error code** — Application throws `attendance.cross_school_access` (HTTP 422). Peer Grades often uses `*_not_found` (404). Changing Domain error codes = CQRS/Domain redesign → **deferred** (not authorized in R1.5).
2. **FORCE RLS / `sessions.school_id`** — still deferred (DDL not authorized). HTTP relies on SchoolContext + Application school checks.
3. **Reopen / Cancel / `attendance.administer`** — deferred (not authorized).
4. **UI / Inertia** — R1.6+ requires separate human gate.
5. **PostgreSQL HTTP integration suite** — Feature tests cover SQLite path; PG write-path regression remains Gate 07 suite.

---

## 13. Remaining Risks

| Risk | Severity | Notes |
|------|----------|-------|
| Policy does not assert session.school ownership | LOW | Defense in depth via Application schoolId; show IDOR → 404 |
| Cross-school create returns 422 not 404 | LOW | Non-leaking enough; code differs from Grades |
| Thin Eloquent session mapper for Gate | LOW | Empty `$fillable`; writes remain Application-only |

---

## 14. Scope Verification Commands

```text
git status --short
git diff --name-only
```

R1.5 authorization boundary: **no migration/DDL/RLS files touched for this HTTP phase.**

---

## 15. Final Stop

```text
STOP AFTER R1.5 IMPLEMENTATION GATE.

R1.6: NOT AUTHORIZED
DDL/RLS: NOT AUTHORIZED
UI: NOT AUTHORIZED
Reopen/Cancel: NOT AUTHORIZED
CQRS redesign: NOT AUTHORIZED
New permissions: NOT AUTHORIZED

Next action requires a separate human gate.
```

## SIS CHANGE REPORT (R1.5)

```text
Status: PASS
Risk: LOW

Summary: Attendance R1.5 HTTP/API wired over locked R1.4 CQRS with Policy Gate, FormRequests, routes, and feature/security tests.

Application Code Modified: YES (HTTP boundary only; CQRS contracts unchanged)
Database Modified: NO
API Modified: YES (new /api/v1/attendance/*)
UI Modified: NO
Dependencies Modified: NO
Governance Modified: YES (gate 09 + feature contract HTTP status)

Security: SchoolContext + AttendancePolicy + catalog permissions
Authorization: view/create/mark/correct/close as Option B
Tests: HTTP authz + HTTP feature PASS; architecture fitness PASS
Human Approval Required for next phase: YES
Final Gate Status: PASS
```
