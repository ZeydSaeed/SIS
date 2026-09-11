# ATTENDANCE APPLICATION READINESS GATE

**Date:** 2026-09-11  
**Mode:** AUDIT + DESIGN ONLY — READ ONLY  
**Source audit:** `.cursor/database/CORE-DOMAIN-COMPLETENESS-AUDIT.md` (R1 recommendation)  
**Live catalog inspected:** PostgreSQL via read-only `information_schema` / `pg_catalog` (no DML)

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
DDL AUTHORIZATION: NOT GRANTED
RLS MUTATION AUTHORIZATION: NOT GRANTED
CQRS IMPLEMENTATION: NOT GRANTED
MIGRATION AUTHORIZATION: NOT GRANTED
```

---

## 1. Executive Summary

Attendance has a **usable database foundation** (sessions, partitioned records, daily summary, timetable periods) and a **legacy batch writer**, but **no Clean Architecture CQRS surface**, **no attendance permissions**, **incomplete RLS**, and **undefined session/correction business vocabulary**.

```text
SCHEMA READINESS:        YELLOW (usable foundation; gaps)
APPLICATION READINESS:   RED (no Application/Attendance)
OVERALL GATE:            READY WITH CONDITIONS

R1 may proceed only after a Design Lock resolves:
  ATT-D1  Session status vocabulary + lifecycle
  ATT-D2  Correction / history model (schema-compatible)
  ATT-D3  Permission catalog
  ATT-D4  Enrollment validity rules for marking
```

**Preserve:** Graduation, Certificates 4.1, Grades — untouched.  
**Do not** treat this gate as implementation authorization.

---

## 2. Repository Evidence

| Artifact | State | Classification |
|----------|-------|----------------|
| `database/migrations/2026_09_05_100800_create_attendance_tables.php` | Creates `timetable.periods`, `attendance.sessions`, partitioned `attendance.records`, `attendance.daily_section_summary` | **IMPLEMENTED** / **DATABASE-ENFORCED** |
| `database/migrations/2026_09_05_100900_enable_row_level_security.php` | ENABLE RLS + initial policy on `attendance.records` | **IMPLEMENTED** |
| `database/migrations/2026_09_07_120100_fix_rls_fail_closed.php` | Fail-closed policy rewrite | **IMPLEMENTED** |
| `database/migrations/2026_09_05_101000_create_reports_materialized_views.php` | `reports.mv_daily_attendance` from summary | **IMPLEMENTED** |
| `app/Services/Attendance/AttendanceBatchService.php` | Legacy upsert + summary refresh; marked `@architecture-legacy-allowed migrate to Application/Attendance` | **IMPLEMENTED** (non-CQRS) |
| `app/Application/Attendance/**` | Absent | **MISSING** |
| `app/Domain/Attendance/**` | Absent | **MISSING** |
| `Permission` / `config/security.php` | No `attendance.*` codes | **MISSING** |
| Routes / HTTP / Jobs for attendance | Absent | **MISSING** |
| Dedicated Attendance tests | Absent (only incidental optimization/RLS-grant mentions) | **MISSING** |
| Blueprint + dictionary + Phase C + batch-write-patterns | Document status codes, partition, batch upsert | **DOCUMENTED** (partially ≠ enforced) |
| Enrollment / Grades CQRS | Patterns for idempotency, outbox, VOID+INSERT (grades) | **IMPLEMENTED** (peer domains) |

---

## 3. Live Database Evidence

Inspected 2026-09-11 (read-only).

### Tables

| Relation | Kind | RLS | FORCE RLS |
|----------|------|-----|-----------|
| `attendance.sessions` | ordinary | **false** | **false** |
| `attendance.records` | partitioned parent | **true** | **false** |
| `attendance.records_default` | DEFAULT partition | false* | false |
| `attendance.daily_section_summary` | ordinary | **false** | **false** |
| `timetable.periods` | ordinary | **false** | **false** |

\*Partition children inherit policy evaluation from parent when querying through parent; child itself shows `relrowsecurity=false` in catalog.

### Partitions

| Parent | Child | Bound |
|--------|-------|-------|
| `attendance.records` | `attendance.records_default` | **DEFAULT only** |

No year-specific `FOR VALUES IN (...)` partitions exist yet.

### Policies (LIVE)

```text
attendance.records → attendance_school_isolation
  PERMISSIVE / ALL / roles={public}
  USING: school_id = current_setting('app.current_school_id') AND setting IS NOT NULL
  WITH CHECK: null
  → FAIL-CLOSED when setting empty
  → NOT FORCE RLS (table owners / bypass roles still a concern)
```

No policies on `sessions` or `daily_section_summary`.

### Triggers / CHECKs

```text
Triggers on attendance.*: NONE
CHECK constraints on attendance.*: NONE
```

---

## 4. Current Attendance Schema

### 4.1 `timetable.periods`

| Column | Type | Null | Notes |
|--------|------|------|-------|
| id | SMALLINT identity | NO | PK |
| school_id | BIGINT | NO | FK → organization.schools RESTRICT |
| period_number | SMALLINT | NO | UNIQUE(school_id, period_number) |
| start_time / end_time | TIME | NO | |
| period_type | SMALLINT | NO | default 1 |

### 4.2 `attendance.sessions`

| Column | Type | Null | Notes |
|--------|------|------|-------|
| id | BIGINT | NO | PK |
| section_id | BIGINT | NO | FK → enrollment.sections RESTRICT |
| subject_id | BIGINT | NO | FK → curriculum.subjects RESTRICT |
| academic_year_id | BIGINT | NO | FK → academic.academic_years RESTRICT |
| session_date | DATE | NO | |
| period_id | SMALLINT | YES | FK → timetable.periods **SET NULL** |
| teacher_id | BIGINT | NO | FK → teachers.teachers RESTRICT |
| status | SMALLINT | NO | **default 1 — vocabulary NOT DATABASE-ENFORCED** |
| created_at | timestamp **without** tz | NO | Constitution prefers TIMESTAMPTZ |

**Indexes:** `(section_id, session_date)`, `(academic_year_id, session_date)`  
**Missing:** `school_id`; UNIQUE for natural session identity; CHECK on status.

### 4.3 `attendance.records` (partitioned LIST `academic_year_id`)

| Column | Type | Null | Notes |
|--------|------|------|-------|
| id | BIGINT GENERATED ALWAYS AS IDENTITY | NO | PK composite `(id, academic_year_id)` |
| session_id | BIGINT | NO | FK → sessions RESTRICT |
| student_id | BIGINT | NO | FK → students RESTRICT |
| enrollment_id | BIGINT | NO | FK → enrollments RESTRICT |
| academic_year_id | BIGINT | NO | **PARTITION KEY** + FK |
| school_id | BIGINT | NO | Denormalized for RLS |
| attendance_date | DATE | NO | |
| status | SMALLINT | NO | No CHECK |
| notes | TEXT | YES | |
| recorded_by | BIGINT | YES | FK users SET NULL |
| created_at / updated_at | TIMESTAMPTZ | NO | |

**Authoritative uniqueness (LIVE):**

```text
UNIQUE INDEX attendance_records_session_student_unique
  ON (session_id, student_id, academic_year_id)
```

→ Attendance record identity = **student + session** (plus partition key for PG).

Other indexes: `(student_id, attendance_date)`, `(academic_year_id, attendance_date)`, `(school_id, attendance_date)`.

### 4.4 `attendance.daily_section_summary`

| Column | Type | Notes |
|--------|------|-------|
| section_id + attendance_date | PK | |
| school_id, academic_year_id | FK RESTRICT | |
| total_students, present_count, absent_count, late_count | SMALLINT | |
| updated_at | timestamp without tz | |

**Purpose:** dashboard projection (documented P0). Refreshed by legacy batch service after write.

---

## 5. Integrity Audit

| Guarantee | State | Evidence |
|-----------|-------|----------|
| School isolation (records) | **PARTIAL** | RLS fail-closed on records; no FORCE; sessions/summary unprotected |
| Academic year on records | **DATABASE-ENFORCED** | NOT NULL + FK + partition key |
| Student/session uniqueness | **DATABASE-ENFORCED** | UNIQUE `(session_id, student_id, academic_year_id)` |
| Duplicate session prevention | **NOT ENFORCED** | No UNIQUE on section/subject/date/period |
| Session lifecycle integrity | **NOT ENFORCED** | status SMALLINT unconstrained; no close lock |
| Record lifecycle / immutability | **NOT ENFORCED** | UPDATE allowed; legacy upsert overwrites |
| Referential integrity | **DATABASE-ENFORCED** | All core FKs RESTRICT (period SET NULL; recorded_by SET NULL) |
| Historical preservation | **PARTIAL** | No hard-delete cascade; cancel enrollment does not delete rows; **prior status overwritten** by upsert |
| Enrollment required | **DATABASE-ENFORCED** | `enrollment_id NOT NULL` |
| Active enrollment at mark time | **NOT ENFORCED** | No trigger/CHECK tying date to enrollment effective window |
| Status vocabulary (1/2/3) | **DOCUMENTED** only | dictionary; **NOT** CHECK |
| Reject-delete triggers | **MISSING** | Unlike Graduation/Certificates |

---

## 6. RLS / Security Audit

| Table | Requires RLS? (docs + risk) | LIVE | FORCE | Recommendation |
|-------|----------------------------|------|-------|----------------|
| `attendance.records` | Yes | ENABLE + fail-closed | **No** | **R3 security hardening** — acceptable temporary for R1 **if** SchoolContext + app auth always set; still **PRODUCTION BLOCKER** at scale |
| `attendance.sessions` | Yes (tenant leakage risk) | **None** | No | **R3** (may need `school_id` denormalize — **DDL**, separate auth) |
| `attendance.daily_section_summary` | Yes (docs claim) | **None** | No | **R3** |
| `timetable.periods` | Soft | None | No | Defer |

**Execution roles:** Superuser / `BYPASSRLS` bypass RLS. Table owner without FORCE can bypass ENABLE-only RLS — same pattern as Enrollment.

**Application reliance:** `SchoolContext` middleware pattern is the intended primary control (`rls-policies.md`). Attendance has **no** permission constants yet.

```text
R1 implementation blocker?     NO for CQRS scaffolding — YES if claiming production security complete
R3 security hardening?         YES (FORCE + sessions/summary policies)
Acceptable temporary condition? YES for Design Lock / unit-authorized Application R1 under SchoolContext
```

---

## 7. Domain Boundary Audit

| Concern | Attendance | Enrollment | Teachers | Exams | Results |
|---------|------------|------------|----------|-------|---------|
| Student identity | consumes FK | owns lifecycle | — | consumes | — |
| Enrollment validity | **must check** at mark (proposed) | **owns** | — | — | — |
| Teacher assignment | session.teacher_id FK only | section placement | **owns** (future CQRS) | — | — |
| Daily attendance fact | **owns** | — | — | — | — |
| Exam attendance / seating | **out of scope** | — | — | **owns** exam_enrollments | — |
| Attendance section daily summary | **owns** (projection) | — | — | — | — |
| Term/annual aggregates for transcript | read-only feed later | — | — | — | **owns** (missing) |
| Transcript artifacts | **NOT** | — | — | — | **owns** |
| Graduation evidence | may cite later; **not** R1 | — | — | — | / Graduation |

**Enrollment boundary (locked for design):**

```text
Enrollment = identity / placement / cancel lifecycle
Attendance = daily participation fact for an enrollment + session

Historical attendance MUST remain after enrollment cancel (RESTRICT FK).
R1 MUST define whether marking requires ACTIVE enrollment covering session_date
  (PROPOSED APPLICATION RULE — not currently DB-enforced).
Transfer/re-entry: OUT OF SCOPE (domains MISSING).
```

**Section / class boundary:**

```text
Session REQUIRES section_id + subject_id + teacher_id (+ optional period_id).
R1 depends on existing enrollment.sections / curriculum.subjects / teachers.teachers rows.
R1 does NOT implement Teachers Assignment CQRS (accept teacher_id as input + FK).
Homeroom vs subject-section ambiguity remains as in Core Audit — do not redesign.
```

---

## 8. Session Lifecycle Design

### Evidence

- Column `sessions.status SMALLINT NOT NULL DEFAULT 1`
- **No** documented mapping for session status in dictionary (record statuses only)
- **No** CHECK, trigger, or Application enum

### Classification of rules

| Topic | CURRENTLY ENFORCED | PROPOSED APPLICATION RULE (R1 Design Lock) | PROPOSED DATABASE RULE | DEFERRED |
|-------|--------------------|---------------------------------------------|------------------------|----------|
| Create session | FK only | Authorized actor; section/year/school consistent | Optional UNIQUE natural key | Year partition DDL |
| Who creates | Unknown | Teacher / attendance.mark or create permission | — | Assignment SoD |
| Modify open session | Allowed | Only while OPEN | — | |
| Immutable when | Not enforced | After CLOSE: no mark; correct only with correct permission | Optional status CHECK | LOCKED state |
| Reopen | Not enforced | Privileged reopen permission | — | Policy HD |
| Cancel session | Not enforced | Soft cancel status; no DELETE | Reject hard delete | Trigger like certificates |
| Date in academic year | Not enforced | App validates against academic year bounds | Optional CHECK/trigger | Academic CQRS |
| Section required | **YES** FK | — | — | |
| Teacher required | **YES** FK | Teacher must exist; assignment validation soft | — | Teachers CQRS |
| DELETE historical | Possible at SQL | **Forbidden** in Application | Reject-delete trigger | R3/DDL |

**Proposed session status vocabulary (DESIGN ONLY — requires Design Lock confirmation):**

```text
1 = OPEN
2 = CLOSED
3 = CANCELLED
```

Do **not** implement until Design Lock. Alternative: keep status opaque and only use OPEN/CLOSED in R1.

---

## 9. Attendance Record Lifecycle Design

### Evidence conflict

| Source | Pattern |
|--------|---------|
| Legacy `AttendanceBatchService` | **UPSERT** overwrite status/notes/recorded_by |
| `batch-write-patterns.md` | Upsert for idempotent section submit |
| Grades Application | **VOID + INSERT** new row |
| Graduation | Versioned rows |
| Constitution | No hard-delete of official academic records |

### Schema capability

- Single current row per `(session_id, student_id, academic_year_id)`
- `updated_at` present; **no** version_no / voided_at / prior_status table

### Recommendation (Design Lock required — ATT-D2)

```text
R1 (schema-compatible, no DDL):
  MARK (session OPEN):
    INSERT or UPSERT same natural key — retry-safe for same submission
  CORRECT (session CLOSED or privileged):
    UPSERT status/notes with mandatory reason
    Outbox audit event capturing previous_status → new_status
  NEVER hard DELETE records
  Historical prior values: APPLICATION-AUDITED only (not row history)

DEFERRED (requires separate DDL Design Lock):
  VOID+INSERT or attendance_record_versions
  Reject-delete triggers
```

**Rationale:** Choosing Grades-style VOID+INSERT without DDL is impossible. Choosing silent overwrite without audit violates auditability. R1 compromise = **controlled upsert + mandatory correction audit**, with versioning explicitly deferred.

**Must remain immutable (R1):**

```text
session_id, student_id, enrollment_id, academic_year_id, school_id, attendance_date
(identity + tenant + date facts once written)
```

Mutable under rules: `status`, `notes`, `recorded_by`, `updated_at`.

---

## 10. Status Model

### Record status (FACT — documented)

| Code | Meaning | Source |
|------|---------|--------|
| 1 | present | `database-dictionary.md` + summary refresh SQL |
| 2 | absent | same |
| 3 | late | same |

| Aspect | Classification |
|--------|----------------|
| Definition location | DOCUMENTED (dictionary / batch service filters) |
| Stable identifiers | Yes (SMALLINT codes) |
| Extensible | Soft — no CHECK; new codes would break summary filters |
| Status change auditable | **NOT ENFORCED** today |
| Excused / unexcused | **MISSING** (notes free-text only) |
| Partial attendance | **MISSING** (late is nearest) |
| Absence reason typed | **MISSING** |

```text
FACT:     status SMALLINT on records; counts in daily_section_summary
DERIVED:  summary counts; MV attendance_percentage
POLICY:   which codes count as “absent for promotion”; excused rules — DEFERRED
```

Session status: **UNKNOWN** / undocumented — Design Lock ATT-D1.

---

## 11. CQRS Candidate Catalog (DESIGN ONLY)

Derived from schema + legacy batch + Enrollment/Grades conventions. **Not implemented.**

### Commands (R1 proposed)

#### `CreateAttendanceSession`

| Field | Value |
|-------|-------|
| Purpose | Open a marking session for section/subject/date |
| Actor | Teacher / registrar with create |
| Permission | `attendance.session.create` |
| Input | schoolId, academicYearId, sectionId, subjectId, sessionDate, teacherId, periodId?, idempotencyKey? |
| Aggregate | AttendanceSession |
| Transaction | Single insert session |
| Idempotency | Recommended (HTTP retry) |
| Side effects | Outbox `AttendanceSessionCreated` (proposed) |
| Failures | FK miss; duplicate natural key (if locked); auth; school mismatch |

#### `MarkSectionAttendance`

| Field | Value |
|-------|-------|
| Purpose | Batch mark all/partial students for a session |
| Actor | Teacher |
| Permission | `attendance.mark` |
| Input | sessionId, schoolId, academicYearId, records[{studentId, enrollmentId, status, notes?}], recordedBy, idempotencyKey |
| Aggregate | Session + records set |
| Transaction | Session OPEN check + chunked upsert + summary refresh **same logical unit** (or outbox → job for large N) |
| Idempotency | **Required** (peak-hour retries) |
| Side effects | Upsert records; refresh `daily_section_summary`; outbox `SectionAttendanceMarked` |
| Failures | Session not OPEN; enrollment inactive (policy); status invalid; cross-school; empty batch |

#### `CorrectAttendanceRecord`

| Field | Value |
|-------|-------|
| Purpose | Change status after initial mark / after close |
| Actor | Privileged teacher / admin |
| Permission | `attendance.correct` |
| Input | sessionId, studentId, academicYearId, newStatus, reason, recordedBy, idempotencyKey |
| Transaction | Read previous → upsert → audit outbox |
| Idempotency | Required |
| Side effects | Summary refresh; `AttendanceCorrected` outbox |
| Failures | Missing reason; session CANCELLED; auth |

#### `CloseAttendanceSession`

| Field | Value |
|-------|-------|
| Purpose | Freeze routine marking |
| Permission | `attendance.session.close` |
| Idempotency | Recommended |
| Side effects | status→CLOSED; outbox |

#### Deferred commands (OUT OF R1)

```text
ReopenAttendanceSession   — policy / SoD
CancelAttendanceSession   — policy
ImportAttendanceBatch     — bulk COPY path
LockAttendanceSession     — stronger than close
```

### Queries (R1 proposed)

| Query | Purpose | Permission |
|-------|---------|------------|
| `GetAttendanceSession` | Session header + optional records | `attendance.view` |
| `ListAttendanceSessions` | Filter by section/date/year | `attendance.view` |
| `GetSectionAttendance` | Section × date sheet | `attendance.view` |
| `GetStudentAttendance` | Student history (year-scoped) | `attendance.view` |
| `GetDailySectionSummary` | Read summary row(s) | `attendance.view` |

**Defer:** school-wide analytics, directorate MVs refresh orchestration, promotion absence tallies, transcript feeds.

---

## 12. Authorization Design

Reuse `Permission` + `config/security.php` + role seed pattern (Enrollment/Grades).

### Proposed permissions (justified)

| Code | Intent |
|------|--------|
| `attendance.view` | Read sessions/records/summary |
| `attendance.session.create` | Create session |
| `attendance.mark` | Mark / batch mark while OPEN |
| `attendance.correct` | Correct after mark / closed |
| `attendance.session.close` | Close session |
| `attendance.administer` | Optional catch-all for school admin (or omit if close+correct suffice) |

**Defer:** `reopen`, `cancel` until Design Lock.

**SoD:** R1 soft — prefer correct ≠ same as mark only if policy demands (Grades separates create/correct). **Proposed:** separate `mark` vs `correct` like grades create/correct. No evaluator/approver SoD (Graduation-specific).

**Conditional:** Adding permissions requires config/seeder changes — **separate implementation authorization**, not part of this audit.

---

## 13. Idempotency Design

Reuse `audit.idempotency_keys` + `IdempotencyStore` (Enrollment/Grades).

| Operation | Idempotency | Why |
|-----------|-------------|-----|
| CreateAttendanceSession | Recommended | Double-click / retry |
| MarkSectionAttendance | **Required** | Peak hour, queue, client retry |
| CorrectAttendanceRecord | **Required** | Operator retry |
| CloseAttendanceSession | Recommended | Safe replay |
| Batch/queue worker | **Required** | Worker retry |

Natural DB uniqueness already prevents duplicate student/session rows; idempotency store prevents duplicate **side effects** / ambiguous partial retries.

---

## 14. Transaction Design

| Operation | Atomic boundary | Partial failure |
|-----------|-----------------|-----------------|
| Create session | Single row | Fail closed |
| Mark ≤ ~50 students | One DB transaction: upsert all + summary refresh | All-or-nothing |
| Mark large / school import | Accept command → stage outbox/job → chunk 500; job-level idempotency | Retry chunk; summary refresh at end |
| Correct single | One transaction + outbox stage | Fail closed |
| Close session | Update status | Fail if not OPEN |

**HTTP must not** row-loop 45K inserts (Constitution / Phase C).

---

## 15. Outbox / Audit Design

| Operation | Idempotency row | Outbox domain event (proposed names) | Audit |
|-----------|-----------------|--------------------------------------|-------|
| Create session | optional | `AttendanceSessionCreated` | yes |
| Mark section | yes | `SectionAttendanceMarked` | yes |
| Correct | yes | `AttendanceCorrected` (include previous_status) | **mandatory** |
| Close | optional | `AttendanceSessionClosed` | yes |

Events are **proposed contracts only** — not registered.

MV refresh remains **async / scheduled**, not in-request (existing reports design).

---

## 16. Batch / Scale Assessment

| Concern | CURRENT CAPABILITY | EXPECTED BOTTLENECK | REQUIRED FUTURE OPTIMIZATION | PHASE |
|---------|--------------------|---------------------|------------------------------|-------|
| Schema volume | LIST partition + indexes | DEFAULT partition holds all years | Year partitions `FOR VALUES IN (year_id)` | Scale / R3+ |
| Section mark (~50) | Upsert chunk 500 in legacy service | Peak 8:00 concurrency | Queue `attendance-writes` + workers | R1 ops |
| School-wide import | Pattern documented (COPY) | HTTP timeout | Queue + COPY | Deferred |
| Historical student query | Index (student_id, date) + year prune | Cross-year DEFAULT scan | Year partitions + prune | Scale |
| Dashboard | `daily_section_summary` + MV | Stale MV | Scheduled REFRESH | Ops |
| Application write path | Legacy service only | Controllers calling row inserts | Application CQRS + job | **R1** |

```text
Schema readiness ≠ Application write-path readiness.
Schema: YELLOW-GREEN foundation.
Write path: RED until CQRS + queue discipline.
```

---

## 17. Test Strategy (Design Only — do not implement)

### Unit

- Status code validation (1/2/3)
- Session OPEN/CLOSED/CANCELLED transitions
- Mark rejected when CLOSED
- Correct requires reason
- Permission deny paths
- Idempotency cache hit returns same result

### Feature

- Create session → mark → get section attendance
- Correct → summary counts update
- Close → mark fails
- Cancel enrollment → historical records still readable

### Security

- Cross-school mark denied (SchoolContext)
- RLS tester role without school setting → 0 rows on records
- FORCE RLS tests when R3 authorized
- Permission denial for view/mark/correct

### Database

- UNIQUE session+student enforced
- FK RESTRICT behavior
- Inserts route to DEFAULT partition (current)
- No hard delete from Application

### Concurrency

- Double mark same payload → one row, idempotent
- Concurrent correct last-write + audit both staged (document expected)

### Scale

- Chunked mark 500
- Query filtered by academic_year_id uses year index (EXPLAIN when authorized)

---

## 18. Scope Lock

### IN SCOPE (proposed R1 Application foundation — after Design Lock + separate IMPLEMENT authorization)

```text
- Application/Attendance + Domain/Attendance (Clean Architecture)
- Commands: CreateAttendanceSession, MarkSectionAttendance,
            CorrectAttendanceRecord, CloseAttendanceSession
- Queries: GetAttendanceSession, ListAttendanceSessions,
           GetSectionAttendance, GetStudentAttendance, GetDailySectionSummary
- Migrate behavior from AttendanceBatchService into Infrastructure write path
- Idempotency + outbox for mark/correct
- Application enrollment ACTIVE check (policy from Design Lock)
- Unit + feature + RLS (ENABLE) tests
```

### OUT OF SCOPE

```text
Results / Transcript / Promotion / Transfer / Re-entry
Graduation / Certificates / Grades changes
Exam attendance
Excused/unexcused catalog / absence reason taxonomy
Teachers Assignment CQRS
Exam scheduling
VOID+INSERT attendance versioning DDL
FORCE RLS / sessions.school_id DDL
Year-specific partitions
Finance / communication / AI predictions
HTTP surface (unless separately authorized after CQRS)
Advanced analytics / directorate product UI
```

### CONDITIONAL (separate authorization required)

```text
- New attendance.* permissions + seeder/config
- FORCE RLS on records; RLS on sessions/summary
- DDL: school_id on sessions; CHECK status; UNIQUE session natural key;
      reject-delete triggers; year partitions; TIMESTAMPTZ fixes
- Queue workers / horizon config for attendance-writes
- Reopen/Cancel session commands
```

---

## 19. Blockers

| ID | Severity | Type | Finding | Evidence | Why it matters | Blocks impl? | Blocks prod? | Phase |
|----|----------|------|---------|----------|----------------|--------------|--------------|-------|
| ATT-001 | P0 | DESIGN BLOCKER | Session status vocabulary undefined | LIVE status SMALLINT; no dict/CHECK | Cannot safely close/lock | **Yes** until Design Lock | Yes | Design Lock |
| ATT-002 | P0 | DESIGN BLOCKER | Correction vs upsert vs VOID unresolved | Legacy upsert vs Grades VOID | Audit/history risk | **Yes** until ATT-D2 | Yes | Design Lock |
| ATT-003 | P0 | DESIGN BLOCKER | No attendance permissions | `Permission.php` | Cannot authorize CQRS | **Yes** for secured R1 | Yes | Design Lock + auth implement |
| ATT-004 | P1 | SECURITY BLOCKER | records RLS ENABLE without FORCE | LIVE `force_rls=false` | Owner bypass | No for Design; soft for R1 app | **Yes** | R3 |
| ATT-005 | P1 | SECURITY BLOCKER | sessions + summary have no RLS | LIVE | Tenant leak via session list | Soft if app-scoped | **Yes** | R3 (+ possible DDL) |
| ATT-006 | P1 | IMPLEMENTATION BLOCKER | No Application/Domain/CQRS | Repo absent | Cannot ship ops | Cleared by R1 implement auth | Yes | R1 implement |
| ATT-007 | P1 | DESIGN BLOCKER | Active enrollment-at-date rule undefined | enrollment_id NOT NULL only | Illegal marks possible | **Yes** until ATT-D4 | Soft | Design Lock |
| ATT-008 | P2 | DEFERRED ENHANCEMENT | Only DEFAULT partition | LIVE partitions | Scale pruning weak | No | Soft→Yes at multi-year volume | Scale |
| ATT-009 | P2 | DEFERRED ENHANCEMENT | No session natural UNIQUE | LIVE indexes | Duplicate sessions | Soft (app unique) | Soft | Optional DDL |
| ATT-010 | P2 | DEFERRED ENHANCEMENT | No CHECK on record status | LIVE | Invalid codes | Soft (app validate) | Soft | Optional DDL |
| ATT-011 | P2 | DEFERRED ENHANCEMENT | Excused/reason model missing | Schema | Policy incomplete | No | Soft | Later |
| ATT-012 | P2 | DEFERRED ENHANCEMENT | Teachers Assignment CQRS missing | Core audit | Assignment SoD weak | No (FK teacher_id OK) | Soft | Teachers phase |
| ATT-013 | P3 | DEFERRED ENHANCEMENT | Legacy service outside Architecture | `@architecture-legacy-allowed` | Debt | No | Soft | R1 migrate |
| ATT-014 | P3 | DEFERRED ENHANCEMENT | Doc vs LIVE (FORCE/sessions RLS) | rls-policies.md | Confusion | No | No | Doc sync |

---

## 20. Readiness Score

| Dimension | Score /10 | Band | Notes |
|-----------|----------:|------|-------|
| Database | 7 | YELLOW | Solid tables/FKs/partition parent; DEFAULT-only; no school on sessions |
| Integrity | 6 | YELLOW | Strong FKs + student/session unique; weak lifecycle CHECKs |
| Security | 4 | RED | Partial RLS; no FORCE; no permissions; sessions unprotected |
| Domain Model | 3 | RED | Implicit via schema; no Domain layer; status gaps |
| CQRS Readiness | 5 | YELLOW | Clear candidate catalog; peers exist; nothing built |
| Tests | 1 | RED | No attendance domain tests |
| Operations | 4 | RED | Legacy batch only; no queue wiring in code |
| Scale | 6 | YELLOW | Partition design intent good; year slices missing |

```text
TOTAL (diagnostic): 36 / 80
OVERALL COLOR: YELLOW → RED lean (application/security)
```

Scores do not authorize implementation.

---

## 21. Final Gate

```text
ATTENDANCE APPLICATION READINESS GATE

Overall:
  READY WITH CONDITIONS

Conditions (must clear before IMPLEMENT authorization):
  1. Design Lock ATT-D1: session status + lifecycle
  2. Design Lock ATT-D2: correction/history model (R1 upsert+audit vs deferred versioning)
  3. Design Lock ATT-D3: permission catalog + role mapping
  4. Design Lock ATT-D4: enrollment validity rule for marking
  5. Explicit human phrase authorizing CQRS implementation (separate from this gate)
  6. RLS FORCE / sessions RLS remain R3 — accepted temporary for R1 Design/CQRS under SchoolContext

Not READY for production go-live.
Not READY for unchecked implementation.
Schema is sufficient to Design Lock then implement Application without mandatory DDL,
  PROVIDED correction model stays schema-compatible.
```

---

## 22. Explicit Authorization Status

```text
ATTENDANCE APPLICATION READINESS GATE

Overall:
  READY WITH CONDITIONS

Implementation Authorization:
  NOT GRANTED

DDL Authorization:
  NOT GRANTED

RLS Mutation Authorization:
  NOT GRANTED

CQRS Implementation:
  NOT GRANTED

Migration Authorization:
  NOT GRANTED

Recommended Next Action:
  ATTENDANCE APPLICATION DESIGN LOCK
  (resolve ATT-D1..ATT-D4 only — no code, no DDL)

Human Approval Required:
  YES
```

---

## Mutation Check

```text
PHP / MIGRATION / DDL / RLS / CODE / TESTS MODIFIED: NONE
Deliverable only:
.cursor/database/phase-attendance/01-ATTENDANCE-APPLICATION-READINESS-GATE.md
```

**STOP** — do not continue into Design Lock or implementation without a new human authorization.
