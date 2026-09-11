# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.10 — NEXT-PHASE READINESS AUDIT

**Document Type:** AUDIT ONLY — NO IMPLEMENTATION  
**Date:** 2026-09-11  
**Upstream authority:** R1.3–R1.9 · Gate 16 (R1.9 Option B PASS)

```text
NO MIGRATIONS · NO DDL · NO CODE CHANGES · NO API/UI/PERMISSION CHANGES
NO LEGACY CLASS DELETION · NO PARTITION CREATION
```

---

## Executive Verdict

```text
READY WITH CONDITIONS

Attendance R1 core (CQRS + HTTP + RLS Strategy A + R1.8A integrity +
R1.9 legacy quarantine) is STABLE and regression-PASS.

None of the deferred candidates (Reopen / Cancel / UI / year partitions)
is READY for an immediate implementation authorization.

NO NEXT IMPLEMENTATION PHASE IS READY.
ADDITIONAL DESIGN/READINESS WORK IS REQUIRED.
```

**Conditions (governance, not code defects):**

1. Human prioritization among deferred candidates.  
2. A dedicated Design Lock / Decision Gate before any implementation grant.  
3. Explicit implementation phrase naming exactly one capability.  
4. Preserve all immutable locks in §Architectural Safety Rules below.

**Score: 81 / 100** (stability of completed R1; incompleteness of deferred design)

| Area | /10 |
|------|----:|
| R1.9 quarantine integrity | 10 |
| CQRS / API core completeness | 9 |
| RLS / Strategy A integrity | 9 |
| R1.8A integrity integrity | 9 |
| Reopen design completeness | 4 |
| Cancel design completeness | 5 |
| UI readiness | 2 |
| Year-partition measurement readiness | 4 |
| Ops/external-caller certainty (legacy delete) | 5 |
| Scope discipline of this audit | 10 |

---

## Candidate Matrix

| Candidate | Readiness | Blockers | Required Preconditions |
|-----------|-----------|----------|------------------------|
| **Reopen** | **NOT YET DEFINED** (design deferred) | ATT-D1 locks CLOSED→OPEN as deferred; no permission/route/command/event; conflicts with “close discipline”; R1.8A already allows CLOSED+new OPEN recreate | Human ops use-case; Design Lock revising ATT-D1; SoD permission; concurrency vs OPEN unique; outbox/idempotency; API contract; then implement auth |
| **Cancel** | **READY WITH CONDITIONS** *(design only — not implement)* | No `attendance.session.cancel`; no handler/route/event; from-OPEN-only vs also-CLOSED undecided; report visibility undecided | Design Lock (transitions, irreversibility, reports, SoD); permission catalog approval; outbox event; API contract; preserve records; then implement auth |
| **UI** | **BLOCKED** (premature) | No Inertia/React Attendance pages; lifecycle Reopen/Cancel unfinished; UI governance forbids building on unfinished contracts | Finalize deferred lifecycle decisions **or** explicitly scope UI to locked Create/Mark/Correct/Close/query surface only via separate UI design lock + auth |
| **Academic-Year Partitioning** | **READY WITH CONDITIONS** *(measurement/design — not create partitions)* | Live table is partitioned parent with **only** `records_default`; **0** live rows → no measured pruning evidence; year-partition ops/FK/UNIQUE plan not locked | Measured workload or capacity trigger; partition design lock (create-on-year, no DEFAULT?, migrate-from-default); RLS/FK impact analysis; DBA ops runbook; then implement auth |

---

## Current Attendance Architecture

### Authoritative write path (PROVEN)

```text
HTTP (AttendanceController + FormRequest + AttendancePolicy)
  → Application Commands/Handlers
  → Domain guards/VOs
  → AttendanceWriteRepository (Infrastructure)
  → PostgreSQL (FORCE RLS + R1.8A constraints)
  → Outbox + IdempotencyStore
```

| Command | Status |
|---------|--------|
| `CreateAttendanceSession` | Implemented → OPEN; Strategy A `school_id` stamp; R1.8A duplicate OPEN conflict |
| `MarkSectionAttendance` | Implemented → OPEN only; ATT-D4; ≤500; session_date |
| `CorrectAttendanceRecord` | Implemented → not CANCELLED; reason; outbox previous→new |
| `CloseAttendanceSession` | Implemented → optimistic OPEN→CLOSED |

### Authoritative read path (PROVEN)

`GetAttendanceSession` · `ListAttendanceSessions` · `GetSectionAttendance` · `GetStudentAttendance` · `GetDailySectionSummary`

### Lifecycle (LIVE)

```text
Create → OPEN → (Mark*) → Close → CLOSED
Correct allowed on OPEN or CLOSED
CANCELLED = reserved (CHECK + Domain); no writer sets 3
CLOSED + new OPEN same natural key = ALLOWED (R1.8A) — recreate without Reopen
```

### Legacy path (PROVEN R1.9)

```text
AttendanceBatchService::recordSectionAttendance → quarantined fail-closed
AttendanceBatchService::refreshDailySummary → quarantined fail-closed
error_code: attendance.legacy_writer_quarantined
QuarantinedLegacyWriterGuard → [ARCH-LEGACY-001]
Class file retained; full deletion NOT authorized
```

### Permissions (LIVE catalog)

```text
attendance.view
attendance.session.create
attendance.mark
attendance.correct
attendance.session.close
```

**Absent:** `attendance.session.reopen`, `attendance.session.cancel`

### Events (LIVE)

`AttendanceSessionCreated` · `SectionAttendanceMarked` · `AttendanceCorrected` · `AttendanceSessionClosed`  
**Absent:** Reopen / Cancel domain events

---

## R1.9 Regression Result

| Check | Result | Classification |
|-------|--------|----------------|
| Legacy methods throw quarantine exception | **PASS** (unit) | Product OK |
| `architecture:validate --fitness` incl. `legacy_writer_quarantine` | **PASS** | Product OK |
| Quarantined reference guard | **PASS** | Product OK |
| Unit Attendance | **22 PASS** | Product OK |
| R1.4 WritePath + R1.7 RLS + R1.8A integrity (PG) | **13 PASS** | Product OK |
| R1.5 HTTP API | **5 PASS** | Product OK |
| Class file still present | **PROVEN** | As designed |
| No new unsupported app/routes references | **PROVEN** | As designed |

```text
R1.9 OPTION B REMAINS INTACT.
NO PRODUCT DEFECT OBSERVED IN REGRESSION PACK.
```

---

## Database Readiness

### Live catalog findings (PROVEN on PostgreSQL — empty operational data)

| Object | Finding |
|--------|---------|
| `attendance.sessions` | Columns include `school_id NOT NULL`, `status` default 1 |
| Status CHECK | `attendance_sessions_status_check` → `status IN (1,2,3)` |
| OPEN unique | `attendance_sessions_open_natural_key_uidx` … `NULLS NOT DISTINCT WHERE status=1` |
| Sessions RLS | ENABLE=1 FORCE=1; SELECT/INSERT/UPDATE policies |
| `attendance.records` | `relkind=p` (partitioned parent) |
| Year children | **Only** `records_default` (no `records_ay_*`) |
| Records RLS | ENABLE=1 FORCE=1 |
| `daily_section_summary` RLS | ENABLE=1 FORCE=1 |
| Row counts | `sessions=0`, `records=0` |
| Triggers (session status transitions) | **None** observed for lifecycle matrix (app-owned) |

### Lifecycle / academic-year structure

- `academic_year_id` present on sessions, records, summary.  
- Create enforces year date bounds in Application.  
- Partition key already `academic_year_id` on records parent.  
- **Year-specific LIST partitions not created** — all volume (currently zero) routes to DEFAULT.

### Partition readiness (Candidate D)

| Question | Answer |
|----------|--------|
| Key availability | **Yes** (`academic_year_id`) |
| Parent already partitioned? | **Yes** |
| Year partitions exist? | **No** — DEFAULT only |
| Measured volume justifying new partitions? | **No** (0 rows live) |
| Blueprint aspirational P0? | Yes (capacity model) |
| Adaptive governance | Do **not** optimize for a fixed student number without measurement |
| FK/UNIQUE implications | Year partitions complicate composite uniqueness; need design lock |
| Cross-year queries | Already year-scoped in CQRS queries; pruning benefit unproven at volume=0 |
| Migration from DEFAULT | Requires explicit cutover plan if DEFAULT has future data |

**Do not create year partitions in R1.10.**

---

## Security Readiness

| Concern | State |
|---------|-------|
| Authorization | Policy + Permission constants for Create/Mark/Correct/Close/View |
| School isolation | SchoolContext GUC + Strategy A `school_id` + FORCE RLS |
| SoD | Mark ≠ Correct (manager-only correct) — locked for current surface |
| Auditability | Outbox events for implemented commands; security audit on create |
| Idempotency | Create/Mark/Correct/Close support keys per R1 design |
| Concurrency | Close optimistic; R1.8A DB unique for OPEN; Mark OPEN re-check |
| Reopen/Cancel SoD | **Undefined** (no permissions) |
| Legacy bypass | Quarantined + architecture guard |

---

## Architecture Readiness

| Concern | State |
|---------|-------|
| CQRS authoritative | **Yes** |
| Legacy quarantine | **Yes** (R1.9) |
| Architecture fitness | **PASS** |
| Forbidden references | Baseline + `QuarantinedLegacyWriterGuard` |
| Controller DB writes | None observed on AttendanceController |
| Service-layer write bypass | Legacy fail-closed |
| Bypass risks residual | External/tinker scripts **UNKNOWN** (runtime still fail-closed) |

---

## Database Safety Analysis (per candidate)

### A — Reopen

| # | Question | Answer | Prerequisite if YES |
|---|----------|--------|---------------------|
| 1 | Schema changes? | **No** (status UPDATE only) | — |
| 2 | RLS changes? | **No** (same school_id) | — |
| 3 | New permissions? | **Yes** | Catalog + role grants approval |
| 4 | New CQRS commands? | **Yes** | `ReopenAttendanceSession` Design Lock |
| 5 | New state transitions? | **Yes** | Revise ATT-D1 CLOSED→OPEN |
| 6 | New audit/outbox? | **Yes** | New domain event |
| 7 | Affect historical facts? | **No** (records unchanged) / **soft** (re-enables Mark) | Policy on mark-after-reopen |
| 8 | Affect concurrency? | **Yes** | Must respect R1.8A OPEN unique (cannot reopen if another OPEN exists for key) |
| 9 | Affect R1.8A uniqueness? | **Yes** (interaction) | Explicit conflict rules vs recreate-new-OPEN |
| 10 | API contract changes? | **Yes** | New route + errors |

### B — Cancel

| # | Question | Answer | Prerequisite if YES |
|---|----------|--------|---------------------|
| 1 | Schema? | **No** (status→3; CHECK already allows) | — |
| 2 | RLS? | **No** | — |
| 3 | New permissions? | **Yes** | `attendance.session.cancel` + roles |
| 4 | New CQRS commands? | **Yes** | `CancelAttendanceSession` |
| 5 | New transitions? | **Yes** | Lock from-OPEN and/or from-CLOSED |
| 6 | New outbox? | **Yes** | Cancel event |
| 7 | Historical facts? | **Must preserve records** | No hard delete; query filters TBD |
| 8 | Concurrency? | **Yes** | Optimistic status predicate |
| 9 | R1.8A uniqueness? | **Low** (CANCELLED not in OPEN unique) | Confirm no accidental OPEN recreate rules |
| 10 | API changes? | **Yes** | New route + error codes |

### C — UI

| # | Question | Answer | Prerequisite if YES |
|---|----------|--------|---------------------|
| 1–2 | Schema/RLS? | **No** (if presentation-only) | — |
| 3 | New permissions? | **Maybe** | Only if new actions exposed |
| 4–6 | Commands/events? | **No** if UI wraps existing API only | UI design lock to locked surface |
| 7–9 | History/concurrency/R1.8A? | Indirect via UX | Error/idempotency contract UX |
| 10 | API changes? | **Should not** for first UI | Consume existing contracts |

### D — Academic-year partitioning

| # | Question | Answer | Prerequisite if YES |
|---|----------|--------|---------------------|
| 1 | Schema changes? | **Yes** (new LIST partitions / possibly DEFAULT policy) | database-change skill + blueprint + impact |
| 2 | RLS changes? | **Usually No** (inherits) | Verify FORCE on children |
| 3 | New permissions? | **No** | — |
| 4–6 | CQRS/transitions/events? | **No** (infra) | Partition manager / year-create hook |
| 7 | Historical facts? | **Migration risk** if moving DEFAULT rows | Explicit data move plan |
| 8 | Concurrency? | **Ops** during attach/detach | Maintenance window |
| 9 | R1.8A? | **No** (sessions unpartitioned) | — |
| 10 | API? | **No** | — |

---

## Dependency Graph

### Reopen

```text
Human ops use-case approval
   ↓
ATT-D1 Design Lock revision (CLOSED→OPEN + SoD + vs recreate-OPEN)
   ↓
Human implementation authorization
   ↓
Command/Handler/Event/Permission/Route/Tests
   ↓
Regression (R1.4–R1.9 + R1.8A reopen conflicts)
   ↓
Implementation Gate
```

### Cancel

```text
Human decisions (from-OPEN only? reports? irreversible?)
   ↓
Cancel Design Lock (permission, event, API, query filters)
   ↓
Human implementation authorization
   ↓
Command/Handler/Event/Permission/Route/Tests
   ↓
Regression (Correct/Mark reject CANCELLED already partially coded)
   ↓
Implementation Gate
```

### UI

```text
Decide UI scope (locked API only vs wait for Cancel/Reopen)
   ↓
UI Design Lock (Inertia pages, a11y, RTL, tokens, optimization gate)
   ↓
Human UI authorization
   ↓
Presentation adapters only (no domain logic)
   ↓
UI + API contract tests
   ↓
UI Gate
```

### Academic-Year Partitioning

```text
Measured evidence OR explicit capacity ADR override
   ↓
Partition Design Lock (children naming, DEFAULT fate, year-create hook, FK/UNIQUE)
   ↓
Human database-change authorization
   ↓
Versioned migrations + partition manager + tests
   ↓
EXPLAIN/prune validation + RLS child verify
   ↓
Database Implementation Gate
```

---

## Recommended Next Phase

```text
NO NEXT IMPLEMENTATION PHASE IS READY.
ADDITIONAL DESIGN/READINESS WORK IS REQUIRED.
```

**Do not force** Reopen, Cancel, UI, or year-partition **implementation**.

### Optional sequencing hint (design-only — not an authorization)

If humans must pick one **design-lock** follow-up first, evidence slightly favors:

```text
PREFERRED DESIGN-ONLY FOLLOW-UP: Cancel Session Design Lock
```

**Why Cancel over Reopen (evidence):**

- Status `3` already in Domain + R1.8A CHECK.  
- Correct already rejects CANCELLED.  
- R1.8A already provides CLOSED→new OPEN **recreate** without Reopen.  
- Cancel is semantically distinct from Correct (void session vs fix student fact).

**Why not year partitions next for implementation:** volume=0; adaptive governance requires measurement; parent+DEFAULT already exists.

**Why not UI next:** no pages; unfinished lifecycle; UI Contract forbids premature surfaces.

**Residual (outside A–D):** R1.9 Option A full legacy deletion remains deferred pending ops attestation — also **not** implementation-ready without separate auth.

---

## Architectural Safety Rules (immutable unless separately authorized)

```text
CQRS = authoritative write path
Legacy AttendanceBatchService = quarantined
No new callers to legacy writer
No direct DB write bypass
No controller write bypass
No hard deletion of historical attendance facts
RLS remains mandatory where required
FORCE RLS remains intact
Existing integrity constraints remain authoritative
No automatic schema destructive changes
No automatic FK/RLS disablement
No automatic migration of historical Attendance data
```

---

## Audit Hygiene

| Check | Result |
|-------|--------|
| Repository inspected (not docs-only) | Yes |
| Live PG catalog inspected | Yes |
| Implementation changes in this task | **None** |
| Migrations altered by this task | **None** |
| DB modified by this task | **None** (read-only queries) |
| Temp audit script removed | Yes |
| R1.9 quarantine still active | Yes |
| Architecture fitness | PASS |
| Attendance regression packs | PASS |
| This document created | Yes |

Pre-existing uncommitted/untracked work from prior R1 phases remains in the workspace; **this audit added only this gate file**.

---

## Governance State

```text
AUDIT COMPLETE.
NO IMPLEMENTATION AUTHORIZED.
NO DATABASE CHANGES AUTHORIZED.
NO API CHANGES AUTHORIZED.
NO UI CHANGES AUTHORIZED.
NO LEGACY CLASS DELETION AUTHORIZED.

HUMAN REVIEW REQUIRED.

DO NOT AUTOMATICALLY START THE RECOMMENDED NEXT PHASE.
```

---

## SIS CHANGE REPORT (R1.10 Audit)

```text
Status: PASS (audit deliverable)
Risk: N/A

Application / Database / API / UI Modified: NO
Governance Modified: YES (this gate only)

Files Added:
  .cursor/database/phase-attendance/17-ATTENDANCE-R1.10-NEXT-PHASE-READINESS-AUDIT.md

Final Gate Status: READY WITH CONDITIONS
Next implementation phase: NONE READY
Preferred design-only follow-up (hint only): Cancel Session Design Lock
```
