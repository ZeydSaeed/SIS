# Feature: Attendance

> Phase R1.4 CQRS + R1.5 HTTP/API + R1.7 DDL/RLS Strategy A + R1.8A integrity
> + R1.9 Option B legacy writer quarantine. UI deferred.

## Bounded Context

- **Context:** Attendance
- **Primary aggregate:** AttendanceSession (lifecycle) + AttendanceRecord (facts)
- **Scoped by:** school_id (Strategy A stamped on sessions) + academic_year_id

## Planned Use Cases

| Type | Name | Status |
|------|------|--------|
| Command | CreateAttendanceSession | ✅ (OPEN; duplicate OPEN → conflict) |
| Command | MarkSectionAttendance | ✅ (partial upsert; ATT-D4; ≤500) |
| Command | CorrectAttendanceRecord | ✅ (mandatory reason; previous→new outbox) |
| Command | CloseAttendanceSession | ✅ (optimistic OPEN→CLOSED) |
| Command | CancelAttendanceSession | ✅ R1.11 (OPEN|CLOSED→CANCELLED; terminal; manager cancel) |
| Query | GetAttendanceSession | ✅ |
| Query | ListAttendanceSessions | ✅ (paginated) |
| Query | GetSectionAttendance | ✅ |
| Query | GetStudentAttendance | ✅ (academic_year_id required) |
| Query | GetDailySectionSummary | ✅ (projection) |
| Command | Reopen session | ⛔ NOT AUTHORIZED (R1.12+) |
| HTTP / Controllers | AttendanceController + routes + FormRequests | ✅ R1.5 + R1.11 cancel |

## Write-path authority

| Path | Status |
|------|--------|
| **CURRENT — CQRS** (`Application/Attendance`) | **Authoritative supported Attendance writer** |
| **LEGACY** `AttendanceBatchService` | **Deprecated / quarantined (R1.9 Option B)** — runtime fail-closed; class file retained |
| **FUTURE** full deletion of legacy class | Requires separate human authorization after external/ops caller confirmation |

## Non-negotiables

- Idempotency ordering: find cache → UoW (writes + outbox) → store after success
- Strategy A: `sessions.school_id` stamped at create; RLS ENABLE+FORCE (R1.7)
- R1.8A: status CHECK `{1,2,3}`; partial UNIQUE OPEN natural key with NULLS NOT DISTINCT on `period_id`
- ATT-D4 date-window enrollment predicate (not EnrollmentStatus::isActive alone)
- Summary refresh after Mark/Correct using `session.session_date`
- Do not call quarantined legacy `AttendanceBatchService` write methods
