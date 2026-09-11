# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.8A — INTEGRITY HARDENING IMPLEMENTATION GATE

**Document Type:** IMPLEMENTATION GATE  
**Date:** 2026-09-11  
**PostgreSQL (live):** 18.2

---

## 1. Authorization Confirmation

```text
ATT-SEC-004: STRATEGY A REMAINS LOCKED

APPROVED — IMPLEMENT ATTENDANCE R1.8A INTEGRITY HARDENING ONLY
```

Authorization applied **only** to this scope. No other Attendance phase was implemented.

---

## 2. Exact Scope Implemented

| Item | Status |
|------|--------|
| `attendance.sessions.status` CHECK ∈ {1,2,3} | ✅ |
| Partial UNIQUE for OPEN sessions (locked natural key) | ✅ |
| NULL period = distinct bucket (`NULLS NOT DISTINCT`) | ✅ |
| CLOSED + new OPEN same key allowed | ✅ |
| Create early `findDuplicateOpenSession` + DB conflict mapping | ✅ |
| Focused PG integrity + concurrency tests | ✅ |
| Blueprint + indexing matrix sync | ✅ |

**Not implemented (explicitly out of scope):** Reopen, Cancel, UI, API redesign, new permissions, legacy writer retirement, year partitions, RLS changes, records/summary redesign.

---

## 3. Migration Filenames

```text
database/migrations/2026_09_11_200100_r18a_attendance_sessions_integrity.php
```

Batch: **[13]** on live `sis` (after R1.7 batch 12).

SQLite path included for local suites only (status triggers + `COALESCE(period_id, -1)` partial unique). Authoritative enforcement is PostgreSQL.

---

## 4. CHECK Definition

```sql
ALTER TABLE attendance.sessions
ADD CONSTRAINT attendance_sessions_status_check
CHECK (status IN (1, 2, 3));
```

**Live verified:**

```text
CHECK: CHECK ((status = ANY (ARRAY[1, 2, 3])))
```

Vocabulary only — **no** transition triggers.

---

## 5. Unique Enforcement Definition

```sql
CREATE UNIQUE INDEX attendance_sessions_open_natural_key_uidx
ON attendance.sessions (
    school_id,
    academic_year_id,
    section_id,
    subject_id,
    session_date,
    period_id
)
NULLS NOT DISTINCT
WHERE status = 1;
```

**Live verified** on PostgreSQL 18.2 with `NULLS NOT DISTINCT` and `WHERE (status = 1)`.

---

## 6. NULL Period Behavior

| Case | Result |
|------|--------|
| NULL + NULL (both OPEN) | Rejected (23505 / conflict) |
| NULL + period 1 | Allowed (distinct buckets) |
| period 1 + period 1 (OPEN) | Rejected |
| period 1 + period 2 | Allowed |

---

## 7. CLOSED Recreation Behavior

CLOSED session with natural key K does **not** participate in the partial unique index.

Create of a new OPEN session with the same key **succeeds** (proven in `closed_session_allows_new_open_with_same_natural_key`).

---

## 8. Create Conflict Handling

| Layer | Behavior |
|-------|----------|
| Early app check | `CreateAttendanceSessionHandler` → `findDuplicateOpenSession(school, year, section, subject, date, period)` |
| DB authority | Partial UNIQUE (race-safe) |
| Mapping | `UniqueConstraintViolationException` → `DuplicateOpenAttendanceSessionException` (`attendance.duplicate_open_session_conflict`) |
| HTTP | Existing `SisDomainException` renderer → **409** (code contains `conflict`) |
| Leakage | No SQLSTATE / constraint name / raw PG text exposed via domain exception path |

Strategy A preserved: Create still stamps `sessions.school_id = command.schoolId` after section→class resolution match.

---

## 9. Concurrency Test Evidence

Test: `AttendanceR18aIntegrityPostgreSqlTest::concurrent_duplicate_open_cannot_produce_two_open_rows`

- Dual PDO overlapping inserts on identical OPEN natural key
- One connection commits; peer receives **SQLSTATE 23505**
- Final OPEN row count for key = **1**

```text
R1.8A PG: 6 tests PASS (22 assertions)
```

---

## 10. RLS Regression Evidence

| Check | Result |
|-------|--------|
| Live `sessions` ENABLE RLS | true |
| Live `sessions` FORCE RLS | true |
| `AttendanceRlsPostgreSqlTest` | **4 tests PASS** |
| R1.8A migration touches RLS? | **NO** |

---

## 11. CQRS Regression Evidence

| Suite | Result |
|-------|--------|
| `AttendanceWritePathPostgreSqlTest` (R1.4) | **3 tests PASS** |
| `tests/Unit/Attendance` | **17 tests PASS** (includes new Create conflict unit) |

---

## 12. HTTP Regression Evidence

| Suite | Result |
|-------|--------|
| `AttendanceHttpApiTest` (R1.5) | **5 tests PASS** |
| `AttendanceApiAuthorizationTest` + unit auth | **13 tests PASS** |

No route/API redesign.

---

## 13. Architecture Validation

```text
php artisan architecture:validate --fitness
→ PASS (all fitness categories)
```

---

## 14. Migration Contamination

```text
NONE
```

`php artisan migrate` executed **only**:

```text
2026_09_11_200100_r18a_attendance_sessions_integrity
```

No pre-existing Pending migrations were present.  
Note: `migrate:rollback` is **prohibited** on the protected live environment; rollback/re-up was validated via `sis_test` RefreshDatabase (`migrate:fresh`) in PG tests.

---

## 15. Files Modified / Added

**Added**

- `database/migrations/2026_09_11_200100_r18a_attendance_sessions_integrity.php`
- `app/Domain/Attendance/Exceptions/DuplicateOpenAttendanceSessionException.php`
- `tests/Feature/Database/PostgreSql/AttendanceR18aIntegrityPostgreSqlTest.php`
- `tests/Unit/Attendance/CreateAttendanceSessionHandlerTest.php`
- `.cursor/database/phase-attendance/14-ATTENDANCE-R1.8A-INTEGRITY-HARDENING-IMPLEMENTATION-GATE.md`

**Modified**

- `app/Application/Attendance/Commands/CreateAttendanceSessionHandler.php`
- `app/Domain/Attendance/Repositories/AttendanceWriteRepositoryInterface.php`
- `app/Infrastructure/Persistence/Attendance/EloquentAttendanceWriteRepository.php`
- `.cursor/architecture/database-blueprint.md`
- `.cursor/architecture/indexing-matrix.md`
- `.cursor/architecture/features/Attendance.md`

---

## 16. Files Explicitly NOT Modified

```text
AttendanceBatchService::recordSectionAttendance
Reopen / Cancel commands, permissions, routes
attendance.records / daily_section_summary schema
R1.7 RLS policies / FORCE RLS
HTTP routes / FormRequest contracts (beyond conflict exception path)
Year partitions
UI / Inertia pages
Graduation / Enrollment / Certificates
```

---

## 17. Risks

| Risk | Mitigation / Residual |
|------|------------------------|
| Existing OPEN duplicate rows before migrate | Pre-check not required on empty/clean env; migrate would fail on conflict if dups existed |
| Early check still TOCTOU | Accepted; DB UNIQUE is authority |
| SQLite COALESCE sentinel ≠ PG NULLS NOT DISTINCT | Local approx only; PG is SSOT |
| Live rollback blocked by env policy | Use sis_test / controlled ops for rollback drills |

---

## 18. Final Verdict

```text
STATUS: PASS
Risk: LOW

R1.8A INTEGRITY HARDENING IMPLEMENTATION COMPLETE.

Human review required.

NO AUTOMATIC NEXT PHASE.
```

Do **not** begin R1.9, Reopen, Cancel, UI, legacy retirement, or year partitions without a new human authorization.

---

## SIS CHANGE REPORT

```text
Status: PASS
Risk: LOW

Summary: Added DB CHECK for session status vocabulary and partial UNIQUE for OPEN sessions; wired Create conflict mapping.

Scope: Attendance R1.8A integrity hardening only

Application Code Modified: YES
Database Modified: YES
API Modified: NO (conflict uses existing SisDomainException → 409)
UI Modified: NO
Dependencies Modified: NO
Governance Modified: YES (gate + blueprint/index matrix + feature note)

Database: attendance.sessions CHECK + open natural-key partial UNIQUE
API: NONE (error_code attendance.duplicate_open_session_conflict via existing renderer)
UI: NONE

Security: RLS unchanged; Strategy A intact
Authorization: No new permissions

Tests: Unit Attendance PASS; R1.4/R1.5/R1.7/R1.8A PASS
Validation: architecture:validate --fitness PASS
Security Validation: N/A (no security surface change beyond existing conflict path)
Architecture Validation: PASS

Regression: LOW
Technical Debt: SQLite COALESCE approx for open unique; reserved CANCELLED still unused by writers
Remaining Issues: NONE in R1.8A scope
Known Risks: See §17

Human Approval Required: YES — human review of this gate before any next Attendance phase
Recommended Next Step: Human review only — STOP
Final Gate Status: PASS
```
