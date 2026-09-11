# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.7 — DDL + RLS IMPLEMENTATION GATE (STRATEGY A)

**Date:** 2026-09-11  
**Authorization:** `APPROVED — IMPLEMENT ATTENDANCE R1.7 DDL + RLS ONLY — STRATEGY A`  
**ATT-SEC-004:** **Strategy A LOCKED** — `attendance.sessions.school_id` denormalized historical ownership  

```text
R1.8 / UI / Reopen-Cancel / year partitions / legacy writer: NOT AUTHORIZED
```

---

## 1. Scope

| In | Out |
|----|-----|
| `sessions.school_id` DDL + backfill + NOT NULL + FK | HTTP redesign |
| ENABLE + FORCE RLS on sessions, records, summary | CQRS redesign / new commands |
| Fail-closed policies + WITH CHECK | New permissions/roles |
| DELETE denied (no DELETE policy) | Status CHECK / session UNIQUE (P2 deferred) |
| Index via FK on `sessions.school_id` | Speculative year partitions |
| Minimal stamp in Create path (`insertSession(schoolId)`) | Legacy writer retirement |
| PG RLS tests + blueprint update | Certificates / Graduation / Enrollment redesign |

---

## 2. Strategy A Confirmation

```text
At CreateAttendanceSession:
  sessions.school_id = command.schoolId
  after Application asserts command.schoolId === section→class.school_id

RLS predicate (sessions / records / summary):
  school_id = current_setting('app.current_school_id')::bigint
  AND setting IS NOT NULL (fail-closed)
```

Section→class remains domain relationship; **not** the sole DB RLS predicate for sessions.

---

## 3. Implementation Plan (executed)

1. Add nullable `school_id` + FK + backfill from section→class.  
2. Set `school_id` NOT NULL (PG).  
3. Replace records ALL policy; ENABLE+FORCE all three tables; SELECT/INSERT/UPDATE policies with WITH CHECK.  
4. Stamp `school_id` in `insertSession` / Create handler.  
5. Read path filters `sess.school_id` (Strategy A).  
6. PG RLS tests + LIVE catalog verify.

---

## 4. DDL Changes

| Change | Detail |
|--------|--------|
| Column | `attendance.sessions.school_id BIGINT NOT NULL` |
| FK | → `organization.schools(id)` ON DELETE RESTRICT |
| Backfill | `UPDATE … FROM sections JOIN classes` (0 orphans on LIVE) |
| Index | Created with `foreignId` (BTREE on `school_id` for RLS) |

**Not added:** status CHECK, natural UNIQUE, extra `school_id` columns, year partitions.

---

## 5. Migration List

| Migration | Purpose |
|-----------|---------|
| `2026_09_11_190100_r17_attendance_sessions_school_id.php` | Strategy A column |
| `2026_09_11_190200_r17_attendance_force_rls.php` | ENABLE/FORCE + policies |

---

## 6. RLS Policies

Per table (`sessions`, `records`, `daily_section_summary`):

| Policy | Command | Predicate |
|--------|---------|-----------|
| `*_school_select` | SELECT | fail-closed school_id match |
| `*_school_insert` | INSERT | WITH CHECK fail-closed |
| `*_school_update` | UPDATE | USING + WITH CHECK fail-closed |
| *(none)* | DELETE | **Denied** under RLS (academic evidence) |

Legacy `attendance_school_isolation` (ALL) on records was dropped and replaced.

---

## 7. FORCE RLS Verification (LIVE `sis`)

```text
sessions              rls=t  force=t
records               rls=t  force=t
daily_section_summary rls=t  force=t
sessions.school_id    present
```

---

## 8. Constraint / Index Changes

| Item | Status |
|------|--------|
| `sessions.school_id` NOT NULL + FK | DONE |
| FK-backed school_id index | DONE |
| Status CHECK | DEFERRED (P2) |
| Session natural UNIQUE | DEFERRED (P2) |

---

## 9. Partition Behavior

- Existing LIST parent + `records_default` **preserved**.  
- No new year partitions.  
- FORCE RLS on parent `attendance.records` verified in catalog + RLS tests (writes/reads via parent).  

---

## 10–12. Security / Isolation / CQRS Tests

| Suite | Result |
|-------|--------|
| `AttendanceRlsPostgreSqlTest` (phpunit.database-pgsql.xml) | **7/7 PASS** |
| `AttendanceWritePathPostgreSqlTest` | **PASS** |
| `AttendanceHttpApiTest` + `AttendanceApiAuthorizationTest` | **PASS** |
| `Tests\Unit\Attendance\*` | **PASS** |

Covered: fail-closed empty GUC; cross-school SELECT hidden; cross-school INSERT blocked; cross-school UPDATE 0 rows; DELETE no-op/denied; same-school INSERT/UPDATE OK; CQRS write path with stamped school_id.

---

## 13. Live PostgreSQL Verification

Performed against database `sis` after migrate:

- ENABLE + FORCE on all three Attendance tables: **YES**  
- Nine Attendance policies (3×3 SELECT/INSERT/UPDATE): **YES**  
- `sessions.school_id` column: **YES**  

---

## 14. Application touch (minimal, required by DDL)

| File | Change |
|------|--------|
| `CreateAttendanceSessionHandler` | Pass `schoolId` into `insertSession` |
| `AttendanceWriteRepositoryInterface` + Eloquent impl | `insertSession(..., int $schoolId)`; snapshot uses stamped school |
| `EloquentAttendanceReadRepository` | Filter/list by `sess.school_id` |

No HTTP route/FormRequest/permission changes.

---

## 15. Incidental note (migrate queue)

`php artisan migrate` also applied **pre-existing pending** Certificates Phase 4.1 migrations already present on disk (`2026_09_11_180100`–`180300`). Those were **not** authored in R1.7 and are **out of R1.7 authorization scope**. Tracked as environment migrate-queue contamination — no Certificates feature work performed in this phase.

---

## 16. SIS CHANGE REPORT

```text
Status: PASS
Risk: MEDIUM (FORCE RLS + ownership stamp; Certificates migrate incidental)

Summary: R1.7 Strategy A sessions.school_id + FORCE RLS on sessions/records/summary with fail-closed WITH CHECK; DELETE denied under RLS.

Application Code Modified: YES (minimal Create stamp + read filter)
Database Modified: YES (2 Attendance migrations)
API Modified: NO
UI Modified: NO
Dependencies Modified: NO
Governance Modified: YES (gate 11 + blueprint + feature note)

Tests: PG RLS PASS; CQRS PG PASS; HTTP/unit PASS
Human Approval Required for next: YES
Final Gate Status: PASS
```

---

## 17. Final Verdict

```text
ATTENDANCE R1.7 DDL + RLS IMPLEMENTATION GATE: PASS

Strategy A: IMPLEMENTED
FORCE RLS: VERIFIED LIVE
Cross-school isolation tests: PASS
CQRS compatibility: PASS
```

```text
STOP.

R1.8 / UI / Reopen-Cancel / year partitions / legacy writer retirement:
NOT AUTHORIZED — separate human gate required.
```
