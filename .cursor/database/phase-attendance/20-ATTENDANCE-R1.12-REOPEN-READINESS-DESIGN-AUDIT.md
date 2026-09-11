# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.12 — REOPEN SESSION READINESS / DESIGN AUDIT

**Document Type:** READINESS + DESIGN AUDIT ONLY  
**Date:** 2026-09-11  
**Implementation Authorization:** **NOT GRANTED**

**Upstream:** ATT-D1 Design Lock · R1.8 / R1.8A · R1.10 · R1.11 Cancel Design Lock + Implementation Gate 19 (PASS)

```text
NO MIGRATIONS · NO DDL · NO CODE · NO PERMISSIONS · NO ROUTES · NO UI
ReopenAttendanceSession MUST NOT be implemented in this phase.
R1.11 / R1.8A MUST NOT be modified.
```

---

## 1. Scope

This audit answers one primary question:

> Should `CANCELLED → OPEN` be supported as a true Reopen on the **same session row**,  
> or should **CANCELLED remain terminal** and recovery remain **CreateAttendanceSession**  
> (new OPEN row) under R1.8A OPEN-only uniqueness?

Secondary (related, not primary): whether a separate `CLOSED → OPEN` reopen remains justified.

**In scope:** repository inspection, domain/security/concurrency/idempotency/event/API/DB analysis, recommendation, readiness gate.  
**Out of scope:** any implementation or mutation of Attendance behavior.

---

## 2. Authorization Boundary

```text
AUTHORIZED: R1.12 Reopen Session — Readiness / Design Audit only
NOT AUTHORIZED: R1.12 implementation
NOT AUTHORIZED: routes, permissions, migrations, schema, UI, reporting
NOT AUTHORIZED: changes to R1.11, R1.8A, lifecycle guards, or status vocabulary
```

Human must issue a separate, explicit implementation authorization before any Reopen code exists.

---

## 3. Current Lifecycle (PROVEN)

### 3.1 Status vocabulary

| Value | Constant | Source |
|------:|----------|--------|
| 1 | `SessionStatus::Open` | Domain VO + R1.8A CHECK |
| 2 | `SessionStatus::Closed` | Domain VO + R1.8A CHECK |
| 3 | `SessionStatus::Cancelled` | Domain VO + R1.8A CHECK |

Helpers (PROVEN):

- `allowsMark()` → OPEN only  
- `allowsCorrect()` → not CANCELLED  

### 3.2 Live transitions

```text
CreateAttendanceSession     → OPEN (1)
MarkSectionAttendance       → requires OPEN; else SessionNotOpenException
CorrectAttendanceRecord     → rejects CANCELLED (SessionCancelledException); OPEN|CLOSED OK
CloseAttendanceSession      → OPEN → CLOSED (conditional WHERE status=OPEN)
CancelAttendanceSession     → OPEN|CLOSED → CANCELLED (conditional; terminal on row)
```

**Absent (PROVEN):**

- No `ReopenAttendanceSession` command/handler/result  
- No `attendance.session.reopen` permission (auth unit test asserts absence)  
- No reopen route (`routes/api.php` has cancel only)  
- No `AttendanceSessionReopened` event  

### 3.3 Terminal policy (LOCKED by R1.11)

From Gate 18 / Gate 19 (authoritative for Cancel):

```text
Cancel voids THIS session identity.
CANCELLED → OPEN  = NO
CANCELLED → CLOSED = NO
Recreation = new CreateAttendanceSession (new id), not status restore.
```

---

## 4. R1.8A Interaction (PROVEN)

| Invariant | Evidence |
|-----------|----------|
| Status CHECK `{1,2,3}` | Migration R1.8A + PG tests |
| Partial UNIQUE OPEN natural key `WHERE status = 1` | `attendance_sessions_open_natural_key_uidx` |
| Duplicate OPEN rejected | PG integrity tests (incl. concurrent 23505) |
| CLOSED → new OPEN same natural key | `closed_session_allows_new_open_with_same_natural_key` |
| CANCELLED → new OPEN same natural key | `cancelled_then_new_open_same_natural_key_allowed` (R1.11 PG) |

**Implication:** Recovery after cancel **already exists** without Reopen:

```text
CANCELLED (session_id = N, status = 3)
        ↓ CreateAttendanceSession (same natural key)
NEW OPEN (session_id = M ≠ N, status = 1)
```

R1.8A remains authoritative and must not be weakened for a Reopen design.

---

## 5. R1.11 Interaction (PROVEN — PASS / CLOSED)

| Artifact | Status |
|----------|--------|
| `CancelAttendanceSession` | Implemented |
| Permission `attendance.session.cancel` | Manager-only |
| Event `AttendanceSessionCancelled` | Mandatory; same UoW |
| Idempotency | Required `X-Idempotency-Key` |
| Preservation | Session row + records + summaries kept; status-only mutation |
| Restore/Reopen | Explicitly **not** implemented |
| Gate 19 | **PASS** |

Any `CANCELLED → OPEN` same-row reopen would **contradict** the R1.11 irreversibility lock unless ATT-D1 / Gate 18 are formally revised by human authorization. This audit must not weaken that lock.

---

## 6. Domain Analysis

### Option A — True row restoration (`CANCELLED → OPEN` same row)

| Lens | Analysis |
|------|----------|
| Identity | Preserves `session_id`; collapses “voided identity” back into operational identity |
| Cancel meaning | Undoes the operational meaning of Cancel; contradicts Gate 18 “irreversible on same session row” |
| Attached records | Marks/corrects already written under a voided session would become Mark/Correct-eligible again without a new fact boundary |
| Summaries | `refreshDailySectionSummary` currently aggregates records joined to sessions for section+date **without excluding CANCELLED**; reopening would further blur void vs active contribution |
| Outbox history | Would produce `…Cancelled` then later `…Reopened` on same id — ambiguous whether cancel was a mistake or a temporary hold |
| Temporal meaning | Original session “ended” as void; restore rewrites timeline semantics without versioning |
| Concurrent replacement | After Cancel, a replacement OPEN may already exist (R1.8A). Reopening the cancelled row to OPEN would collide with partial UNIQUE (`status=1`) → conflict or dual-ops confusion |
| Verdict on Option A | **Architecturally hostile** to R1.11 terminal Cancel + audit clarity |

### Option B — Keep CANCELLED terminal; create new OPEN (R1.8A)

| Lens | Analysis |
|------|----------|
| Historical cancel | Preserved as immutable voided session + preserved records |
| New operational boundary | New `session_id`; clean Create + optional Mark/Close path |
| Natural key | Allowed by OPEN-only unique (PROVEN) |
| Record isolation | New marks attach to new session; cancelled facts stay under cancelled session |
| Audit clarity | Clear event chain: Created → … → Cancelled; separately Created (new) → … |
| Ops simplicity | Reuses existing Create + permissions + idempotency; **no new lifecycle command** |
| Reporting | Still needs operational filters for CANCELLED (ATT-D1 intent; not changed by this audit) |
| Reconciliation | Corrections on cancelled session remain forbidden; corrections belong on active OPEN/CLOSED sessions |
| Verdict on Option B | **Aligned** with R1.8A, R1.11, ATT-D1 terminal CANCELLED |

### Historical “Reopen” candidate (`CLOSED → OPEN`) — distinct from CANCELLED restore

R1.8 lifecycle gate proposed Reopen as **CLOSED → OPEN** (early close), **not** CANCELLED restore. That candidate remains:

- Technically easy  
- **Business requirement not evidenced**  
- Weakens “closed = frozen marks” discipline  
- Still **not** required for cancel recovery (Create already covers CLOSED + new OPEN)

This audit’s primary CANCELLED question does **not** unlock CLOSED→OPEN.

---

## 7. Critical Questions — Answers

| # | Question | Answer |
|---|----------|--------|
| 1 | Is CANCELLED truly intended to be terminal? | **YES** — ATT-D1 + R1.11 Gate 18/19 lock irreversibility on the same row |
| 2 | Does reopening the same row violate historical cancellation meaning? | **YES** — Cancel voids session identity; restore collapses void into active ops |
| 3 | Can cancelled-session records safely become active again? | **NO (unsafe by default)** — they are evidence under a voided session; reactivation without a new identity boundary is audit-ambiguous |
| 4 | Would Reopen create audit/history ambiguity? | **YES** — Cancelled + Reopened on same id vs void + new Create |
| 5 | Would a new OPEN provide a cleaner event/history boundary? | **YES** |
| 6 | Does R1.8A already provide sufficient recovery? | **YES** — PROVEN for CLOSED and CANCELLED |
| 7 | Is there an actual business requirement for Reopen? | **NOT EVIDENCED** in ops docs / gates (R1.8, R1.10); no named use case for same-row CANCELLED restore |
| 8 | Would Reopen require new permissions? | **If ever built: YES** — must not reuse `attendance.session.cancel`; dedicated `attendance.session.reopen` (manager SoD). Today: absent by design |
| 9 | Would it require a new domain event? | **If ever built: YES** — e.g. `AttendanceSessionReopened` |
| 10 | Would it require new idempotency semantics? | **If ever built: YES** — at least R1.11-class mandatory key + payload identity |
| 11 | Would it require reporting/list changes? | **Possibly** — operational filters for CANCELLED already ATT-D1 intent; reopen would force dual-path reporting rules |
| 12 | Would it require schema/DDL? | **Not for status vocabulary** (CHECK already allows 1/2/3). Transition legality remains app-side |
| 13 | Would it require uniqueness changes? | **Must NOT** — R1.8A OPEN unique must stay. Reopen would have to fail if another OPEN exists |
| 14 | Authorization / privilege-escalation risks? | **HIGH if mis-granted** — teacher cancel/reopen or reusing cancel permission would enable void↔restore cycles |
| 15 | Cancel after marks already recorded? | Records preserved; Mark/Correct blocked; recovery = new OPEN + new marks (Option B) |
| 16 | Another actor creates replacement OPEN? | Allowed (R1.8A). Same-row Reopen would then conflict with unique OPEN — operational hazard |
| 17 | Concurrent Reopen/Mark/Close/Cancel? | See §8 — without Reopen, existing conditional mutations already define winners |

---

## 8. Security Analysis

| Topic | Finding |
|-------|---------|
| Reuse `attendance.session.cancel` for reopen? | **FORBIDDEN** if reopen ever exists — opposite intents; SoD violation |
| Teacher access | **Must remain denied** for any restore capability |
| Viewer | **Denied** |
| Creator-of-session alone | **Insufficient** |
| `attendance.correct` participation | Correct fixes student facts; **not** a session lifecycle restore |
| Dedicated permission | Historical reservation: `attendance.session.reopen` (ATT-D3 deferred; still absent in live catalog) |
| School ownership | Strategy A + SchoolContext + RLS FORCE remain mandatory |
| Fail-closed posture | Prefer no reopen capability over soft restore |

**Live catalog (PROVEN):**

```text
attendance_manager: … + attendance.correct + attendance.session.cancel
attendance_teacher: view/create/mark/close  (NO cancel, NO reopen)
attendance_viewer: view only
attendance.session.reopen: ABSENT
```

---

## 9. Concurrency Analysis (design-only)

If Option A were ever authorized (not recommended), minimum semantics would be:

```sql
-- conceptual only — DO NOT IMPLEMENT
UPDATE attendance.sessions
SET status = 1
WHERE id = :sessionId
  AND status = 3
  -- AND no conflicting OPEN natural key (R1.8A will also enforce)
RETURNING ...;
```

| Race | Expected outcome under Option A | Under Option B (recommended) |
|------|----------------------------------|------------------------------|
| Reopen vs Cancel | N/A if already cancelled; cancel already terminal | Cancel then Create — no reopen |
| Reopen vs Close | Close requires OPEN — N/A on CANCELLED | N/A |
| Reopen vs Mark | Mark requires OPEN — would suddenly become legal mid-flight | Mark only on new OPEN |
| Reopen vs Correct | Correct rejects CANCELLED until reopen succeeds | Correct only on non-cancelled sessions |
| Dual Reopen | Exactly one winner; second 409 | N/A |
| Reopen after replacement OPEN exists | Unique OPEN conflict / 409 | Desired: cancelled stays cancelled; replacement remains sole OPEN |

**Option B concurrency** already validated:

- Cancel vs Close: conditional updates; one winner  
- Cancel then Create: allowed  
- Duplicate Create OPEN: R1.8A 409  

No new concurrency surface required if Reopen is not introduced.

---

## 10. Idempotency Analysis (design-only)

| Topic | If Reopen existed | Current R1.11 Cancel |
|-------|-------------------|----------------------|
| Mandatory `X-Idempotency-Key` | Should be **required** (elevated lifecycle) | Required |
| Exact payload replay | Required | Required |
| Payload conflict | Required | Required |
| Cached success | `from_idempotency_cache=true` | Yes |
| Already OPEN without cache | Conflict (not silent success) | Analog: already CANCELLED → 409 |

**Reliability concern without Reopen:** None new — Create + Cancel idempotency already cover recovery path.

---

## 11. Event / Audit Analysis

| Path | Event boundary |
|------|----------------|
| Option A | Would need `AttendanceSessionReopened` with `session_id`, `school_id`, `academic_year_id`, `previous_status=3`, `new_status=1`, `reason`, `reopened_by`, `occurred_at` |
| Option B | Keep `AttendanceSessionCancelled` on voided row; new session emits existing `AttendanceSessionCreated` |

Lifecycle clarity:

```text
PREFERRED (Option B):
  OPEN → (CLOSED?) → CANCELLED     [terminal row]
  + separately:
  Create → OPEN → …

REJECTED for CANCELLED (Option A):
  CANCELLED → OPEN                 [rewrites voided identity]
```

Audit implication: Option B preserves a permanent void fact; Option A turns Cancel into a soft pause.

---

## 12. API Proposal

**No route is added in this phase.**

Because primary recommendation is **B**, **no Reopen API is proposed for authorization**.

Operational recovery API already exists:

```http
POST /api/v1/attendance/sessions
Header: X-Idempotency-Key
Body: natural-key create payload
```

If a future human ever reverses this audit and authorizes Option A, a **non-binding sketch only** would be:

```http
POST /api/v1/attendance/sessions/{session}/reopen
auth:sanctum + SchoolContext + attendance.session.reopen (manager)
Header: X-Idempotency-Key (required)
Body: { "reason": "..." }
→ 200 { previous_status:3, new_status:1 }
→ 409 if not CANCELLED or OPEN unique conflict
```

This sketch is **not** recommended and **not** design-locked.

---

## 13. Database Impact

| Change type | Required for recommended Option B? | Required for rejected Option A? |
|-------------|------------------------------------|----------------------------------|
| Migration | **NO** | No (status 3→1 already in CHECK vocabulary) |
| CHECK change | **NO** | No |
| Index change | **NO** | **Must not** drop/alter OPEN unique |
| Uniqueness change | **NO** | **Must not** |
| RLS / FORCE RLS | **NO** | No |

```text
NO DATABASE DDL CHANGE JUSTIFIED FOR R1.12.
R1.8A REMAINS PROTECTED.
```

---

## 14. Alternatives

| Alternative | Disposition |
|-------------|-------------|
| **A — Same-row CANCELLED→OPEN** | Reject — violates R1.11 terminal Cancel; audit ambiguity; unique-OPEN races with replacement sessions |
| **B — Keep CANCELLED terminal; Create new OPEN** | **Primary recommendation** — already implemented & tested |
| **CLOSED→OPEN “early close reopen”** | Separate candidate; still **not evidenced**; remains deferred; not unlocked by Cancel |
| Soft-delete / hard-delete cancelled sessions | Forbidden by constitution / R1.11 preservation |
| Reuse Cancel permission as reverse | Security fail — SoD violation |

---

## 15. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Implementing Option A without ATT-D1 revision | **CRITICAL** | Do not authorize; keep Gate 18 irreversibility |
| Privilege escalation via shared cancel/reopen permission | **HIGH** | No reopen permission; never alias cancel |
| Dual OPEN after cancel+create+reopen | **HIGH** | Avoid reopen; R1.8A unique is last line of defense only |
| Reporting double-count (cancelled + new OPEN records in summary) | **MEDIUM** | Existing summary join does not exclude CANCELLED — separate reporting design (out of scope); Option B still preferred historically |
| Premature CLOSED→OPEN weakening close discipline | **MEDIUM** | Require named ops use case before any reopen design lock |
| Scope creep into UI / R1.13 | **LOW** if STOP honored | Hard STOP after this audit |

---

## 16. Recommendation

### Primary recommendation (exactly one)

```text
B — KEEP CANCELLED TERMINAL; CREATE NEW OPEN SESSION
```

**Justification (architecture + operations):**

1. R1.11 explicitly locked Cancel as irreversible on the same session row.  
2. R1.8A already provides a tested recovery mechanism (CANCELLED → new OPEN).  
3. Same-row restore collapses voided evidence into active operations and creates audit ambiguity.  
4. No evidenced business requirement for `CANCELLED → OPEN` restore.  
5. Option B preserves historical Cancel events and record attachment under the voided session identity.  
6. Option B requires **zero** new DDL, permissions, routes, or lifecycle commands.

**Not selected:**

- **A** — Rejected for lifecycle/audit/concurrency reasons above.  
- **C** — Not used as primary: the CANCELLED recovery question is **decidable now** (answer = terminal + Create). Deferral applies only to a *different* candidate (`CLOSED → OPEN`), which remains out of primary recommendation.

### Secondary note (CLOSED → OPEN)

```text
CLOSED → OPEN reopen remains DEFERRED pending a named operational use case.
It is NOT authorized by this audit and is NOT required for Cancel recovery.
```

---

## 17. Proposed Design Lock

```text
NO REOPEN DESIGN LOCK IS PROPOSED FOR IMPLEMENTATION AUTHORIZATION.
```

Instead, the following policy statements are recommended as **audit conclusions** (not new code):

1. `CANCELLED` remains **terminal** on the existing session row.  
2. Recovery after Cancel = `CreateAttendanceSession` under R1.8A.  
3. `CANCELLED → OPEN` same-row restore is **not** an Attendance R1 capability.  
4. `attendance.session.reopen` remains **absent** until a future human-authorized design revises ATT-D1 with a named use case (likely CLOSED→OPEN, not Cancel restore).  
5. R1.11 Gate 19 PASS remains closed; do not reopen Cancel implementation.

---

## 18. Explicit Implementation Readiness Status

| Item | Status |
|------|--------|
| Same-row CANCELLED→OPEN implementation | **NOT READY / NOT JUSTIFIED** |
| New OPEN after Cancel (Create) | **ALREADY IMPLEMENTED** (R1.8A + Create + R1.11 PG proof) |
| CLOSED→OPEN Reopen implementation | **NOT READY** (missing business requirement + design lock) |
| Permission/route/event for Reopen | **MUST NOT be created** under this audit |
| DDL | **NONE justified** |

### Gate verdict

```text
DEFERRED
```

**Meaning:** Do **not** authorize R1.12 Reopen implementation. Prefer Option B (terminal Cancel + Create). Revisit only if human presents a named operational requirement that forces ATT-D1 revision — and even then, prefer designing against `CLOSED → OPEN` use cases, **not** undoing Cancel.

---

## Evidence Index (repository)

| Evidence | Location |
|----------|----------|
| Status VO / guards | `app/Domain/Attendance/ValueObjects/SessionStatus.php` |
| Cancel handler | `app/Application/Attendance/Commands/CancelAttendanceSessionHandler.php` |
| Conditional cancel mutation | `EloquentAttendanceWriteRepository::cancelSessionIfOpenOrClosed` |
| Mark OPEN-only | `MarkSectionAttendanceHandler` |
| Correct rejects CANCELLED | `CorrectAttendanceRecordHandler` |
| Permission catalog | `config/security.php` |
| Reopen permission absent | `AttendanceAuthorizationTest::no_role_receives_…_reopen_permissions` |
| Cancel route only | `routes/api.php` |
| CANCELLED → new OPEN | `AttendanceCancelSessionPostgreSqlTest` |
| CLOSED → new OPEN / duplicate OPEN | `AttendanceR18aIntegrityPostgreSqlTest` |
| Cancel irreversibility lock | Gate 18 §§3, 13; Gate 19 §E |
| Feature SSOT | `.cursor/architecture/features/Attendance.md` (Reopen NOT AUTHORIZED) |

---

## STOP

```text
R1.12 REOPEN READINESS / DESIGN AUDIT COMPLETE.

RECOMMENDATION: B — KEEP CANCELLED TERMINAL; CREATE NEW OPEN SESSION.
GATE: DEFERRED (do not authorize Reopen implementation).

NO CODE CHANGED.
NO ROUTE CREATED.
NO PERMISSION CREATED.
NO MIGRATION CREATED.
R1.11 NOT MODIFIED.
R1.8A NOT MODIFIED.
UI NOT STARTED.
R1.13 NOT STARTED.

STOP. Wait for explicit human authorization before any next Attendance phase.
```
