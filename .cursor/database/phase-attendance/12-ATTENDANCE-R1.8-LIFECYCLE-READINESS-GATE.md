# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.8 — LIFECYCLE & OPERATIONAL INTEGRITY READINESS GATE

**Document Type:** AUDIT + DESIGN READINESS ONLY  
**Date:** 2026-09-11  
**Implementation Authorization:** **NOT GRANTED**

**Upstream (authoritative):** R1.3 Security · R1.4 CQRS · R1.5 HTTP · R1.6 DDL readiness · **R1.7 DDL+RLS Strategy A PASS**

```text
ATT-SEC-004 Strategy A LOCKED — sessions.school_id = historical ownership snapshot
FORCE RLS on sessions / records / daily_section_summary — DO NOT REOPEN
```

```text
NO MIGRATIONS · NO DDL · NO CODE CHANGES · NO API/UI/PERMISSION CHANGES
```

---

## 1. Executive Verdict

```text
STATUS: READY WITH CONDITIONS

Core Attendance lifecycle (OPEN → CLOSED) is already implemented and
aligned with Design Lock ATT-D1. Correction-after-close is intentional
and coded. CANCELLED is reserved in Domain but has no transition path.

A separate R1.8 *implementation* phase is OPTIONAL and must be scoped
only after human decisions on Reopen / Cancel / Status CHECK /
Natural UNIQUE / legacy-writer retirement.

R1.8 implementation is NOT authorized by this audit.
```

**Score: 78 / 100**

| Dimension | /10 | Notes |
|-----------|----:|-------|
| Lifecycle clarity (current) | 9 | ATT-D1 + handlers agree on OPEN/CLOSED |
| Transition enforcement | 8 | Mark/Close strong; Correct allows CLOSED |
| Status integrity (DB) | 5 | No CHECK; invalid SMALLINT possible |
| Duplicate-session integrity | 5 | Soft find exists unused; no UNIQUE |
| Reopen/Cancel readiness | 6 | Design reserved; commands absent |
| Legacy writer risk | 5 | Dual path remains |
| RLS / Strategy A fit | 9 | Lifecycle UPDATEs compatible |
| Audit/outbox coverage | 8 | Create/Mark/Correct/Close covered |
| Partition independence | 9 | No year-partition blocker |
| Documentation vs code | 8 | Minor: duplicate guard unused |

Score does **not** authorize implementation.

---

## 2. Scope Lock (this phase)

| Done | Forbidden (verified intent) |
|------|------------------------------|
| Evidence from gates + code | Migrations / DDL / RLS |
| Readiness decisions | Reopen/Cancel implementation |
| Proposed matrices only | Permission catalog changes |
| Governance note on migrate contamination | Legacy writer retirement |

---

## 3. Current Lifecycle (LIVE behavior)

### Status vocabulary

| Value | Name | Domain VO | DB CHECK | Transitioned by R1? |
|------:|------|-----------|----------|---------------------|
| 1 | OPEN | `SessionStatus::Open` | **None** | Yes (create default) |
| 2 | CLOSED | `SessionStatus::Closed` | **None** | Yes (`CloseAttendanceSession`) |
| 3 | CANCELLED | `SessionStatus::Cancelled` | **None** | **No** (reserved) |

Evidence: `app/Domain/Attendance/ValueObjects/SessionStatus.php`; Design Lock ATT-D1.

### Who can do what (R1.3 + R1.5)

| Action | Permission | Roles (Option B) | Handler gate |
|--------|------------|------------------|--------------|
| Create session | `attendance.session.create` | teacher, manager | Create → OPEN |
| Mark | `attendance.mark` | teacher, manager | **OPEN only** |
| Correct | `attendance.correct` | **manager only** | Not CANCELLED; **CLOSED allowed** |
| Close | `attendance.session.close` | teacher, manager | Optimistic OPEN→CLOSED |
| View | `attendance.view` | viewer+ | Queries |

### Closed-session mutability (current)

| Operation | CLOSED allowed? | Evidence |
|-----------|-----------------|----------|
| Mark | **No** → `SessionNotOpenException` | `MarkSectionAttendanceHandler` |
| Correct | **Yes** | `CorrectAttendanceRecordHandler` (blocks CANCELLED only) |
| Close again | **No** → `SessionCloseConflictException` (409) | optimistic UPDATE |
| Reopen | **Not implemented** | — |
| Cancel | **Not implemented** | — |
| Hard DELETE records | Denied under R1.7 RLS (no DELETE policy) | Gate 11 |

**Closed session is not fully immutable:** routine marking is frozen; **manager Correct remains allowed** (ATT-D1 / ATT-D2 locked).

### `SessionStatus` helpers vs handlers

| Helper | Intent | Used by handlers? |
|--------|--------|-------------------|
| `allowsMark()` | OPEN only | Mark uses raw `=== Open` |
| `allowsCorrect()` | not CANCELLED | Correct uses CANCELLED check |

Aligned with Design Lock.

---

## 4. Proposed Lifecycle (NOT implemented)

Preserve ATT-D1 unless human revises:

```text
Create → OPEN
OPEN → CLOSED     (authorized today)
OPEN → CANCELLED  (proposed future)
CLOSED → OPEN     (proposed future reopen — optional)
CLOSED → CANCELLED(proposed future — optional)
CANCELLED → *     (terminal; no reopen without explicit new policy)
```

**Unknowns requiring human input:** whether Cancel applies only from OPEN, whether Cancelled sessions keep records visible in student history, whether Reopen is ever needed operationally.

---

## 5. State Transition Matrix (proposed)

| From | Action | To | Actor/Permission | Conditions | Audit / Outbox |
|------|--------|-----|------------------|------------|----------------|
| — | CreateAttendanceSession | OPEN | `attendance.session.create` | school stamp Strategy A; year contains date | `AttendanceSessionCreated` |
| OPEN | MarkSectionAttendance | OPEN | `attendance.mark` | ATT-D4; ≤500; OPEN re-check in tx | `SectionAttendanceMarked` |
| OPEN / CLOSED | CorrectAttendanceRecord | same | `attendance.correct` | reason; not CANCELLED; record exists | `AttendanceCorrected` |
| OPEN | CloseAttendanceSession | CLOSED | `attendance.session.close` | `UPDATE … WHERE status=OPEN` | `AttendanceSessionClosed` |
| OPEN | **CancelAttendanceSession** *(proposed)* | CANCELLED | **PROPOSED** `attendance.session.cancel` | preserve rows; no hard delete | proposed cancel event |
| CLOSED | **ReopenAttendanceSession** *(proposed)* | OPEN | **PROPOSED** `attendance.session.reopen` | SoD; audit; mark re-enabled | proposed reopen event |
| CANCELLED | Mark / Correct / Close | — | — | **Reject** | — |

Only first four rows are **implemented**.

---

## 6. Reopen Decision

```text
REOPEN: DEFER (NOT JUSTIFIED as R1.8 must-have)
```

| Lens | Finding |
|------|---------|
| Technical possibility | Easy `CLOSED→OPEN` UPDATE under Strategy A RLS |
| Business requirement | **Not evidenced** in ops docs; Design Lock deferred |
| Records / summary | Unchanged on reopen; Mark resumes |
| Outbox / audit | Would need dedicated event |
| Idempotency | Optional/required TBD |
| Historical evidence | Reopen does not destroy facts; weakens “closed = frozen marks” signal |
| Risk | Bypass of close discipline; needs elevated permission |

**Recommendation:** Do not implement Reopen until a named operational use case (e.g. teacher closed early same day) is human-approved.

---

## 7. Cancel Decision

```text
CANCEL: DEFER (design-ready; not required for current HTTP surface)
```

| Topic | Recommendation (proposal only) |
|-------|--------------------------------|
| Semantics | Soft void session ops — **not** delete |
| Preserve session row | **Yes** |
| Preserve records | **Yes** (historical evidence) |
| Correct after cancel | **No** (already coded if status=3) |
| Mark after cancel | **No** |
| Outbox | Required when introduced |
| Irreversible | Prefer terminal CANCELLED |
| vs Correct | Cancel voids session context; Correct fixes student facts |
| vs Reopen | Opposite intents |

**No hard delete** remains absolute.

---

## 8. Status CHECK Decision

```text
STATUS CHECK: RECOMMENDED (not R1.8-blocking)
```

| Aspect | Today |
|--------|-------|
| App validation | Domain VO + Mark/Correct status checks for **records**; session status compared in handlers |
| DB validation | **None** — any SMALLINT writable |
| Invalid values possible? | **Yes** via raw SQL / legacy / bug |
| Transition matrix in DB | **No** (app-only) |

`CHECK (status IN (1,2,3))` is justified as defense-in-depth; does **not** enforce transition legality. Classify **RECOMMENDED** for a future integrity micro-phase; **DEFER** if R1.8 is Reopen/Cancel-only.

---

## 9. Natural UNIQUE Decision

```text
NATURAL UNIQUE: RECOMMENDED
```

| Evidence | Detail |
|----------|--------|
| Repo method | `findDuplicateOpenSession(section, subject, date, period)` exists |
| Create handler | **Does not call it** — duplicates possible |
| DB UNIQUE | **Absent** |
| Harm | Parallel OPEN sessions → double marking surfaces, summary confusion |

**Candidate invariant (proposed, not implemented):**

```text
At most one OPEN session per
  (school_id, section_id, subject_id, session_date, period_id)
with NULLS NOT DISTINCT on period_id (PG15+)
```

Alternatively unique among **all** statuses for that natural key — **human must choose** (closed+recreate same day?).

Classification: **RECOMMENDED** for integrity; not a security P0 while HTTP Create lacks concurrency control.

---

## 10. Legacy Writer Decision

```text
LEGACY WRITER: ACCEPT TEMPORARILY → RETIRE LATER
(NOT an R1.8 lifecycle blocker)
```

`AttendanceBatchService::recordSectionAttendance`:

| Check | Result |
|-------|--------|
| Reachable | Yes (class remains; not called by R1.5 HTTP) |
| Bypasses CQRS | **Yes** if invoked |
| Session date | Uses **`today()`** — conflicts with CQRS `session.session_date` |
| OPEN/CLOSED | **No check** |
| School ownership | Accepts caller `schoolId`; no Strategy A stamp on sessions |
| Summary refresh | Yes, but date may desync |
| R1.8 blocker? | **No** for Reopen/Cancel design |
| Future | Compatibility / retirement under separate authorization |

---

## 11. Year Partition Decision

```text
YEAR PARTITIONS: NOT REQUIRED for R1.8 / FUTURE scalability
```

Lifecycle UPDATEs on `sessions.status` and record corrections do not require new LIST partitions. Preserve `records` + `records_default`. Classify **R1.9+ scalability concern**.

---

## 12. Academic Year Audit

| Rule | Status |
|------|--------|
| Session `academic_year_id` + date in year bounds | Enforced on Create |
| Mark/Correct year match session | Enforced |
| ATT-D4 enrollment window | Mark only |
| Cross-year reopen/cancel | N/A until those commands exist — **must keep year immutable on session** |
| Strategy A school_id | Independent of year; do not rewrite on lifecycle transitions |

**Invariant to preserve:** lifecycle transitions must **not** change `sessions.school_id` or `academic_year_id`.

---

## 13. RLS / Strategy A Compatibility

```text
Strategy A REMAINS AUTHORITATIVE — CONFIRMED COMPATIBLE
```

| Proposed op | RLS need |
|-------------|----------|
| Close / Reopen / Cancel | UPDATE `sessions` same `school_id` + GUC |
| Correct after close | UPDATE `records` + summary upsert |
| Cross-school | Denied by FORCE + WITH CHECK |
| DELETE | Still forbidden under R1.7 policy shape |

Do **not** switch session RLS back to section→class joins.

---

## 14. Audit / Outbox / Idempotency (current + proposed)

| Operation | Outbox today | Idempotency | Notes |
|-----------|--------------|-------------|-------|
| Create | `AttendanceSessionCreated` | Optional | — |
| Mark | `SectionAttendanceMarked` | **Required** | — |
| Correct | `AttendanceCorrected` (prev/new/reason) | **Required** | After CLOSE OK |
| Close | `AttendanceSessionClosed` | Optional | — |
| Reopen *(proposed)* | New domain event required | Optional→Required TBD | — |
| Cancel *(proposed)* | New domain event required | Optional→Required TBD | Preserve evidence |

Event **names** above are existing class names / proposals — not a new catalog invent.

---

## 15. Permission / SoD Impact

Canonical catalog (unchanged):

```text
attendance.view | session.create | mark | correct | session.close
```

| Future need | Status |
|-------------|--------|
| `attendance.session.reopen` | **PROPOSED — NOT AUTHORIZED** |
| `attendance.session.cancel` | **PROPOSED — NOT AUTHORIZED** |
| `attendance.administer` | **Rejected historically — do not revive** |

SoD: Mark ≠ Correct remains; Reopen/Cancel should not share Mark permission if introduced.

---

## 16. CQRS Impact

| Command | Status |
|---------|--------|
| Create / Mark / Correct / Close | **Sufficient for current ops** |
| ReopenAttendanceSession | **Proposed only** |
| CancelAttendanceSession | **Proposed only** |

**Current command violations of ATT-D1?** None material. Gap: Create does not invoke duplicate-open guard (integrity, not transition illegality).

---

## 17. HTTP/API Impact (proposed only)

| Need | Today | Future if Cancel/Reopen authorized |
|------|-------|-------------------------------------|
| Close route | Exists | — |
| Reopen route | Absent | e.g. `POST .../sessions/{id}/reopen` |
| Cancel route | Absent | e.g. `POST .../sessions/{id}/cancel` |
| Correct on CLOSED | Works | Document in API notes |
| Status codes | 409 close conflict; 422 not open | Reuse patterns |

No route changes in this audit.

---

## 18. UI Impact (dependencies only)

Backend must expose session `status` (already in DTOs) so UI can:

- Disable Mark when not OPEN  
- Allow Correct for managers when OPEN/CLOSED  
- Hide Cancel/Reopen until authorized  
- Show audit/reason on corrections  

No UI design in this phase.

---

## 19. Historical Evidence / Immutability

| Risk | Current protection |
|------|--------------------|
| Hard delete records | R1.7 no DELETE policy + Constitution |
| Overwrite without audit | Mark while OPEN can upsert; Correct requires reason + outbox |
| Retroactive school ownership change | Strategy A stamp; lifecycle must not rewrite `school_id` |
| Silent summary wrong date | CQRS uses `session_date`; legacy `today()` debt |
| Destroy on cancel | Cancel must preserve rows if introduced |

---

## 20. Migration Contamination Governance (debt)

R1.7 noted `php artisan migrate` applied **pre-existing pending Certificates 4.1** migrations.

| Classification | Governance debt — not Attendance lifecycle work |
|----------------|--------------------------------------------------|
| Action now | **None** (no Certificates change/rollback) |
| Future improvement | Phase gates should list **authorized migration filenames** and refuse “migrate all pending” without explicit allow-list |

---

## 21. Required Future Implementation Scope (if R1.8 implement authorized later)

### MUST (only if human scopes integrity hardening)

```text
(none mandatory for production ops today)
```

### SHOULD (integrity micro-scope candidates)

```text
- Wire Create → findDuplicateOpenSession OR DB UNIQUE (human picks key)
- CHECK (status IN (1,2,3))
- Document Correct-after-close as supported API behavior
```

### DEFER

```text
- ReopenAttendanceSession + permission
- CancelAttendanceSession + permission
- Year partitions
- ATT-D2-FUTURE versioned record history
```

### OUT OF SCOPE

```text
- Strategy A redesign
- HTTP redesign unrelated to lifecycle
- UI implementation
- Legacy writer retirement (separate auth)
- Certificates / Graduation / Enrollment schema
```

---

## 22. Blockers

```text
P0 blockers preventing continued Attendance ops: NONE

P1 blockers before a Reopen/Cancel implementation: human decisions (§23)
P1 blockers before integrity UNIQUE/CHECK: none (optional)
```

---

## 23. Human Decisions Required

1. **Is an R1.8 implementation phase needed now?** Integrity-only vs Cancel vs Reopen vs combined.  
2. **Reopen:** ship / defer permanently / never.  
3. **Cancel:** ship / defer; from OPEN only vs also CLOSED.  
4. **Duplicate policy:** reject second OPEN vs allow multiple; exact UNIQUE key; NULL period handling.  
5. **Status CHECK:** include in next DDL micro-gate or defer.  
6. **New permissions** for reopen/cancel (names + role grants) — catalog change needs separate approval.  
7. **Legacy writer:** schedule retirement authorization or accept until named phase.  
8. **Migrate allow-list governance** (Certificates contamination) — process, not Attendance DDL.

---

## 24. Correction After Close — Policy Statement (current)

```text
INTENDED (ATT-D1 / ATT-D2 / coded):
  Mark  → OPEN only
  Correct → allowed when session is OPEN or CLOSED
  Correct → forbidden when CANCELLED (when status used)

GAP: not restated in HTTP OpenAPI/docs; behavior is correct in Application.
```

No handler change in this audit.

---

## 25. Final Gate Statement

```text
R1.8 READINESS AUDIT COMPLETE.

NO IMPLEMENTATION AUTHORIZATION GRANTED.

STOP.

Human approval is required before any R1.8 implementation.
```

Do not create migrations.  
Do not modify schema, RLS, CQRS, HTTP, UI, permissions, or legacy writer.  
Do not proceed to R1.9 automatically.  
**R1.7 Strategy A remains authoritative.**

---

## SIS CHANGE REPORT (R1.8 Audit)

```text
Status: PASS (audit deliverable)
Risk: N/A (no mutation)

Application Code Modified: NO
Database Modified: NO
API Modified: NO
UI Modified: NO
Governance Modified: YES (this gate only)

Files Added:
  .cursor/database/phase-attendance/12-ATTENDANCE-R1.8-LIFECYCLE-READINESS-GATE.md

Final Gate Status: READY WITH CONDITIONS
Human Approval Required: YES — before any R1.8 implementation
```
