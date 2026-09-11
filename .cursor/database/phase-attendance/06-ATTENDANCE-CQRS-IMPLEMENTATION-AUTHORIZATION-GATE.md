# SIS DATABASE — PHASE R1  
# ATTENDANCE APPLICATION  
# R1.4 — CQRS IMPLEMENTATION AUTHORIZATION GATE

**Date:** 2026-09-11  
**Mode:** AUDIT + DESIGN VERIFICATION + IMPLEMENTATION AUTHORIZATION PREPARATION ONLY  
**Mutation:** This file only  

```text
CQRS IMPLEMENTATION: NOT AUTHORIZED
HTTP/API: NOT AUTHORIZED
DDL/RLS: NOT AUTHORIZED
R1.5: NOT STARTED
```

**Upstream:**  
01 Readiness · 02 Design Lock · 03 Role Approval · 04 Impl Auth Gate · 05 Security Package PASS  

---

## 1. Executive Verdict

```text
R1.4 CQRS DESIGN: READY

All nine locked operations (4 commands + 5 queries) are implementable
against LIVE schema + existing CQRS/idempotency/outbox/security infrastructure
WITHOUT design change and WITHOUT DDL/RLS mutation.

BLOCKING ISSUES: NONE

Deferred ATT-SEC-001..004 do not block Application CQRS under
SchoolContext + AttendancePolicy (R1.3 PASS) + ATT-D5 resolution.

CQRS IMPLEMENTATION: NOT AUTHORIZED
(await explicit human implement phrase)
```

**Note on “seven” vs nine:** Design Lock + this gate cover **4 commands + 5 queries = 9** operations. The stage prompt’s “seven” count is treated as a wording slip; readiness is assessed for the full locked catalog.

---

## 2. R1.4 Scope (authorization package proposal)

### IN SCOPE (when later authorized)

```text
app/Application/Attendance/**   Commands, Queries, DTOs, Results, Contracts
app/Domain/Attendance/**        Events, Exceptions, Status/Session VOs, guards
app/Infrastructure/Persistence/Attendance/**  Read/Write repositories
ArchitectureServiceProvider bindings
EloquentOutboxRepository::rehydrateEvent match arms for Attendance events
Unit + Feature(DB) tests for CQRS (no HTTP)
Optional: private infra helper extracting upsert/summary from legacy service
architecture:validate --fitness / feature-check Attendance
```

### OUT OF SCOPE

```text
HTTP / controllers / routes / FormRequests
DDL / migrations / RLS / FORCE RLS / session.school_id
attendance.administer / reopen / cancel
VOID+INSERT / record_versions (ATT-D2-FUTURE)
AttendanceBatchService as public writer
Permission/role changes
R1.5
```

---

## 3. Repository Evidence

| Component | Evidence | Compatible? |
|-----------|----------|-------------|
| Permissions R1.3 | `Permission::ATTENDANCE_*`, `config/security.php` Option B roles | YES — consume, do not redesign |
| AttendancePolicy + AttendanceSchoolAccessService | `app/Security/Policies/AttendancePolicy.php` | YES — HTTP later; handlers follow peer school checks |
| SchoolContext | Middleware + `SchoolContext` | YES |
| UnitOfWork | `EloquentUnitOfWork` via ArchitectureServiceProvider | YES |
| IdempotencyStore | `audit.idempotency_keys`; key+command_name; store **after** tx (Grades) | YES — do not invent hashing |
| OutboxRepository | `stage(DomainEvent)`; `event_type` = FQCN; rehydrate match | YES — add Attendance arms at implement time |
| Peer CQRS | Enrollment / Exams(Grades) / Graduation | YES — Command/Handler/Result/DTO pattern |
| Attendance schema | sessions, records (partitioned), daily_section_summary | YES — no DDL required |
| Unique record key | LIVE `(session_id, student_id, academic_year_id)` | YES |
| AttendanceBatchService | Legacy upsert + `refreshDailySummary`; uses `today()` | Reusable **internals only** with wrap |
| Academic year bounds | `academic.academic_years.start_date/end_date` (migration) | YES — load via Attendance infra port (no Academic CQRS exists) |
| Section→school | `sections.class_id` → `classes.school_id` | YES — join in Attendance repository (no ATT-SEC-004) |
| Enrollment validity columns | enrollments: student/school/year/section, effective_from/to, status | YES — ATT-D4 predicate |
| Authorization in Application handlers | Enrollment/Grades handlers do **not** call Permission | YES — peer pattern: school on command; permission at HTTP (R1.5) |
| Application/Attendance | Absent | Expected — to be created |
| Feature contract Attendance | Not yet | Create under implement auth |

---

## 4. Command-by-Command Readiness

| Command | Design complete? | Schema OK? | Infra OK? | Gaps | Ready? |
|---------|------------------|------------|-----------|------|--------|
| CreateAttendanceSession | YES (ATT-D1 OPEN) | YES | UoW/outbox/idempotency | App duplicate check optional; resolve school via section→class; load year dates | **READY** |
| MarkSectionAttendance | YES (ATT-D6 partial) | YES unique | Batch upsert pattern + summary SQL exists | Must use **session_date** not `today()`; OPEN check in tx; ATT-D4 | **READY** |
| CorrectAttendanceRecord | YES (ATT-D2) | YES upsert | Outbox previous/new | No VOID+INSERT | **READY** |
| CloseAttendanceSession | YES OPEN→CLOSED | YES status column | Optimistic `WHERE status=OPEN` | Already CLOSED ⇒ fail unless idempotent replay | **READY** |

No command requires design change or DDL.

---

## 5. Query-by-Query Readiness

| Query | Design complete? | Isolation | Notes | Ready? |
|-------|------------------|-----------|-------|--------|
| GetAttendanceSession | YES | School via section→class | Optional records include | **READY** |
| ListAttendanceSessions | YES | School filter + pagination required | Prefer year/date filters for indexes | **READY** |
| GetSectionAttendance | YES | Section school resolve | Records SSOT | **READY** |
| GetStudentAttendance | YES | School + **required academic_year_id** | Partition-friendly | **READY** |
| GetDailySectionSummary | YES | School on summary.school_id | Projection only | **READY** |

---

## 6. Enrollment Dependency Audit

| Rule | Implementable without redefining Enrollment? |
|------|-----------------------------------------------|
| student/school/year/section match | YES — read enrollment row |
| `effective_from <= session_date` | YES |
| `effective_to IS NULL OR session_date <= effective_to` | YES |
| Cancelled but in window | YES — date window, not `isActive()` alone |
| After `effective_to` | Reject | YES |
| Future enrollment | Reject | YES |
| Later cancel preserves attendance | YES — FK RESTRICT; no delete |

```text
Enrollment remains authoritative. Attendance consumes ATT-D4 predicate only.
No Enrollment schema/API redesign required.
```

**Note:** `EnrollmentStatus::isActive` (ACTIVE ∧ effective_to null) is **insufficient alone** for ATT-D4; Attendance Domain/Application must implement the **date-window** predicate (Design Lock). Not a conflict — do not change EnrollmentStatus semantics.

---

## 7. School Isolation Audit

| Path | Mechanism | Sufficient for R1.4 CQRS? |
|------|-----------|---------------------------|
| Session school | section → class.school_id | YES — fail closed |
| Record school_id | Denormalized write = resolved school | YES — must match enrollment.school_id |
| SchoolContext | Required on operations | YES |
| RLS FORCE | Deferred ATT-SEC-001 | Acceptable temporary (Design Lock + R1.3) |
| sessions/summary RLS | Deferred ATT-SEC-002/003 | App joins/filters required |
| session.school_id column | ATT-SEC-004 deferred | **Must not add in R1.4** |

```text
Permission ≠ cross-school bypass — LOCKED and evidenced by AttendanceAuthorizationTest.
```

---

## 8. Idempotency Audit

| Item | Evidence | R1.4 rule |
|------|----------|-----------|
| Store | `IdempotencyStore` / `audit.idempotency_keys` | Reuse only |
| Key scope | `(key, command_name)` | Unchanged |
| Body hash | Not in store | **Do not add** |
| Ordering (Grades) | Idempotency find → business `unitOfWork->transaction` (outbox inside) → `idempotency->store` after success | **Follow exactly** |
| Mark / Correct | Required | YES |
| Create / Close | Recommended | YES |

No idempotency infrastructure change required.

---

## 9. Audit / Outbox Audit

| Event (locked name) | Outbox fit | Conflict? |
|---------------------|------------|-----------|
| AttendanceSessionCreated | New `DomainEvent` class + `stage()` | NONE — FQCN event_type |
| SectionAttendanceMarked | same | NONE |
| AttendanceCorrected | same; payload must include previous/new status/notes + reason | NONE |
| AttendanceSessionClosed | same | NONE |

| Work at implement time | Classification |
|------------------------|----------------|
| Add classes under `Domain/Attendance/Events` | Implementation |
| Extend `EloquentOutboxRepository::rehydrateEvent` match | Implementation (same pattern as Grades/Graduation) |
| Optional SecurityAuditLogger listeners/bridges | Optional / follow Enrollment; Correct outbox is mandatory |

```text
CONFLICT — MUST RESOLVE BEFORE IMPLEMENTATION: NONE
```

Do not create a second outbox or audit framework.

---

## 10. Concurrency Audit

| Scenario | Locked behavior | Implementable? |
|----------|-----------------|----------------|
| Mark vs Close | Close: `UPDATE WHERE status=OPEN`; Mark: revalidate OPEN inside tx | YES |
| Correct vs Correct | Last write wins; each audited if distinct keys | YES |
| Correct after Close | Allowed | YES |
| Mark after Close | Reject | YES |
| Idempotent retry | Cached result / unique upsert | YES |

No unapproved locking strategy required (no SELECT FOR UPDATE mandated). Optimistic status predicate is sufficient.

---

## 11. Daily Summary Audit

| Item | Evidence |
|------|----------|
| Table | `attendance.daily_section_summary` PK `(section_id, attendance_date)` |
| Refresh logic | `AttendanceBatchService::refreshDailySummary` — SQL upsert aggregating status 1/2/3 |
| Atomicity | Can run inside same UnitOfWork transaction as record upserts |
| Triggers | NONE (LIVE) — app refresh required |

```text
CQRS dependency: handlers MUST refresh summary after Mark/Correct (Design Lock).
Reuse/adapt refresh SQL into Infrastructure under handler control.
Do not redesign table. Do not add triggers/DDL.
```

**Incompatibility if calling legacy `recordSectionAttendance`:** uses `today()` and skips OPEN/enrollment checks — **must not** call as public path.

---

## 12. Legacy Service Compatibility Audit

| Behavior | Compatible with Design Lock? |
|----------|------------------------------|
| Upsert on natural key | YES (Mark/Correct write shape) |
| Chunk 500 | YES (hard cap alignment) |
| `today()` for attendance_date | **NO** — must use `session.session_date` |
| No OPEN check | **NO** |
| No ATT-D4 | **NO** |
| Silent overwrite without Correct audit | **NO** for corrections |
| Summary refresh | YES as private helper |

```text
R1 authoritative writer = Application/Attendance CQRS only.
Legacy service must not remain an alternate public writer.
Internal reuse: extract upsert/summary into Infrastructure; handlers own validation.
Do NOT migrate/rewrite legacy during this authorization stage.
```

---

## 13. CQRS Architecture Compatibility

| Convention | Peer evidence | Attendance approach |
|------------|---------------|---------------------|
| Namespaces | `Application/{Context}`, `Domain/{Context}` | `Attendance` |
| Commands/Handlers/Results | Enrollment, Exams | Same |
| Queries/DTOs | Enrollment, Exams, Graduation | Same |
| Domain events + Outbox | All write peers | Same |
| Repositories | Domain/Application port + Eloquent infra | Same |
| DI | ArchitectureServiceProvider | Bind Attendance repos |
| Scaffold | `sis:make-command` / `sis:make-query` | Preferred |
| Handler authz | HTTP Policy, not Application | Same — R1.5 for HTTP; handlers enforce school/lifecycle |
| Exceptions | Domain exceptions | Attendance-specific exceptions |
| Tests | Unit handlers + Feature PG | Same; no HTTP tests in R1.4 |

No parallel CQRS framework.

---

## 14. Domain Boundary Assessment

| Object | Needed in R1.4? | Rationale |
|------|-----------------|-----------|
| AttendanceSession status VO (OPEN/CLOSED/CANCELLED) | **YES** | ATT-D1 |
| AttendanceRecord status VO (PRESENT/ABSENT/LATE) | **YES** | Dictionary 1/2/3 — app enforce, no DB CHECK |
| Full rich Aggregate root entity | **OPTIONAL / lean** | Peers often use handlers + repo data objects; avoid anemic ceremony |
| Correction reason VO | **SOFT** | Non-empty string guard sufficient |
| DTOs / Results | **YES** | Peer pattern |
| Domain events | **YES** | Outbox |
| Domain exceptions | **YES** | Fail closed |

Do not over-engineer entities for theoretical purity.

---

## 15. Failure Semantics Matrix

| Condition | Create | Mark | Correct | Close | Queries |
|-----------|--------|------|---------|-------|---------|
| Missing SchoolContext / wrong school | Reject | Reject | Reject | Reject | Deny/empty |
| Missing permission | HTTP R1.5 / test harness | same | same | same | same |
| Session not found | — | Reject | Reject | Reject | Not found |
| Session CLOSED | — | Reject | Allow | Fail† | OK |
| Session CANCELLED | — | Reject | Reject | Reject | OK to read |
| Invalid year/date | Reject | — | — | — | — |
| Invalid section/period | Reject | — | — | — | — |
| Invalid/out-of-window enrollment | — | Reject all | — | — | — |
| Duplicate students in payload | — | Reject all | — | — | — |
| Empty payload | — | Reject | — | — | — |
| Payload > 500 | — | Reject | — | — | — |
| Empty correction reason | — | — | Reject | — | — |
| Identity mutation attempt | — | — | Reject | — | — |
| Idempotent replay | Cached success | Cached | Cached | Cached | — |
| Close race (0 rows updated) | — | — | — | Fail† | — |

† Already CLOSED without matching idempotency cache ⇒ fail (Design Lock). With idempotency cache ⇒ success replay.

HTTP status codes: **out of scope** (no R1.5).

---

## 16. Required Test Matrix (do not implement now)

### CreateAttendanceSession

valid · wrong school · missing context · invalid year/date · invalid section · invalid period · idempotent replay · optional app duplicate detection

### MarkSectionAttendance

valid partial · omitted unchanged · duplicate student IDs · unknown enrollment · wrong section · future enrollment · out-of-window · cancelled after effective_to · cancelled in-window allow · empty · >500 · CLOSED session · wrong school · missing context · idempotent replay · Mark/Close race

### CorrectAttendanceRecord

valid OPEN · valid CLOSED · CANCELLED reject · empty reason · immutable identity · wrong school · missing context · idempotent replay · outbox previous/new · summary refresh

### CloseAttendanceSession

OPEN→CLOSED · already CLOSED fail · idempotent replay · wrong school · missing context · Mark after close fails

### Queries

same-school · cross-school deny · missing context · academic year required (student) · section scope · summary projection not SSOT

### Regression

legacy public writer not used; summary counts match statuses 1/2/3

---

## 17. Deferred Security Dependencies

| ID | Item | Blocks R1.4 CQRS? |
|----|------|-------------------|
| ATT-SEC-001 | FORCE RLS records | **NO** — app SchoolContext + fail-closed RLS ENABLE |
| ATT-SEC-002 | RLS sessions | **NO** — join/filter by resolved school |
| ATT-SEC-003 | RLS summary | **NO** — filter school_id |
| ATT-SEC-004 | session.school_id | **NO** — must not implement |

If production go-live claimed without ATT-SEC-*, that is a **production** concern — not an R1.4 CQRS design blocker (already accepted in Design Lock).

---

## 18. Blocking Issues

```text
NONE
```

---

## 19. Non-Blocking Risks

| Risk | Severity | Mitigation at implement |
|------|----------|-------------------------|
| Duplicate sessions (no DB unique) | P2 | Optional app natural-key check |
| DEFAULT-only partition | P2 | Writes still work; year partitions later |
| Legacy service misuse | P1 | Do not call `recordSectionAttendance` from handlers |
| Permission not in Application handlers | P2 | Peer pattern; enforce at R1.5 HTTP + policy |
| Outbox rehydrate omission | P2 | Must add match arms with events |
| Summary drift if refresh skipped | P1 | Mandatory in Mark/Correct tx |
| ATT-D4 vs EnrollmentStatus::isActive confusion | P2 | Implement date-window explicitly |

---

## 20. Implementation Scope Proposal

Ordered (for future authorize only):

1. Scaffold `Attendance` feature (`sis:make-feature` / make-command / make-query).  
2. Domain: SessionStatus, RecordStatus, exceptions, four events.  
3. Write repository: sessions CRUD-ish, record upsert, enrollment validity load, section school resolve, academic year bounds, optimistic close, summary refresh.  
4. Read repository: five queries with school filters + pagination.  
5. Handlers: Create, Mark, Correct, Close + five query handlers.  
6. Wire ArchitectureServiceProvider + outbox rehydrate.  
7. Unit handler tests + PostgreSQL feature tests (RLS actor where useful).  
8. Deprecate/document legacy public path; extract private upsert/summary.  
9. `architecture:validate --fitness` + `architecture:feature-check Attendance`.  

---

## 21. Explicitly Forbidden Changes

```text
DDL / migrations / indexes / CHECKs / partitions / triggers
FORCE RLS / new RLS policies
session.school_id column
attendance.administer / reopen / cancel
VOID+INSERT / versioning tables
HTTP routes/controllers
New idempotency or outbox systems
Body hashing in IdempotencyStore
Redefining Enrollment
Expanding roles/permissions
Automatic advance to R1.5
```

---

## 22. Human Authorization Matrix

| Decision | Status |
|----------|--------|
| ATT-D1..D6 design | LOCKED (02) |
| ATT-D3 Option B security | IMPLEMENTED (05 PASS) |
| R1.4 CQRS design readiness | **READY** (this gate) |
| R1.4 CQRS **implementation** | **NOT AUTHORIZED** — needs human phrase |
| R1.5 HTTP | NOT AUTHORIZED |
| DDL/RLS | NOT AUTHORIZED |

Suggested future phrase (not granted now):

```text
APPROVED — IMPLEMENT ATTENDANCE R1.4 CQRS ONLY
(commands/queries/handlers/repos/events/tests per Design Lock 02 + Gate 06)
HTTP/DDL/RLS/BatchService public rewrite: NOT AUTHORIZED
```

---

## 23. Final Gate

```text
SIS DATABASE — PHASE R1
ATTENDANCE APPLICATION
R1.4 — CQRS IMPLEMENTATION AUTHORIZATION GATE
```

## READY FOR HUMAN IMPLEMENTATION AUTHORIZATION

```text
R1.4 CQRS DESIGN:
READY

CQRS IMPLEMENTATION:
NOT AUTHORIZED

HTTP/API:
NOT AUTHORIZED

DDL/RLS:
NOT AUTHORIZED

R1.5:
NOT STARTED

NEXT ACTION:
Human must explicitly authorize R1.4 CQRS implementation.
```

```text
DO NOT IMPLEMENT COMMANDS/QUERIES/HANDLERS/DTOS/TESTS UNTIL AUTHORIZED.
DO NOT ADVANCE TO R1.5 AUTOMATICALLY.
STOP.
```

---

## Mutation check

```text
Files modified: ONLY
.cursor/database/phase-attendance/06-ATTENDANCE-CQRS-IMPLEMENTATION-AUTHORIZATION-GATE.md

app/ · config/ · database/ · routes/ · tests/ · AttendanceBatchService: NONE
```
