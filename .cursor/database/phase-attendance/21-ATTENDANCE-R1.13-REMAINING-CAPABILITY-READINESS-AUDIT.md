# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.13 — REMAINING CAPABILITY & LIFECYCLE READINESS AUDIT

**Document Type:** READINESS / DISCOVERY AUDIT ONLY  
**Date:** 2026-09-11  
**Implementation Authorization:** **NOT GRANTED**

**Upstream:**

- ATT-D1 Design Lock  
- R1.8 / R1.8A Integrity  
- R1.10 Next-phase audit  
- R1.11 Cancel Session — **PASS / CLOSED**  
- R1.12 Reopen Audit — **DEFERRED / OPTION B**

```text
NO MIGRATIONS · NO DDL · NO CODE · NO PERMISSIONS · NO ROUTES · NO UI
NO R1.14 LIFECYCLE IMPLEMENTATION FROM THIS AUDIT ALONE
```

---

## 1. Scope

Determine whether Attendance R1 lifecycle is complete for the **currently locked scope**, or whether repository/design/business evidence justifies **exactly one** next capability design phase.

This audit does **not** invent features to force another phase.

---

## 2. Authorization Boundary

```text
AUTHORIZED: R1.13 Remaining Capability & Lifecycle Readiness Audit only
NOT AUTHORIZED: any new command, permission, route, event, migration, UI
NOT AUTHORIZED: modifications to R1.11, R1.8A, RLS, reporting, lifecycle guards
NOT AUTHORIZED: R1.14 implementation
NOT AUTHORIZED: legacy class deletion
```

---

## 3. Current Lifecycle (LOCKED + PROVEN)

```text
CreateAttendanceSession
  ↓
OPEN (1)
  ├── MarkSectionAttendance
  ├── CorrectAttendanceRecord
  ├── CloseAttendanceSession → CLOSED (2)
  └── CancelAttendanceSession → CANCELLED (3)

CLOSED (2)
  ├── CorrectAttendanceRecord (ATT-D1/D2)
  └── CancelAttendanceSession → CANCELLED (3)

CANCELLED (3)
  └── TERMINAL on the same session row
```

**Recovery after Cancel (R1.12 Option B — authoritative for this audit):**

```text
CreateAttendanceSession → NEW OPEN (new session_id)
  (R1.8A OPEN-only unique permits this)
```

| Command | Status | Permission | Event |
|---------|--------|------------|-------|
| `CreateAttendanceSession` | ✅ | `attendance.session.create` | `AttendanceSessionCreated` |
| `MarkSectionAttendance` | ✅ | `attendance.mark` | `SectionAttendanceMarked` |
| `CorrectAttendanceRecord` | ✅ | `attendance.correct` | `AttendanceCorrected` |
| `CloseAttendanceSession` | ✅ | `attendance.session.close` | `AttendanceSessionClosed` |
| `CancelAttendanceSession` | ✅ R1.11 | `attendance.session.cancel` | `AttendanceSessionCancelled` |
| Reopen (any) | ❌ absent | `attendance.session.reopen` absent | — |

HTTP surface (PROVEN): create, list, show, mark, correct, close, cancel, section, student, daily-summary — all via `AttendanceController` → handlers.

---

## 4. Candidate Capability Matrix

| Candidate | Evidence | Business Need | Architectural Need | Security Impact | Recommendation |
|-----------|----------|---------------|--------------------|-----------------|----------------|
| **Reopen CLOSED→OPEN** | Historical ATT-D3 / R1.8 proposal; R1.12 secondary note | **Not evidenced** (no named ops use case) | Optional convenience; weakens close discipline | Would need dedicated permission + SoD | **DEFERRED / NOT JUSTIFIED** as next R1 capability |
| **Reopen CANCELLED→OPEN** | R1.12 audit Option A | Contradicts R1.11 terminal Cancel | Creates audit ambiguity + OPEN unique races | High if mis-granted | **FORBIDDEN** under current locks |
| **Operational recovery after Cancel** | R1.8A + Create + R1.11/R1.12 PG proofs | Yes (mistaken session) | Already satisfied | Existing create authz | **ALREADY COVERED** |
| **Void / invalidate attendance record** | None as separate command | Unclear vs Correct | Duplicate of Correct / Cancel | New SoD risk | **NOT JUSTIFIED** |
| **Session correction / amendment** | None beyond Correct + Cancel | Unclear | Correct fixes facts; Cancel voids session | — | **ALREADY COVERED** by Correct/Cancel |
| **Attendance finalization / lock** | None beyond Close | Soft freeze via Close already | Close freezes Mark | Teacher close exists | **ALREADY COVERED** by Close |
| **Attendance approval workflow** | No Attendance approval design | Not evidenced | Would be new domain | New permissions | **NOT JUSTIFIED** |
| **Attendance reversal (record)** | Correct with reason + outbox | Fact fix already exists | Correct is reverse-capable (status change) | Manager-only correct | **ALREADY COVERED** |
| **Bulk correction** | Mark is batch; Correct is single | Convenience only | Not required for integrity | Amplifies manager risk | **DEFERRED** (UI/ops convenience, not lifecycle) |
| **Session hard-delete** | Constitution + RLS no DELETE | Forbidden | Violates academic non-delete | Critical | **FORBIDDEN** |
| **Archive** | Not in Attendance gates | Retention/lifecycle matrix separate | Not R1 write capability | — | **DEFERRED** (data lifecycle / ops, not R1 command) |
| **List default-exclude CANCELLED** | Gate 18 §10.2 FUTURE optional | Operational UX | Query contract change | Low if read-only | **DEFERRED** — reporting/API evolution, **not** lifecycle |
| **Summary exclude CANCELLED** | ATT-D1 intent; Gate 18 kept summary unchanged on Cancel; refresh joins all sessions | Operational accuracy concern | Query/projection semantics | Low | **ADVISORY reporting** — not a new lifecycle command |
| **Year partitions (`records_ay_*`)** | R1.10: parent partitioned, DEFAULT only, 0 rows | Capacity model aspirational | Measurement-gated infra | Ops/DBA | **DEFERRED** — infra, not lifecycle capability |
| **UI (Inertia Attendance)** | Feature SSOT: UI deferred | Presentation | Adapter only | Must not invent domain | **DEFERRED** — UI phase after explicit auth |
| **Legacy `AttendanceBatchService` deletion** | R1.9 Option B quarantine PASS | Ops certainty for callers | Class retained | Fail-closed today | **DEFERRED** — separate deletion auth, not lifecycle |
| **Teacher cancel** | R1.11 manager-only lock | SoD | Would weaken Cancel | Escalation | **FORBIDDEN** without ATT-D3 revision |
| **Record-level freeze independent of session** | None | Not evidenced | Session Close already freezes Mark | — | **NOT JUSTIFIED** |

**No candidate rises to REQUIRED or JUSTIFIED as the next Attendance R1 lifecycle capability.**

---

## 5. Lifecycle Completeness — State Transition Matrix

| From | To | Existing | Required for locked R1? | Justification |
|------|----|----------|-------------------------|---------------|
| — | OPEN | Yes (`Create`) | **Yes** | Create session |
| OPEN | CLOSED | Yes (`Close`) | **Yes** | Freeze routine marking |
| OPEN | CANCELLED | Yes (`Cancel`) | **Yes** | Void mistaken/abandoned session |
| CLOSED | CANCELLED | Yes (`Cancel`) | **Yes** | Void after accidental close |
| CLOSED | OPEN | No | **No** | No evidenced business requirement; R1.12 secondary DEFER |
| CANCELLED | OPEN | No | **No** | R1.11/R1.12 terminal; recovery = new Create |
| CANCELLED | CLOSED | No | **No** | Terminal |
| CANCELLED → Cancel again | Conflict / idempotent replay | Yes | **Yes** | R1.11 semantics (409 vs cache) |
| OPEN → Mark* | Yes | Yes | Routine capture |
| OPEN\|CLOSED → Correct | Yes | Yes | Fact correction |
| CANCELLED → Mark/Correct/Close | Rejected | Yes | Guards PROVEN |

```text
LOCKED R1 SESSION LIFECYCLE GRAPH IS COMPLETE.
No missing transition is justified by repository or design evidence.
```

---

## 6. Attendance Record Lifecycle

| Stage | Behavior (PROVEN) |
|-------|-------------------|
| Create records | `MarkSectionAttendance` on **OPEN** only; batch upsert; ATT-D4 enrollment window |
| Correct records | `CorrectAttendanceRecord` on OPEN or CLOSED; **not** CANCELLED; mandatory reason; outbox previous→new |
| Frozen for Mark | Session **CLOSED** or **CANCELLED** → Mark rejected |
| Frozen for Correct | Session **CANCELLED** only (CLOSED still correctable per ATT-D1) |
| On session Cancel | Records **preserved**; no delete; no status rewrite by Cancel |
| Record-level void/reversal command | **Absent** — Correct already changes status with audit |
| Hard-delete records | **FORBIDDEN** |

**Conclusion:** Record lifecycle is intentionally asymmetric from session lifecycle. Correct is sufficient for fact amendment. No separate record-void command is evidenced.

---

## 7. Reporting / Summary Analysis

| Surface | CANCELLED behavior | Classification |
|---------|-------------------|----------------|
| `GetAttendanceSession` | Returns cancelled session (status visible) | Locked Gate 18 |
| `ListAttendanceSessions` | Optional `status` filter; **no silent default exclude** | Locked Gate 18; FUTURE optional evolution |
| `GetSectionAttendance` / `GetStudentAttendance` | Include historical facts from cancelled sessions | Locked Gate 18 |
| `GetDailySectionSummary` / `refreshDailySectionSummary` | **Unchanged by Cancel**; refresh aggregates records joined to sessions for section+date **without** `WHERE sess.status <> 3` | Gate 18 §10.1; R1.12 noted MEDIUM reporting risk |

### Reporting finding (not a lifecycle gap)

```text
FINDING-RPT-001 (ADVISORY):
daily_section_summary refresh currently counts records from CANCELLED sessions
for the same section+date. After Cancel → Create new OPEN → Mark, operational
counts may include both voided and active session records.

This is a QUERY / PROJECTION concern, not a missing session-state transition.
Gate 18 explicitly did not rewrite summaries on Cancel and deferred default-exclude.
Remediation (if ever) requires a dedicated reporting/summary design authorization —
NOT a new lifecycle command.
```

Severity: **MEDIUM (reporting accuracy)** · Lifecycle blocker: **NO**

---

## 8. Security Completeness

| Control | State |
|---------|-------|
| Permissions | `view`, `session.create`, `mark`, `correct`, `session.close`, `session.cancel` |
| Absent | `session.reopen`, `administer` (auth unit asserts absence) |
| Teacher | create/mark/close — **no** correct/cancel |
| Manager | + correct + cancel |
| Policy | `AttendancePolicy` methods for all live writes |
| SchoolContext | Required on API path |
| Strategy A + FORCE RLS | R1.7 PASS (sessions/records/summary) |
| Cross-school | Handler ownership checks + RLS |
| Cancel SoD | Manager-class; not teacher; not creator-alone |

**Missing capability security:** None required for lifecycle completeness. Any future reopen/report-default-exclude would need its own permission/API auth — not now.

---

## 9. Concurrency Completeness

| Race | Existing control | Gap? |
|------|------------------|------|
| Close vs Cancel | Conditional UPDATE (OPEN-only close; OPEN\|CLOSED cancel) | **No** |
| Mark vs Close/Cancel | Mark locks/requires OPEN | **No** |
| Correct vs Cancel | Correct rejects CANCELLED | **No** |
| Duplicate Create OPEN | R1.8A partial UNIQUE + early soft guard | **No** |
| Create after Cancel | Allowed (distinct row) | **No** |
| Concurrent dual Cancel | Conditional cancel + idempotency | **No** |

```text
No concrete concurrency defect found that blocks declaring R1 lifecycle complete.
```

---

## 10. Idempotency Completeness

| Command | Key policy | Payload conflict | Classification |
|---------|------------|------------------|----------------|
| Create | Optional (`?string`) | Soft / store when present | **ADVISORY** — HTTP commonly sends key; not mandatory at domain |
| Mark | **Required** | Store/replay | **PASS** |
| Correct | **Required** | Store/replay | **PASS** |
| Close | Optional (`?string`) | Store when present | **ADVISORY** — elevated lifecycle but historically optional |
| Cancel | **Required** + payload match | Conflict on mismatch | **PASS** |

```text
FINDING-IDEM-001 (ADVISORY):
Close (and Create) allow null idempotency keys while Cancel/Mark/Correct require them.
This is inconsistency of risk posture, not a proven production defect in current tests.
Do not refactor in R1.13. Future hardening would be a dedicated quality gate, not a new capability.
```

Blockers: **NONE**

---

## 11. Legacy / Bypass Discovery

| Bypass | Caller Evidence | Security Risk | Current Status | Action |
|--------|-----------------|---------------|----------------|--------|
| `AttendanceBatchService::recordSectionAttendance` | Quarantined; throws; guard `[ARCH-LEGACY-001]` | Was HIGH; now fail-closed | **R1.9 Option B PASS** | DEFER deletion (separate auth) |
| `AttendanceBatchService::refreshDailySummary` | Same | Same | Quarantined | Same |
| Direct session status mutation outside CQRS repo | Write path only via `EloquentAttendanceWriteRepository` close/cancel/create | Contained | CQRS authoritative | None now |
| Controller business logic / DB | `AttendanceController` delegates to handlers | Low | Thin controller | None |
| Policy bypass on writes | FormRequests authorize via `AttendancePolicy` | Low if followed | PASS pattern | None |
| Stale docs prescribing legacy writes | R1.9 updated SSOT to CQRS | Medium if stale copies remain | Quarantine noted in class + feature doc | Doc hygiene only (out of scope) |

```text
No active dangerous write bypass found that blocks R1 lifecycle completeness.
```

---

## 12. Architecture Boundary

```text
HTTP (AttendanceController + FormRequest)
  ↓ AttendancePolicy + SchoolContext
  ↓ Command / Query
  ↓ Handler
  ↓ Domain VO / guards / exceptions
  ↓ AttendanceWrite/ReadRepository
  ↓ UnitOfWork + Outbox + IdempotencyStore
  ↓ PostgreSQL (CHECK + OPEN unique + FORCE RLS)
```

**Direct-write bypass of Application layer for live Attendance HTTP:** **None observed.**  
Legacy service: quarantined, not on routes.

---

## 13. Database Stability

| Object | Justified change after R1.12 deferral? |
|--------|----------------------------------------|
| Status CHECK `{1,2,3}` | **NO** — sufficient |
| OPEN partial unique | **NO** — must remain |
| FORCE RLS sessions/records/summary | **NO** |
| FKs / RESTRICT deletes | **NO** |
| Year partitions | **NO** — measurement still absent (R1.10) |
| New indexes for Reopen | **NO** — Reopen not justified |

```text
NO ADDITIONAL DDL IS JUSTIFIED MERELY BECAUSE R1.12 WAS DEFERRED.
```

---

## 14. Findings Summary

| ID | Type | Severity | Lifecycle blocker? |
|----|------|----------|--------------------|
| FINDING-RPT-001 | Summary may count CANCELLED session records | MEDIUM (reporting) | **No** |
| FINDING-IDEM-001 | Close/Create optional idempotency vs Cancel required | LOW–MEDIUM advisory | **No** |
| FINDING-UI-001 | No Attendance UI | Product gap | **No** (UI phase) |
| FINDING-OPS-001 | Year partitions not created | Capacity/ops | **No** (infra) |
| FINDING-LEG-001 | Legacy class file retained | Accepted R1.9 B | **No** |

**P0 lifecycle defects:** **NONE**

---

## 15. Recommendation

### Overall recommendation (exactly one)

```text
A — ATTENDANCE R1 LIFECYCLE COMPLETE
```

No additional **lifecycle** capability is currently justified by repository evidence, locked design documents, or explicit business requirements.

**Not selected:**

- **B** — would invent a next capability (e.g. CLOSED→OPEN, reporting default-exclude) without evidenced necessity.  
- **C** — no concrete invariant-breaking defect found that blocks safe design of future work; advisories are reporting/idempotency/UI/infra, not lifecycle holes.

### Completeness statement

```text
ATTENDANCE R1 LIFECYCLE = COMPLETE FOR CURRENT LOCKED SCOPE

No R1.14 lifecycle implementation should begin.

Future work should be opened only from a new explicit business requirement.
```

### Allowed future work classes (outside R1 lifecycle commands)

These are **not** recommended as automatic next phases; each needs its own human authorization:

1. **Reporting/summary design** (optional): operational exclude of CANCELLED from projections — addresses FINDING-RPT-001  
2. **UI design lock + implementation** (presentation only)  
3. **Year-partition measurement + design** (infra)  
4. **Legacy class deletion** (R1.9B) after caller confirmation  
5. **CLOSED→OPEN Reopen** only if a named operational use case revises ATT-D1  

---

## 16. Implementation Readiness

| Item | Status |
|------|--------|
| Next lifecycle command implementation | **NOT READY / NOT JUSTIFIED** |
| R1.14 lifecycle phase | **MUST NOT START** from this audit |
| Reporting remediation design | Optional future — requires separate auth |
| UI | Deferred — separate auth |
| DDL | None justified for lifecycle |

### Gate verdict for R1.13

```text
ATTENDANCE R1 LIFECYCLE COMPLETE FOR LOCKED SCOPE
(NO NEXT LIFECYCLE CAPABILITY AUTHORIZED)
```

---

## 17. STOP Condition

```text
R1.13 REMAINING CAPABILITY & LIFECYCLE READINESS AUDIT COMPLETE.

RECOMMENDATION: A — ATTENDANCE R1 LIFECYCLE COMPLETE.

NO CODE CHANGED.
NO PERMISSION CREATED.
NO ROUTE CREATED.
NO EVENT CREATED.
NO MIGRATION CREATED.
R1.11 / R1.8A / RLS / REPORTING / UI NOT MODIFIED.
R1.14 NOT STARTED.
LEGACY NOT DELETED.

STOP. Wait for human review before any next Attendance work.
```

---

## Evidence Index

| Area | Sources |
|------|---------|
| Commands / HTTP | `app/Application/Attendance/**`, `AttendanceController`, `routes/api.php` |
| Status / guards | `SessionStatus`, Mark/Correct/Close/Cancel handlers |
| R1.8A | migrations `…_r18a_…`, integrity PG tests |
| R1.11 | Gates 18–19; Cancel handler/repo/tests |
| R1.12 | Gate 20 — Option B / DEFERRED |
| R1.10 | Gate 17 — UI / partitions deferred |
| Permissions | `config/security.php`, `AttendanceAuthorizationTest` |
| Legacy | `AttendanceBatchService`, `QuarantinedLegacyWriterGuard` |
| Summary SQL | `EloquentAttendanceWriteRepository::refreshDailySectionSummary` |
| Feature SSOT | `.cursor/architecture/features/Attendance.md` |
