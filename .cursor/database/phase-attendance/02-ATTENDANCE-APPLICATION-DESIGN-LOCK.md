# ATTENDANCE APPLICATION DESIGN LOCK

**Date:** 2026-09-11  
**Mode:** AUDIT + DESIGN LOCK ONLY  
**Inputs:**  
`.cursor/database/phase-attendance/01-ATTENDANCE-APPLICATION-READINESS-GATE.md`  
Live attendance schema · Enrollment CQRS · Grades CQRS · `config/security.php` · `AttendanceBatchService` · idempotency/outbox contracts

```text
ATTENDANCE APPLICATION DESIGN LOCK

STATUS:
LOCKED WITH CONDITIONS

IMPLEMENTATION AUTHORIZATION:
NOT GRANTED

HUMAN APPROVAL:
REQUIRED
```

### Overall readiness

| Layer | State |
|-------|--------|
| Application design (ATT-D1..D6) | **LOCKED** (decisions below) |
| Role → permission mapping | **CONDITION** — no attendance roles in LIVE `config/security.php` |
| Schema / DDL | Unchanged; no R1 DDL |
| RLS FORCE / sessions RLS | Deferred ATT-SEC-* |
| Implementation | **NOT AUTHORIZED** |

### Unresolved conflicts (explicit)

| Conflict | Resolution in this lock |
|----------|-------------------------|
| Legacy upsert vs Grades VOID+INSERT | **R1 = controlled upsert + mandatory Correct audit** (schema-compatible). Future versioning = ATT-D2-FUTURE |
| `sessions` lacks `school_id` vs records have `school_id` | **R1 derives school via section→class**; fail-closed. Schema denormalize = ATT-SEC-004 |
| Readiness proposed CANCELLED session status vs “prefer OPEN→CLOSED only” | **Codes 1/2/3 reserved; R1 transitions = OPEN→CLOSED only**. Cancel/reopen = DEFERRED |
| Docs claim summary RLS; LIVE has none | Application SchoolContext required for R1; ATT-SEC-003 deferred |
| No `attendance.*` roles in repo | Permission **codes** locked; **role mapping = HUMAN DECISION** before implement |

### Design decisions (summary)

ATT-D1..D6 locked as specified in §§4–9.  
Commands/queries locked in §§11–12.  
Legacy batch service must not remain a competing write path (§18).

### Conditions before IMPLEMENT authorization

1. Human approve **role → permission mapping** (ATT-D3 condition).  
2. Explicit phrase authorizing CQRS implementation (separate from this document).  
3. Permission codes may be added to `config/security.php` / `Permission.php` **only under that implementation authorization** (not by this lock).  
4. RLS FORCE / session `school_id` DDL remain **not** part of R1.

### Explicit implementation blockers (until cleared)

| ID | Blocker |
|----|---------|
| IMP-ATT-ROLE | Role mapping not evidenced in repository — human must assign roles |
| IMP-ATT-AUTH | No implementation authorization phrase yet |
| IMP-ATT-PERM | Permission constants/config not present until authorized implement |

---

## 1. Evidence base (short)

| Source | Fact used |
|--------|-----------|
| LIVE / migration `100800` | sessions, partitioned records, summary; status SMALLINT default 1; unique `(session_id, student_id, academic_year_id)` |
| Dictionary | record status 1=present, 2=absent, 3=late |
| `EnrollmentStatus` | ACTIVE=1, CANCELLED=2; `isActive` = ACTIVE ∧ `effective_to` null |
| `enrollment.enrollments` | school_id, academic_year_id, section_id, effective_from, effective_to |
| `enrollment.sections` → `classes` | class carries `school_id` (sessions have no school_id) |
| `academic.academic_years` | start_date, end_date |
| `AttendanceBatchService` | upsert supplied rows only; refresh summary; chunk 500 |
| `config/security.php` | grades_* / enrollment_* roles; **no attendance roles** |
| Grades Correct | mandatory reason; outbox; idempotency via `IdempotencyStore` |
| Event catalog | No existing `Attendance*` Domain events — proposed names free |

---

## 2. ATT-D1 — Session lifecycle / status

### 2.1 Locked vocabulary

```text
1 = OPEN
2 = CLOSED
3 = CANCELLED   (reserved; R1 does not transition into/out of this state)
```

| Status | Meaning | Mark | Correct | Metadata change | Terminal? | Reports | Historical validity |
|--------|---------|------|---------|-----------------|-----------|---------|---------------------|
| **OPEN (1)** | Accepting routine marks | **Yes** | Yes (prefer Correct for audits; Mark may upsert while OPEN) | Limited (period/teacher) — avoid identity changes | No | Yes | Yes |
| **CLOSED (2)** | Routine marking frozen | **No** | **Yes** (`attendance.correct`) | No (except via deferred admin) | Soft terminal for mark | Yes | Yes |
| **CANCELLED (3)** | Session voided for ops | **No** | **No** (R1) | No | Terminal (when introduced) | Exclude from operational reports; keep row | Prior records remain; session not active |

Default on create: **OPEN (1)** — matches DB default.

### 2.2 Transition matrix

| From | To | Allowed | Actor/Permission | Conditions |
|------|-----|--------:|------------------|------------|
| OPEN | CLOSED | **Yes (R1)** | `attendance.session.close` | Session exists; school match; optimistic `WHERE status = OPEN` |
| OPEN | CANCELLED | **DEFERRED — NOT PART OF R1** | — | — |
| CLOSED | OPEN | **DEFERRED — NOT PART OF R1** | — | Reopen not authorized |
| CLOSED | CANCELLED | **DEFERRED — NOT PART OF R1** | — | — |
| CANCELLED | OPEN | **DEFERRED — NOT PART OF R1** | — | — |
| CANCELLED | CLOSED | **DEFERRED — NOT PART OF R1** | — | — |

**R1 safety posture:** normal lifecycle is **OPEN → CLOSED** only. No CancelAttendanceSession / ReopenAttendanceSession commands in R1.

### 2.3 Date / year consistency

| Rule | Lock |
|------|------|
| `session.academic_year_id` | Required; must equal command/context year |
| `session_date` vs year bounds | **APPLICATION RULE:** `academic_years.start_date ≤ session_date ≤ academic_years.end_date` or **reject** |
| Historical year create | **Allowed** if year exists and date in bounds (backfill) — same predicate |
| Period | Optional FK; if present, period.school_id must equal resolved session school |
| Mismatch behavior | Fail closed — do not create session |

### 2.4 Metadata mutability (R1)

While OPEN: teacher_id / period_id may be adjusted only if a future admin command is authorized — **R1 has no UpdateSessionMetadata command**. Create sets fields; Close freezes marking.

---

## 3. ATT-D2 — Correction / history model

### 3.1 R1 model (LOCKED)

```text
MARK:
    allowed while session OPEN
    upsert on natural key (session_id, student_id, academic_year_id)
    omitted students unchanged (see ATT-D6)

CORRECT:
    explicit CorrectAttendanceRecord command
    permission attendance.correct
    mandatory non-empty reason
    capture previous → new in outbox/audit
    may run while OPEN or CLOSED
    NOT while CANCELLED (when that status exists)

DELETE:
    NEVER (Application forbids; no reject-delete trigger yet — ATT-SEC deferred)
```

```text
R1 controlled-upsert model is an operational compatibility compromise.
It is NOT the final historical/versioning architecture.
```

```text
ATT-D2-FUTURE — Attendance immutable/versioned history
  (VOID+INSERT or record_versions — requires separate DDL Design Lock)
  MUST NOT be silently introduced during R1 implementation.
```

### 3.2 Correction audit capture (LOCKED)

Supported by schema + outbox pattern (no new columns in R1):

| Field | Source |
|-------|--------|
| record id | `attendance.records.id` |
| session id | record / command |
| student id | record |
| enrollment id | record (immutable) |
| academic year id | record |
| school id | record |
| previous status | read before upsert |
| new status | command |
| previous notes | read before upsert |
| new notes | command (nullable) |
| actor | `recorded_by` / command actor |
| reason | command (required) |
| timestamp | `occurredAt` on event |
| idempotency key | command key (also in idempotency store) |

### 3.3 Immutable vs mutable

**Immutable identity (never changed by Correct or Mark):**

```text
session_id, student_id, enrollment_id, academic_year_id, school_id, attendance_date
```

**Mutable operational fields:**

```text
status, notes, recorded_by, updated_at
```

`attendance_date` MUST equal `session.session_date` on insert; never rewritten on correct.

---

## 4. ATT-D3 — Permissions + role mapping

### 4.1 Permission catalog

| Permission | R1 | Purpose | Sensitive? | SoD concern |
|------------|---:|---------|-----------:|-------------|
| `attendance.view` | **REQUIRED** | Read sessions/records/summary | No | — |
| `attendance.session.create` | **REQUIRED** | Create OPEN session | Soft | — |
| `attendance.mark` | **REQUIRED** | Mark while OPEN | Soft | Distinct from correct |
| `attendance.correct` | **REQUIRED** | Correct with reason | **Yes** | Prefer not identical to mark-only roles |
| `attendance.session.close` | **REQUIRED** | OPEN→CLOSED | Soft | — |
| `attendance.administer` | **REJECTED** | Catch-all bypass | Yes | Avoid god-permission |
| `attendance.session.reopen` | **DEFERRED** | CLOSED→OPEN | Yes | — |
| `attendance.session.cancel` | **DEFERRED** | →CANCELLED | Yes | — |

SoD (R1): **mark** vs **correct** separated (mirror `grades.create` / `grades.correct`). Close is separate. No Graduation-style evaluator/approver SoD.

### 4.2 Role mapping

```text
BLOCKED — ROLE MAPPING EVIDENCE MISSING (as proven assignment)

Evidence: config/security.php defines student_*, enrollment_*, grades_* only.
No attendance_* roles exist. Do not invent roles as “proven.”
```

**Human must approve** a mapping before implementation. Non-binding analogy only:

| Suggested role (NOT LOCKED) | Suggested grants |
|-----------------------------|------------------|
| `attendance_teacher` (new) or extend `grades_teacher` | view, session.create, mark, session.close |
| `attendance_manager` (new) or extend `grades_manager` | + correct |
| `attendance_viewer` | view |

Creating new roles / editing `config/security.php` = **CONDITIONAL** under implementation authorization.

---

## 5. ATT-D4 — Enrollment validity at attendance date

### 5.1 Locked predicate (create/mark)

An enrollment `E` may be used to create an attendance record for session `S` iff **all** hold:

```text
E.student_id     = supplied student_id
E.school_id      = resolved_school_id(S)     -- via section→class.school_id
E.academic_year_id = S.academic_year_id
E.section_id     = S.section_id
E.effective_from <= S.session_date
AND (E.effective_to IS NULL OR S.session_date <= E.effective_to)
```

Status note: Cancelled enrollments **with** `effective_to` still cover dates in `[effective_from, effective_to]`. Dates **after** `effective_to` → **reject**. Pure “ACTIVE only” is **rejected** as too weak for backfill and too strong vs date window (ACTIVE with null `effective_to` is the open-ended case).

Invariant:

```text
Attendance record may be created only when the supplied enrollment
is valid for the student and covers session_date under the authoritative
Enrollment lifecycle date window and section/school/year identity rules above.
```

### 5.2 Answers

| Question | Lock |
|----------|------|
| 7.1 Cancelled before session_date | If `session_date > effective_to` → **reject**. If date still in window → **allow** (backfill) |
| 7.2 Future enrollment (`effective_from > session_date`) | **Reject** |
| 7.3 Ending (`effective_to < session_date`) | **Reject** |
| 7.4 Same school | **Must match** resolved session school and SchoolContext |
| 7.5 Same academic year | **Must match** |
| 7.6 Same section | **Must match** session.section_id |
| 7.7 Later cancel | Existing attendance rows **remain**; FK RESTRICT; no delete |

Enrollment domain remains authoritative for lifecycle; Attendance **consumes** the predicate.

---

## 6. ATT-D5 — Session tenant identity / school boundary

### 6.1 Authoritative tenant resolution (R1, no DDL)

```text
resolved_school_id(session) =
  enrollment.classes.school_id
  WHERE classes.id = enrollment.sections.class_id
    AND sections.id = session.section_id
```

Fail closed if section/class missing or `resolved_school_id ≠ SchoolContext.current_school_id`.

Records write `school_id = resolved_school_id` (denormalized; must match enrollment.school_id).

### 6.2 Authorization checks (all commands/queries)

| Operation | School rule |
|-----------|-------------|
| Create | section’s class.school_id = context |
| Lookup / list / get | Filter/join so only sessions whose resolved school = context |
| Mark / Correct / Close | Load session; resolve school; reject cross-school |
| Student/enrollment | Must satisfy ATT-D4 including school match |

Cross-school access: **deny** (no directorate bypass in R1 unless existing platform role already provides it — Attendance does not invent ministry policies).

### 6.3 Future schema

```text
session.school_id denormalization = DEFERRED TO SECURITY/SCHEMA HARDENING
ATT-SEC-004 — session tenant-bearing schema decision
NOT an R1 migration.
```

---

## 7. ATT-D6 — Partial vs complete section attendance

### 7.1 Choice (LOCKED)

**Option B — Partial marking**

```text
Only supplied students are modified.
Omitted student ≠ Absent
Omitted student = UNCHANGED
```

Evidence: `AttendanceBatchService` only upserts provided rows; Phase C peak-hour upsert pattern; safety against accidental mass absence.

Option A (full roster required) and Option C (caller chooses mode) are **rejected for R1**.

### 7.2 Deterministic rules

| Case | Behavior |
|------|----------|
| Eligible roster | Enrollments satisfying ATT-D4 for session (informational for UI; **not** auto-absent) |
| Duplicate student IDs in payload | **Reject** entire command |
| Unknown student / bad enrollment | **Reject** entire command (all-or-nothing transaction) |
| Wrong section enrollment | **Reject** (ATT-D4) |
| Inactive / out-of-window enrollment | **Reject** |
| Empty payload | **Reject** |
| Partial payload | Upsert those rows only |
| All-student payload | Allowed; still Option B semantics |
| Retry same idempotency key | Return cached result; no divergent mutation |
| Retry new key same marks | Upsert same values — idempotent at DB unique key |

---

## 8. Aggregate boundary

```text
AttendanceSession (root for lifecycle)
  - create, close
  - marking context (OPEN/CLOSED)

AttendanceRecord (fact under session)
  - mark / correct student participation
```

| Concept | Authoritative owner |
|---------|---------------------|
| Student identity | Student |
| Enrollment lifecycle / placement | Enrollment |
| Teacher assignment | Teachers (future); Attendance only stores teacher_id FK |
| Exam attendance | Exams |
| Grades / Graduation / Certificates | Those domains |
| Daily section summary | Attendance **projection** (not SSOT for history — records are) |

---

## 9. R1 command contracts

### 9.1 `CreateAttendanceSession`

| # | Lock |
|---|------|
| 1 Purpose | Create OPEN session for section/subject/date |
| 2 Actor | Authenticated user with create permission |
| 3 Permission | `attendance.session.create` |
| 4 Input | schoolId (context), academicYearId, sectionId, subjectId, sessionDate, teacherId, periodId?, idempotencyKey? |
| 5 Aggregate | AttendanceSession |
| 6 Preconditions | School match; date in year bounds; FKs exist; period school match if set |
| 7 Invariants | status=OPEN; no school_id column |
| 8 Tx | Single transaction insert |
| 9 Idempotency | Recommended |
| 10 Audit | Via outbox |
| 11 Outbox | `AttendanceSessionCreated` |
| 12 Failure | Unauthorized, FK, date/year, school mismatch |
| 13 Concurrency | App-level natural uniqueness optional; DB has no unique — duplicate sessions possible (ATT-009 deferred); R1 may reject duplicate (section, subject, date, period_id) via application check |
| 14 Postcondition | Session row OPEN |

### 9.2 `MarkSectionAttendance`

| # | Lock |
|---|------|
| 1 Purpose | Partial upsert of student statuses for a session |
| 2 Actor | Marker |
| 3 Permission | `attendance.mark` |
| 4 Input | sessionId, schoolId, academicYearId, records[{studentId, enrollmentId, status, notes?}], recordedBy, idempotencyKey |
| 5 Aggregate | Session + records |
| 6 Preconditions | Session OPEN; school/year match; each ATT-D4; status ∈ {1,2,3}; non-empty; no duplicate students |
| 7 Invariants | Identity immutable; attendance_date = session_date; Option B |
| 8 Tx | One transaction for classroom-sized payload: validate → upsert → refresh daily_section_summary → stage outbox |
| 9 Idempotency | **Required** |
| 10 Audit | Outbox |
| 11 Outbox | `SectionAttendanceMarked` |
| 12 Failure | Closed session, invalid enrollment, bad status, empty/dup, cross-school |
| 13 Concurrency | Re-read session status inside tx; if not OPEN → fail. Race with Close: loser fails cleanly |
| 14 Postcondition | Supplied rows current; summary refreshed for (section_id, date) |

**Max sync payload (R1):** section-sized, **≤ 200** students recommended; **hard refuse > 500** in one sync command (aligns chunk size). School-wide mark **forbidden** in HTTP.

### 9.3 `CorrectAttendanceRecord`

| # | Lock |
|---|------|
| 1 Purpose | Audited status/notes change |
| 2 Actor | Corrector |
| 3 Permission | `attendance.correct` |
| 4 Input | sessionId, studentId, academicYearId, schoolId, newStatus, newNotes?, reason, recordedBy, idempotencyKey |
| 5 Aggregate | AttendanceRecord |
| 6 Preconditions | Record exists; session not CANCELLED; reason non-empty; school match; status valid |
| 7 Invariants | Identity fields unchanged |
| 8 Tx | validate → read previous → upsert → summary refresh → outbox (+ idempotency per Grades ordering: tx then store key, or key inside UoW if pattern requires — **follow existing Grades/Enrollment UnitOfWork + IdempotencyStore ordering**) |
| 9 Idempotency | **Required** |
| 10 Audit | Mandatory previous/new in event |
| 11 Outbox | `AttendanceCorrected` |
| 12 Failure | No reason; missing record; cross-school; identity change attempt |
| 13 Concurrency | Last write wins on status; both corrections if different keys leave final = last commit; each audited |
| 14 Postcondition | Record updated; summary consistent |

### 9.4 `CloseAttendanceSession`

| # | Lock |
|---|------|
| 1 Purpose | OPEN→CLOSED |
| 2 Actor | Closer |
| 3 Permission | `attendance.session.close` |
| 4 Input | sessionId, schoolId, idempotencyKey? |
| 5 Aggregate | AttendanceSession |
| 6 Preconditions | status OPEN; school match |
| 7 Invariants | Only status transition |
| 8 Tx | `UPDATE ... SET status=CLOSED WHERE id=? AND status=OPEN` — 0 rows ⇒ conflict/already closed |
| 9 Idempotency | Recommended (replay ⇒ success if already CLOSED by same intent / cached) |
| 10 Audit | Outbox |
| 11 Outbox | `AttendanceSessionClosed` |
| 12 Failure | Not found; wrong school; not OPEN (unless idempotent replay) |
| 13 Concurrency | Optimistic status predicate prevents silent mark-after-close |
| 14 Postcondition | status CLOSED; further Mark rejected |

---

## 10. R1 query contracts

All queries: **SchoolContext required**; resolve/filter by ATT-D5; permission `attendance.view`. Cross-school → empty/not found (fail closed, no leak).

| Query | Scope | Filters | Pagination | Ordering | Notes |
|-------|-------|---------|------------|----------|-------|
| `GetAttendanceSession` | school | sessionId | — | — | Optional include records flag |
| `ListAttendanceSessions` | school | year, section?, dateFrom/To, status? | **Required** (cursor/page) | session_date DESC, id DESC | |
| `GetSectionAttendance` | school | sectionId, date, year | — | student_id ASC | Session(s)+records for date |
| `GetStudentAttendance` | school | studentId, year, dateFrom/To? | **Required** | attendance_date DESC | Partition-friendly year filter **required** |
| `GetDailySectionSummary` | school | sectionId, date / range | range paginated | date ASC | **Projection** — not historical SSOT; authoritative facts = records |

Summary is **derived/refreshed**, not independently authoritative for corrections audit.

---

## 11. Idempotency

Reuse `audit.idempotency_keys` + `IdempotencyStore` (`key` + `command_name`). **No second system.**

| Command | Required? | Scope |
|---------|-----------|-------|
| CreateAttendanceSession | Recommended | key + `CreateAttendanceSession` |
| MarkSectionAttendance | **Required** | key + `MarkSectionAttendance` |
| CorrectAttendanceRecord | **Required** | key + `CorrectAttendanceRecord` |
| CloseAttendanceSession | Recommended | key + `CloseAttendanceSession` |

| Topic | Lock |
|-------|------|
| Actor/school binding | Command carries schoolId; handler validates context — store payload should include school_id + session_id for replay safety |
| Request fingerprint | Existing store has **no** body hash (Grades same). R1 **follows existing**. Conflicting reuse of same key+command with different body → returns **first** cached result (known limitation; do not invent new store) |
| Replay | Return cached success result; no second mutation |
| Ordering | Match Grades: business tx (+ outbox) then `idempotency->store` after success |

Natural unique index prevents duplicate student/session rows under retry without key.

---

## 12. Outbox / audit events

Implement `DomainEvent` in `App\Domain\Attendance\Events\` when authorized. **No name conflicts** with existing catalog.

| Event | Aggregate id | Min payload | Sensitive | Before/after | Consumers |
|-------|--------------|-------------|-----------|--------------|-----------|
| `AttendanceSessionCreated` | session_id | school_id, academic_year_id, section_id, subject_id, session_date, teacher_id, actor, occurred_at | low | — | audit listener |
| `SectionAttendanceMarked` | session_id | school_id, academic_year_id, count, student_ids or hash, actor, occurred_at | student ids | — | audit; summary already sync |
| `AttendanceCorrected` | record_id | school_id, academic_year_id, session_id, student_id, enrollment_id, previous_status, new_status, previous_notes, new_notes, reason, actor, idempotency_key?, occurred_at | reason/notes | **required** | audit |
| `AttendanceSessionClosed` | session_id | school_id, academic_year_id, actor, occurred_at | low | — | audit |

```text
CONFLICT — MUST RESOLVE BEFORE IMPLEMENTATION: NONE known
```

---

## 13. Transaction boundaries

| Op | Boundary |
|----|----------|
| Create | 1 tx |
| Mark (≤500) | 1 tx: validate + upsert + summary refresh + outbox stage |
| Correct | 1 tx: validate + mutate + summary + outbox |
| Close | 1 tx |
| Large import | **OUT OF R1** — future: queue → chunk → tx per chunk |

No HTTP row-by-row commits.

---

## 14. Concurrency

| Scenario | Expected |
|----------|----------|
| Two teachers mark same session | Both may succeed (partial upserts); last write per student wins |
| Duplicate submission | Idempotency key → same result; else upsert same values |
| Correction vs correction | Last commit wins; each audited if distinct keys |
| Mark vs Close | Close uses `status=OPEN` predicate; Mark re-checks OPEN inside tx — one fails cleanly |
| Retry after timeout | Idempotency or unique upsert → safe |
| Correct vs Close | Correct allowed after CLOSED; Mark not |

```text
CLOSE must not race into a state where attendance can be
silently modified after closure via Mark.
Correct remains the only post-close mutator.
```

Application optimistic status checks required; DB CHECK on status **deferred**.

---

## 15. Failure semantics

Use existing Result / domain exception patterns (Enrollment/Grades style) — **no new error framework**.

| Condition | Behavior |
|-----------|----------|
| Unauthorized school / missing SchoolContext | Deny / fail closed |
| Missing permission | Deny |
| Nonexistent session | Not found |
| Wrong academic year | Reject |
| Session CLOSED on Mark | Reject |
| Session CANCELLED | Reject mark/correct (when status used) |
| Invalid enrollment / section / student | Reject |
| Duplicate student in payload | Reject |
| Duplicate idempotent request | Success from cache |
| Invalid status code | Reject |
| Correct without reason | Reject |
| Identity field change | Reject |
| Idempotency conflict (same key, first wins) | Return first payload |

---

## 16. Legacy `AttendanceBatchService`

| Topic | Lock |
|-------|------|
| Authoritative R1 write path | **`Application/Attendance` CQRS only** |
| Legacy status | `@architecture-legacy-allowed` — **temporary**; must migrate into Infrastructure called **only** from handlers |
| Incompatible | Silent upsert without Correct audit; `today()` date vs session_date; no OPEN check; no enrollment window check |
| Coexistence | **Prohibited** as alternate public writer after R1 ships |
| Migration boundary | Handlers own validation; batch upsert+summary refresh may live as private infra helper |

---

## 17. Security hardening boundary

### R1 application security

SchoolContext + ATT-D5 resolution + ATT-D3 permissions + enrollment predicate.

### Deferred (no mutation now)

```text
ATT-SEC-001 — FORCE RLS on attendance.records
ATT-SEC-002 — RLS on attendance.sessions
ATT-SEC-003 — RLS on attendance.daily_section_summary
ATT-SEC-004 — session tenant-bearing schema decision (school_id)
```

---

## 18. Test design lock (Given / When / Then — do not implement)

### Unit

- **Given** OPEN session; **When** Mark; **Then** upsert succeeds.  
- **Given** CLOSED; **When** Mark; **Then** reject.  
- **Given** CLOSED; **When** Correct with reason; **Then** status changes + event has previous/new.  
- **Given** Correct without reason; **When** execute; **Then** reject.  
- **Given** enrollment effective_from > date; **When** Mark; **Then** reject.  
- **Given** partial payload; **When** Mark; **Then** omitted students unchanged.  
- **Given** duplicate student ids; **When** Mark; **Then** reject.  
- **Given** idempotency key replay; **When** Mark twice; **Then** identical result, one logical mutation.

### Feature

Create → Mark → GetSectionAttendance → Correct → Close → Mark fails → queries school-scoped.

### Security

Cross-school get/mark deny; missing SchoolContext deny; mark without correct cannot Correct; correct permission required.

### Database

Unique (session, student, year); FK restrict; inserts hit DEFAULT partition; year filter used in student history query.

### Concurrency

Mark vs Close; double Mark; concurrent Correct.

### Regression

Legacy helper behavior only via handler path; no second public API.

---

## 19. Scale / performance design

| Item | Lock |
|------|------|
| Expected section size | ~30–50 (up to ~200) |
| Max sync Mark | **500** hard cap |
| Forbidden | 45,000-student single HTTP/tx |
| Summary | Refresh in same tx as mark/correct for that section/date |
| MV `reports.mv_daily_attendance` | Async/scheduled — **not** in command tx |
| Queries | Year required for student history; list pagination mandatory |

---

## 20. Explicit non-goals

```text
Results/Transcript · Promotion/Transfer · Graduation · Certificates · Grades changes
Exam attendance · Teacher Assignment CQRS · Exam scheduling
Absence reason taxonomy · ATT-D2-FUTURE DDL · FORCE RLS · session school_id migration
Year-specific partitions · School-wide import engine · AI/analytics · finance/comms
Reopen/Cancel session commands · HTTP routes (unless separately authorized after CQRS)
attendance.administer permission
```

---

## 21. Implementation scope lock

### IN SCOPE (when separately authorized)

```text
app/Application/Attendance/**  — commands/queries/DTOs/results/contracts
app/Domain/Attendance/**       — events, exceptions, status VOs, guards
app/Infrastructure/Persistence/Attendance/** — read/write repos
Migrate batch upsert+summary into infra used only by handlers
Unit + feature + security tests per §18
Wire IdempotencyStore + OutboxRepository + UnitOfWork
```

### CONDITIONAL

```text
Permission constants + config/security.php role grants (after human role mapping)
HTTP/Inertia endpoints
Queue workers for oversized batches
```

### DEFERRED

```text
Reopen/Cancel · ATT-D2-FUTURE · ATT-SEC-001..004 · year partitions
Natural UNIQUE on sessions · status CHECK constraints · reject-delete triggers
```

### OUT OF SCOPE

All §20 non-goals; Graduation/Certificates/Enrollment redesign.

---

## 22. Decision register

| ID | Decision | Final State | R1 | Future |
|----|----------|-------------|---:|-------:|
| ATT-D1 | Session lifecycle | **LOCKED** OPEN=1 CLOSED=2 CANCELLED=3 reserved; only OPEN→CLOSED | Yes | Reopen/Cancel |
| ATT-D2 | Correction/history | **LOCKED** controlled upsert + audited Correct | Yes | ATT-D2-FUTURE versioning |
| ATT-D3 | Permissions | **LOCKED** codes; **role map = HUMAN CONDITION** | Codes yes | Roles/grants |
| ATT-D4 | Enrollment validity | **LOCKED** date-window + section/school/year | Yes | — |
| ATT-D5 | Tenant identity | **LOCKED** resolve via section→class; no DDL | Yes | ATT-SEC-004 |
| ATT-D6 | Partial/full | **LOCKED** Option B partial; omit ≠ absent | Yes | Optional FULL mode |

No P0 `TBD` remains inside ATT-D1/D2/D4/D5/D6. ATT-D3 role assignment is the open **condition**, not a guessed lock.

---

## 23. Risk register

| Risk | Impact | Likelihood | Mitigation | Owner | Phase | Pri |
|------|--------|------------|------------|-------|-------|-----|
| Lifecycle ambiguity | Illegal marks after close | Med | ATT-D1 + optimistic close | Attendance | R1 | P0 |
| Invisible overwrite | Audit gap | High without Correct | ATT-D2 audited Correct | Attendance | R1 | P0 |
| Authorization gaps | Unauthorized writes | High until roles | ATT-D3 + human mapping | Security | Cond | P0 |
| Tenant leakage via sessions | Cross-school read | Med | ATT-D5 app checks | Attendance | R1 | P1 |
| Enrollment/date mismatch | Wrong student marked | Med | ATT-D4 | Attendance | R1 | P0 |
| Accidental bulk absence | Data corruption | High if Option A | ATT-D6 Option B | Attendance | R1 | P0 |
| Concurrent close/mark | Mark after close | Med | Optimistic status | Attendance | R1 | P1 |
| Duplicate marking | Noise | High | Unique + idempotency | Attendance | R1 | P1 |
| Legacy competing writer | Divergent facts | High if both live | Single CQRS path | Attendance | R1 | P0 |
| RLS gaps | Bypass | Med | ATT-SEC-* | Security | R3 | P1 |
| DEFAULT partition only | Scale prune weak | Med long-term | Year partitions | DB | Scale | P2 |
| Summary drift | Dashboard wrong | Med | Refresh in tx | Attendance | R1 | P1 |

---

## 24. Final gate scores

```text
Design completeness:     92/100
Security completeness:   70/100   (app rules locked; DB FORCE deferred; roles unmapped)
Domain completeness:     90/100
Historical integrity:    72/100   (audited upsert; not versioned)
CQRS readiness:          88/100
Testability:             85/100
Scale readiness:         75/100   (section sync OK; import/partitions deferred)
Overall:                 82/100
```

```text
ATTENDANCE APPLICATION DESIGN LOCK — FINAL GATE

PASS WITH CONDITIONS

Conditions:
  1. Human approves role → permission mapping (ATT-D3).
  2. Separate explicit IMPLEMENTATION AUTHORIZATION for CQRS.
  3. Permission/config changes only under that authorization.
  4. No DDL / RLS mutation in R1.
  5. ATT-D2-FUTURE and ATT-SEC-* remain deferred.

NO IMPLEMENTATION AUTHORIZATION IS GRANTED BY THIS DOCUMENT.
```

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
DDL AUTHORIZATION: NOT GRANTED
RLS MUTATION AUTHORIZATION: NOT GRANTED
CQRS IMPLEMENTATION: NOT GRANTED
MIGRATION AUTHORIZATION: NOT GRANTED
PERMISSION/CONFIG MUTATION: NOT GRANTED

Recommended Next Action:
  HUMAN APPROVE ATT-D3 ROLE→PERMISSION MAPPING
  (then, if desired, issue a separate IMPLEMENT AUTHORIZATION for Attendance Application CQRS R1)

Human Approval Required:
  YES
```

---

## Mutation check

```text
Files modified: ONLY
.cursor/database/phase-attendance/02-ATTENDANCE-APPLICATION-DESIGN-LOCK.md

PHP / DDL / RLS / tests / config / services: NONE
```

**STOP.** Await human approval before any implementation phase.
