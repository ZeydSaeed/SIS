# SIS DATABASE — PHASE R1  
# ATTENDANCE APPLICATION  
# R1.4 — CQRS IMPLEMENTATION GATE

**Date:** 2026-09-11  
**Authorization:** `APPROVED — IMPLEMENT ATTENDANCE R1.4 CQRS ONLY`  
**Upstream:** Design Lock 02 · Gate 06 READY · Security Package 05 PASS  

```text
HTTP/API (R1.5): NOT AUTHORIZED
DDL/RLS: NOT AUTHORIZED
DO NOT ADVANCE TO R1.5 AUTOMATICALLY
```

---

## 1. Executive Verdict

```text
ATTENDANCE R1.4 CQRS IMPLEMENTATION GATE: PASS

All four locked commands and five locked queries are implemented,
wired, tested, and architecture-validated without DDL/HTTP/RLS.
```

---

## 2. Implemented Scope

| Area | Status |
|------|--------|
| CreateAttendanceSession | DONE |
| MarkSectionAttendance | DONE (partial; ATT-D4; ≤500; session_date) |
| CorrectAttendanceRecord | DONE (reason; previous→new outbox) |
| CloseAttendanceSession | DONE (optimistic OPEN→CLOSED) |
| GetAttendanceSession | DONE |
| ListAttendanceSessions | DONE (paginated) |
| GetSectionAttendance | DONE |
| GetStudentAttendance | DONE (academic_year_id required) |
| GetDailySectionSummary | DONE (projection) |
| Domain VOs / exceptions / events / ATT-D4 | DONE |
| Read/Write repositories | DONE |
| ArchitectureServiceProvider bindings | DONE |
| Outbox rehydrate arms | DONE |
| Feature contract Attendance.md | DONE |
| Unit + PostgreSQL tests | DONE |

---

## 3. Command Results

| Command | Permission (consumed) | Idempotency | Outbox event | Key rules enforced |
|---------|----------------------|-------------|--------------|-------------------|
| CreateAttendanceSession | session.create | Recommended (optional key) | AttendanceSessionCreated | section→class school; year date bounds; period school |
| MarkSectionAttendance | mark | **Required** (string key) | SectionAttendanceMarked | OPEN in-tx; partial; ATT-D4; ≤500; summary refresh |
| CorrectAttendanceRecord | correct | **Required** | AttendanceCorrected | reason; OPEN/CLOSED; reject CANCELLED; identity immutable |
| CloseAttendanceSession | session.close | Recommended | AttendanceSessionClosed | `WHERE status=OPEN`; conflict if already closed |

---

## 4. Query Results

| Query | School isolation | Notes |
|-------|------------------|-------|
| GetAttendanceSession | section→class school match | Optional include records |
| ListAttendanceSessions | school filter + year preferred | Pagination |
| GetSectionAttendance | section school | Records SSOT |
| GetStudentAttendance | school + **required year** | Partition-friendly year filter |
| GetDailySectionSummary | summary.school_id | Projection only |

---

## 5. Domain / Repository Structure

```text
app/Domain/Attendance/
  ValueObjects/ SessionStatus, AttendanceRecordStatus
  Specifications/ EnrollmentValidForAttendanceSpecification
  Services/ AttendanceEnrollmentGuard, AttendanceMarkPayloadGuard
  Data/ snapshots + UpsertAttendanceRecordData
  Events/ 4 domain events
  Exceptions/ fail-closed set
  Repositories/ AttendanceWriteRepositoryInterface

app/Application/Attendance/
  Commands/ + Handlers/ + Results/
  Queries/ + Handlers/
  DTOs/
  Contracts/ AttendanceReadRepositoryInterface

app/Infrastructure/Persistence/Attendance/
  EloquentAttendanceWriteRepository
  EloquentAttendanceReadRepository
```

---

## 6. Idempotency Evidence

- Reuses `IdempotencyStore` / `audit.idempotency_keys` unchanged.  
- Ordering: find → `UnitOfWork::transaction` (writes + outbox) → store after success (Grades-compatible).  
- Mark/Correct require keys; Create/Close optional.  
- PG test: create/mark/close replay returns `fromIdempotencyCache=true`.

---

## 7. Outbox Evidence

- Events staged inside transaction.  
- `EloquentOutboxRepository::rehydrateEvent` extended for all four Attendance events.  
- PG test asserts `AttendanceCorrected` row in `audit.outbox_messages`.

---

## 8. Audit Evidence

- Correction event payload carries previous/new status & notes + reason (Design Lock ATT-D2).  
- No second audit framework; SecurityAuditLogger listeners optional/deferred (peer Enrollment bridges not required for R1.4 pass).

---

## 9. Concurrency Evidence

- Close uses optimistic `UPDATE ... WHERE status = OPEN`.  
- Mark re-locks/revalidates OPEN inside transaction.  
- PG: mark after close throws `SessionNotOpenException`; second close throws `SessionCloseConflictException`.

---

## 10. School Isolation Evidence

- Session school resolved via section → class (no `sessions.school_id`).  
- Record `school_id` written from resolved school.  
- Cross-school mark / get session denied (`CrossSchoolAttendanceAccessException` / `SessionNotFoundException`).  
- Unit security package (R1.3) remains intact.

---

## 11. Enrollment Validation Evidence

- ATT-D4 date-window specification + guard (not `EnrollmentStatus::isActive` alone).  
- Unit tests cover window / section / school mismatches.  
- Invalid enrollment rejects entire Mark command (all-or-nothing).

---

## 12. Daily Summary Evidence

- Mark/Correct call `refreshDailySectionSummary` with **session.session_date**.  
- SQL adapted from legacy service; **never** calls `AttendanceBatchService::recordSectionAttendance`.  
- PG asserts present/absent/late counts after mark/correct.

---

## 13. Legacy Service Handling

| Item | Result |
|------|--------|
| Public alternate writer | **Not used** by CQRS |
| `today()` | **Not inherited** |
| Summary SQL | Reused/adapted in write repository |
| `@architecture-legacy-allowed` | Remains on legacy class; migrate note unchanged |

---

## 14. Test Results

| Suite | Result |
|-------|--------|
| `php artisan test --filter=Attendance` | **23 passed**, 3 skipped (PG under default phpunit) |
| `phpunit.database-pgsql.xml` · `AttendanceWritePathPostgreSqlTest` | **3 passed**, 28 assertions |
| R1.3 `AttendanceAuthorizationTest` | Still green (included in filter) |

Unit coverage highlights: empty/duplicate/>500 payload, CLOSED mark, ATT-D4 spec, correct reason, close conflict (mocked).  
PG coverage highlights: full write path, idempotency, summary, five queries, cross-school, close concurrency.

---

## 15. Architecture Validation

| Check | Result |
|-------|--------|
| `architecture:validate --fitness` | **PASS** |
| `architecture:feature-check Attendance` | **PASS** |
| Feature contract | `.cursor/architecture/features/Attendance.md` updated |

---

## 16. Regression Results

- No intentional changes to Enrollment/Grades/Graduation/HTTP.  
- Outbox rehydrate match extended only (compatible pattern).  
- ArchitectureServiceProvider bindings additive.  
- Targeted Attendance suites green; fitness green.

---

## 17. Files Changed (primary)

**Added:** Domain/Application/Infrastructure Attendance tree (~60 PHP files), unit tests under `tests/Unit/Attendance/`, `tests/Feature/Database/PostgreSql/AttendanceWritePathPostgreSqlTest.php`, feature contract update.

**Modified:**  
`app/Providers/ArchitectureServiceProvider.php`  
`app/Infrastructure/Persistence/Outbox/EloquentOutboxRepository.php`  
`.cursor/architecture/features/Attendance.md`

**Not modified:** migrations, RLS, security permissions/roles, HTTP, `AttendanceBatchService` public API.

---

## 18. Explicitly Deferred Items

```text
R1.5 HTTP / controllers / routes / FormRequests
ATT-SEC-001..004 (FORCE RLS, sessions/summary RLS, session.school_id)
Reopen / Cancel session commands
attendance.administer
VOID+INSERT / record_versions (ATT-D2-FUTURE)
Year-specific partitions
Natural UNIQUE on sessions
SecurityAuditLogger bridge listeners for Attendance (optional)
```

---

## 19. Remaining Risks

| Risk | Level | Notes |
|------|-------|-------|
| Duplicate sessions (no DB unique) | P2 | App optional check not mandatory |
| DEFAULT-only partition | P2 | Writes succeed via default |
| Permission checks at Application layer | P2 | Peer pattern — enforce at R1.5 HTTP via AttendancePolicy |
| Legacy service still callable | P2 | Documented forbidden for new code paths |
| Incomplete combinatorial unit matrix vs Gate 06 list | P3 | Core paths covered; additional edge cases can extend tests later |

---

## 20. Final Gate

```text
SIS DATABASE — PHASE R1
ATTENDANCE APPLICATION
R1.4 — CQRS IMPLEMENTATION GATE

STATUS: PASS

CQRS Implementation: COMPLETE (authorized scope)
HTTP/API (R1.5): NOT AUTHORIZED
DDL/RLS: NOT AUTHORIZED

NEXT ACTION:
Await separate human authorization for R1.5 HTTP/API
(or other next roadmap item). Do not advance automatically.

STOP.
```

---

## SIS CHANGE REPORT

```text
Status: PASS
Risk: LOW–MEDIUM (ops domain write path introduced; no HTTP yet)

Summary: Implemented Attendance R1.4 CQRS (4 commands, 5 queries) per Design Lock with school isolation, ATT-D4, idempotency, outbox, and summary refresh.

Application Code Modified: YES
Database Modified: NO
API Modified: NO
UI Modified: NO
Dependencies Modified: NO
Governance Modified: YES (feature contract + this gate)

Tests: Unit Attendance + PG AttendanceWritePath + prior AttendanceAuthorization
Validation: architecture:validate --fitness PASS; feature-check Attendance PASS
Security Validation: R1.3 package consumed; no new permissions
Architecture Validation: PASS

Regression: LOW
Technical Debt: Legacy AttendanceBatchService still present; HTTP deferred
Human Approval Required for next: YES — R1.5
Final Gate Status: PASS
```
