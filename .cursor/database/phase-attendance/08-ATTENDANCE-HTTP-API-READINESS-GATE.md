# SIS DATABASE — PHASE R1
# ATTENDANCE APPLICATION
# R1.5 — HTTP/API READINESS GATE

**Date:** 2026-09-11  
**Mode:** AUDIT / READINESS DESIGN ONLY  
**Authorization:** NOT GRANTED (this document does not authorize implementation)

**Upstream:**  
01 Readiness · 02 Design Lock · 03–05 Security · 06 CQRS Authz · **07 CQRS Implementation PASS** · Feature `Attendance.md`

```text
HTTP/API IMPLEMENTATION: NOT AUTHORIZED
DDL/RLS: NOT AUTHORIZED
DO NOT ADVANCE TO IMPLEMENTATION AUTOMATICALLY
```

---

## 1. Executive Verdict

```text
STATUS: READY FOR HUMAN IMPLEMENTATION AUTHORIZATION

R1.5 HTTP/API implementation is NOT authorized by this audit.

Permission catalog (R1.3) is complete and precise.
CQRS contracts (R1.4) are stable and exposable via Application DTOs/Results.
Peer Enrollment/Grades HTTP patterns are sufficient to mirror without redesign.
No unauthorized Attendance HTTP routes exist today.

Human approval is required before implementation.
DDL/RLS remains NOT AUTHORIZED.
```

**Non-blocking conditions for a future implement grant** (document in R1.5 implement auth, do not fix here):

1. Wire `AttendancePolicy` to Laravel Gate (thin Eloquent table mappers **or** `Gate::define` abilities) — policy methods already exist; not registered like Grades.  
2. Gate 07 shorthand labels (`session.create`) ≠ catalog codes (`attendance.session.create`) — use **catalog codes only**.  
3. Nested `records.*.status` is client-writable; root `status` remains prohibited via `SecuritySensitiveFieldGuard`.  
4. Mark/Correct must require header `X-Idempotency-Key` (commands require non-null keys).

---

## 2. Scope Boundary

| In this audit | Out |
|---------------|-----|
| Route/API design matrix | Controllers / routes / FormRequests |
| Authz / validation / error contracts | Permission or role changes |
| Test matrix (design) | Writing tests |
| Conflict search | DDL / RLS / schema |
| | UI / legacy removal / refactor |

```text
DDL = NOT TOUCHED
MIGRATIONS = NOT TOUCHED
RLS = NOT TOUCHED
INDEXES = NOT TOUCHED
PARTITIONS = NOT TOUCHED
DATABASE SCHEMA = NOT TOUCHED
```

---

## 3. Route / API Matrix (proposed only)

Base prefix (peer): `/api/v1` + auth + `SchoolContext` middleware + `X-School-Id`.

| Operation | Verb | Proposed route | Params / query | Body (client) | Auth | Idempotency | Response |
|-----------|------|----------------|----------------|---------------|------|-------------|----------|
| CreateAttendanceSession | POST | `/attendance/sessions` | — | academic_year_id, section_id, subject_id, session_date, teacher_id, period_id? | `attendance.session.create` | Optional `X-Idempotency-Key` | 201 / 200 replay · session id |
| MarkSectionAttendance | POST | `/attendance/sessions/{session}/marks` | session | records[{student_id, enrollment_id, status, notes?}] | `attendance.mark` | **Required** header | 200 · marked_count |
| CorrectAttendanceRecord | POST | `/attendance/sessions/{session}/students/{student}/correct` | session, student | academic_year_id, new_status, notes?, reason | `attendance.correct` | **Required** header | 200 · previous/new |
| CloseAttendanceSession | POST | `/attendance/sessions/{session}/close` | session | (empty / no status) | `attendance.session.close` | Optional header | 200 |
| GetAttendanceSession | GET | `/attendance/sessions/{session}` | session; `include_records?` | — | `attendance.view` | — | 200 DTO |
| ListAttendanceSessions | GET | `/attendance/sessions` | year**, section?, date_from?, date_to?, status?, page, per_page | — | `attendance.view` | — | 200 list page |
| GetSectionAttendance | GET | `/attendance/sections/{section}` | section; `date`, `academic_year_id` | — | `attendance.view` | — | 200 section aggregate |
| GetStudentAttendance | GET | `/attendance/students/{student}` | student; **`academic_year_id` required**; date_from?, date_to?, page, per_page | — | `attendance.view` | — | 200 page |
| GetDailySectionSummary | GET | `/attendance/sections/{section}/daily-summary` | section; `date` or range | — | `attendance.view` | — | 200 projection |

\*\* Prefer required `academic_year_id` on list for index friendliness (Design Lock); if optional, Application still school-filters.

**School_id:** never in body — always `SchoolContext::requireId()` (Enrollment/Grades pattern).

**No route** may call `AttendanceBatchService::recordSectionAttendance`.

---

## 4. Permission / Authorization Matrix

### Catalog evidence (LIVE)

| Permission code | In `Permission.php` / `config/security.php` | Roles (Option B) |
|-----------------|---------------------------------------------|------------------|
| `attendance.view` | YES | viewer, teacher, manager |
| `attendance.session.create` | YES | teacher, manager |
| `attendance.mark` | YES | teacher, manager |
| `attendance.correct` | YES | manager only |
| `attendance.session.close` | YES | teacher, manager |
| `attendance.administer` | ABSENT (rejected) | — |
| reopen / cancel | ABSENT (deferred) | — |

```text
Permission catalog incompleteness: NONE
R1.5 READINESS blocked on permissions: NO
```

### HTTP authz pattern (proposed)

| Layer | Role |
|-------|------|
| FormRequest / `$this->authorize` | Permission ∧ SchoolContext (via AttendancePolicy / Gate) **before** handler |
| Application handler | School match + lifecycle + ATT-D4 (already) |
| Policy | Existing `AttendancePolicy` methods: viewAny, view, createSession, mark, correct, closeSession |

**AttendancePolicy required?** YES for HTTP (peer Grades/Enrollment). Methods already implemented (R1.3). **Gate registration** still needed at implement time (see conditions).

Authorization must not replace Application school checks — defense in depth.

---

## 5. School Isolation Audit

| Concern | R1.4 behavior | HTTP risk | Mitigation (design) |
|---------|---------------|-----------|---------------------|
| Session school | section→class | Client sends wrong school_id | school_id **prohibited** in body; context only |
| Session access | Handler resolves + compares | IDOR by session id | Authorize + Get/Mark with `schoolId` from context; cross-school → not found / deny |
| Section / student paths | Query filters | Swap IDs across schools | Handler fail-closed (evidenced PG tests) |
| Summary | school_id on row | Direct summary IDOR | Pass context school into query |
| Query param school_id | — | Bypass attempt | Ignore/prohibit; never trust |
| Model binding | No Attendance Eloquent in HTTP yet | Premature bind without school check | Prefer int ids + Application queries (like careful Grades show) |

HTTP cannot safely skip Application school arguments. Controllers must pass `SchoolContext::requireId()` into every Command/Query.

---

## 6. Request Validation Contract

### CreateAttendanceSession

| Client writable | Server derived / forbidden |
|-----------------|----------------------------|
| academic_year_id, section_id, subject_id, session_date, teacher_id, period_id? | school_id, status, created_by, timestamps, session id |

FormRequest: shape + types. Domain: year bounds, section→school, period school.

### MarkSectionAttendance

| Client | Forbidden / server |
|--------|-------------------|
| records[] student_id, enrollment_id, status (nested), notes? | school_id, academic_year_id from session (or validated match), attendance_date, recorded_by, root status |
| Header X-Idempotency-Key **required** | |

FormRequest: non-empty array, max 500, distinct student_id, status in 1..3.  
Application: OPEN, ATT-D4, all-or-nothing (already).

### CorrectAttendanceRecord

| Client | Forbidden |
|--------|-----------|
| academic_year_id, new_status, notes?, reason (required min length peer grades ≥3) | school_id, enrollment_id, attendance_date, recorded_by, mutating identity |
| X-Idempotency-Key **required** | |

### CloseAttendanceSession

| Client | Forbidden |
|--------|-----------|
| (none / empty body) | status, school_id |
| X-Idempotency-Key optional | |

---

## 7. Idempotency HTTP Contract

| Evidence | Convention |
|----------|------------|
| EnrollmentController / GradeController | `X-Idempotency-Key` **header** |
| Replay | `meta.from_idempotency_cache`; Grades store uses **200** on replay, **201** on first create |

| Command | HTTP header |
|---------|-------------|
| Mark | **Required** (422 if missing) |
| Correct | **Required** |
| Create | Optional |
| Close | Optional |

Reuse `IdempotencyStore` — no new mechanism. No body hashing.

---

## 8. Query Exposure Contract

| Query | Expose via DTO `toArray()` | Required query params | Notes |
|-------|---------------------------|----------------------|-------|
| GetAttendanceSession | YES | — ; `include_records` bool | Cross-school → SessionNotFound |
| ListAttendanceSessions | YES | page, per_page (cap ≤100 peer); prefer academic_year_id | Paginated |
| GetSectionAttendance | YES | date, academic_year_id | |
| GetStudentAttendance | YES | **academic_year_id required** (422 if absent — mirror Grades show) | Must not become optional |
| GetDailySectionSummary | YES | date (or from/to) | Label as projection in docs; not SSOT |

No Eloquent models in JSON responses.

---

## 9. Error Mapping Matrix

Existing renderer: `SisDomainException` → JSON `message` + `error_code`; status by code substring (`not_found`→404, `conflict`→409, else 422).

| Condition | Typical Attendance code pattern | HTTP |
|-----------|--------------------------------|------|
| Validation (FormRequest) | Laravel validation | 422 |
| Unauthorized / forbidden | AuthorizationException | 403 |
| Unauthenticated | — | 401 |
| Session / record not found | `*_not_found` | 404 |
| Cross-school | often mapped to not_found or domain deny | 403 or 404 (match Grades IDOR style — prefer deny without leak) |
| Session not open / invalid enrollment / payload / ATT-D4 | attendance.* (422 default) | 422 |
| Close conflict | `*conflict*` | 409 |
| Idempotency replay | success Result | 200 |
| Missing SchoolContext | SchoolContextRequiredException | existing middleware behavior |

**Inconsistency note (MEDIUM):** Gate 07 table abbreviated permission names; implementers must use full `attendance.*` codes. Not a runtime conflict.

---

## 10. DTO / Result Boundary

| Safe to expose | Must not expose |
|----------------|-----------------|
| Application DTOs (`toArray`) | Eloquent models |
| Command Results (+ meta) | Domain snapshots / repo internals |
| Query list pages | Raw DB rows / outbox payloads |

Current Application DTOs include resolved `school_id` in session DTO (read model) — acceptable for response; must remain non-writable on input.

**Leakage today:** None on HTTP (no HTTP yet). CQRS boundary is clean.

---

## 11. Client-Controlled Field Audit

| Field | Client? |
|-------|---------|
| school_id | **NEVER** (guard + context) |
| session.status / root status | **NEVER** on write (close has no body status) |
| records[].status | YES (mark/correct nested) |
| attendance_date | **NEVER** (session_date) |
| recorded_by / created_by | **NEVER** (auth user) |
| summary counts | **NEVER** |
| outbox / idempotency metadata | **NEVER** as input |
| academic_year_id | YES where command/query requires; Create yes; Mark should not override session year |

---

## 12. Legacy Writer Boundary

```text
REQUIRED PATH:
HTTP → Authz → FormRequest → Command/Query → Application Handler → Repository/Domain

FORBIDDEN PATH:
HTTP → AttendanceBatchService::recordSectionAttendance
```

R1.4 already forbids CQRS calling the legacy public writer. R1.5 must not reintroduce it.

---

## 13. Security Regression Audit

| R1.3 / R1.4 guarantee | R1.5 preservation |
|-----------------------|-------------------|
| Option B roles/permissions | Consume only — no new perms |
| SchoolContext fail-closed | Middleware + requireId |
| Cross-school denial | Policy + Application |
| No administer / reopen / cancel | No routes |
| No alternate writer | Controller discipline |
| AttendanceAuthorizationTest | Must remain green |

---

## 14. Architecture Audit

Proposed R1.5 mirrors Enrollment/Grades:

```text
Thin AttendanceController
  authorize + SchoolContext
  map validated input → Command/Query
  return DTO/Result JSON + correlation meta
FormRequest: authorize + shape + prohibited fields
Business rules remain in Application/Domain
```

Compatible with Clean Architecture / feature contract (HTTP conditional).

---

## 15. R1.5 Required Test Matrix (do not implement now)

### Authorization
allowed teacher mark · manager correct · teacher cannot correct · viewer read-only · cross-school mark/get · missing permission · IDOR session id

### Validation
malformed body · missing fields · prohibited school_id/status · mark >500 · duplicate students · empty records · correct without reason · student attendance without academic_year_id → 422

### Idempotency
first mark 200 · replay same key · create 201 then 200 replay · missing key on mark → 422

### Queries
pagination · isolation · year required · include_records · summary projection

### Errors
SessionNotOpen → 422 · Close conflict → 409 · not found → 404

### Regression
R1.3 AttendanceAuthorizationTest · R1.4 unit/PG CQRS suites · architecture:validate --fitness

---

## 16. Repository Conflict Search

| Finding | Severity | Notes |
|---------|----------|-------|
| No Attendance routes/controllers today | — | Clean slate |
| Gate 07 permission shorthand vs catalog | **MEDIUM** | Docs only; use `attendance.*` |
| AttendancePolicy not Gate-registered | **MEDIUM** | Implement-time wiring; not catalog gap |
| `SecuritySensitiveFieldGuard` prohibits root `status` | **LOW** | Compatible with nested records.*.status |
| Docs mentioning `sessions.school_id` as future ATT-SEC-004 | **LOW** | Explicitly deferred; do not assume column |
| AttendanceBatchService still present | **LOW** | Must stay off HTTP path |
| Blueprint/docs may lag R1.4 | **LOW** | Prefer gates 02/07 + code |
| `attendance.administer` anywhere as approved | **NONE found** in security config | Rejected |

No **BLOCKER** conflicts for HTTP readiness.

---

## 17. Blockers / Risks

### Blockers

```text
NONE — permission catalog complete; CQRS exposable; peer HTTP patterns clear.
```

### Risks (non-blocking)

| ID | Risk | Severity |
|----|------|----------|
| R15-01 | Policy/Gate wiring approach choice | MEDIUM |
| R15-02 | Doc shorthand permission names | MEDIUM |
| R15-03 | IDOR if controller skips Application schoolId | HIGH if mishandled — mitigated by design |
| R15-04 | Accidental legacy batch call | MEDIUM |
| R15-05 | Making academic_year_id optional on student query | HIGH if mishandled — lock required |

---

## 18. Deferred Items

```text
DDL / migrations / RLS / FORCE RLS / session.school_id (ATT-SEC-*)
Reopen / Cancel HTTP
attendance.administer
UI / Inertia pages
Year partitions
VOID+INSERT history
Removing AttendanceBatchService class file
```

---

## 19. Implementation Authorization Gate

```text
STATUS: READY FOR HUMAN IMPLEMENTATION AUTHORIZATION

R1.5 HTTP/API implementation is NOT authorized by this audit.

Human approval is required before implementation.

DDL/RLS remains NOT AUTHORIZED.

Suggested future phrase (not granted now):
  APPROVED — IMPLEMENT ATTENDANCE R1.5 HTTP/API ONLY
  (controllers/routes/FormRequests/Gate wiring/tests per Gate 08)
  CQRS redesign / DDL / RLS / new permissions: NOT AUTHORIZED

STOP.
```

---

## 20. SIS Change Report

```text
Status: PASS (audit only)
Risk: N/A — no runtime change

Summary: R1.5 HTTP/API readiness audit — READY for human implementation authorization; no code implemented.

Application Code Modified: NO
Database Modified: NO
API Modified: NO
UI Modified: NO
Governance Modified: YES (this gate only)

Files Added:
  .cursor/database/phase-attendance/08-ATTENDANCE-HTTP-API-READINESS-GATE.md

Files Modified: NONE (implementation)

Human Approval Required: YES — before any R1.5 implementation
Final Gate Status: READY FOR HUMAN IMPLEMENTATION AUTHORIZATION
```

---

## Mutation check

```text
Intended artifact only:
.cursor/database/phase-attendance/08-ATTENDANCE-HTTP-API-READINESS-GATE.md

Verify after write:
git diff --name-only / git status for accidental app/ changes from this turn: NONE intended
```
