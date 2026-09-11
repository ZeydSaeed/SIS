# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.8A — INTEGRITY HARDENING READINESS GATE

**Document Type:** AUDIT + DESIGN ONLY  
**Date:** 2026-09-11  
**Implementation Authorization:** **NOT GRANTED**

**Upstream:** R1.3–R1.8 · **R1.7 Strategy A LOCKED** · Lifecycle OPEN→CLOSED (R1.8)

```text
NO MIGRATIONS · NO DDL · NO APPLICATION CHANGES
Reopen / Cancel / year partitions / legacy retirement: OUT OF SCOPE
```

---

## 1. Executive Verdict

```text
STATUS: READY WITH CONDITIONS

A narrowly scoped R1.8A *implementation* phase IS JUSTIFIED for:
  (1) session status vocabulary CHECK, and
  (2) concurrent-safe prevention of duplicate OPEN sessions,

but ONLY after humans lock the exact natural key and CLOSED-recreation policy.

This audit does NOT authorize implementation.
```

**Score: 74 / 100**

| Area | /10 |
|------|----:|
| Status vocabulary clarity | 9 |
| Status transition clarity | 9 |
| Status DB enforcement gap | 4 |
| Duplicate helper design quality | 7 |
| Duplicate helper usage | 2 |
| Natural-key evidence | 8 |
| Concurrency honesty | 8 |
| RLS / Strategy A compatibility | 9 |
| Test coverage for integrity | 3 |
| Scope discipline | 9 |

---

## 2. Strategy A / RLS Preservation

```text
ATT-SEC-004 Strategy A REMAINS AUTHORITATIVE.
sessions.school_id = historical ownership snapshot.
ENABLE + FORCE RLS + fail-closed + WITH CHECK — DO NOT REDESIGN.
```

Any future UNIQUE/CHECK on `attendance.sessions` must use stamped `school_id` only as a column already present — **not** replace RLS with section→class joins.

Records partitioning (`records` / `records_default`) is **unaffected** by session-level UNIQUE/CHECK.

---

## 3. Status Vocabulary Audit

### Authoritative values (verified)

| Value | Name | Evidence |
|------:|------|----------|
| 1 | OPEN | `SessionStatus::Open`; Create always inserts `1`; DB default `1` |
| 2 | CLOSED | `SessionStatus::Closed`; `closeSessionIfOpen` sets `2` |
| 3 | CANCELLED | `SessionStatus::Cancelled`; Correct rejects; **no writer sets 3** |

Blueprint / Design Lock ATT-D1 / Domain VO / Gate 12: **aligned**. No fourth value in code.

### Entry paths

| Path | Can set session status? | Invalid values? |
|------|-------------------------|-----------------|
| `CreateAttendanceSessionHandler` | Always OPEN | No (hardcoded VO) |
| `CloseAttendanceSessionHandler` | OPEN→CLOSED only | No |
| HTTP Create/Close FormRequests | `status` **prohibited** | No via client |
| Mark / Correct | Do not update session status | N/A |
| `AttendanceBatchService` | Writes **records** only — **does not create/update sessions** | N/A for session status |
| Raw SQL / DBA / tests | Direct INSERT/UPDATE | **Yes** — SMALLINT unconstrained |

### Recommendation

```text
STATUS CHECK: RECOMMENDED
```

Logical invariant only (not implemented):

```text
attendance.sessions.status ∈ {1, 2, 3}
```

Not REQUIRED for security (FORCE RLS unrelated); justified as defense-in-depth against raw/legacy/admin mistakes.

---

## 4. Status VALUES vs TRANSITIONS

| Concern | Owner | Mechanism |
|---------|-------|-----------|
| Allowed **values** | Database CHECK *(proposed)* | Vocabulary only |
| Allowed **transitions** | Application / CQRS | OPEN→CLOSED today; Mark OPEN-only; Correct ¬CANCELLED |

Do **not** encode transition matrix in PostgreSQL triggers for R1.8A. Lifecycle remains Application-authoritative (Architecture Stack).

Current lifecycle (unchanged):

```text
Create → OPEN
OPEN → CLOSED (Close)
Mark → OPEN only
Correct → OPEN or CLOSED (not CANCELLED)
```

---

## 5. `findDuplicateOpenSession` Audit

### Signature / implementation

```text
findDuplicateOpenSession(sectionId, subjectId, sessionDate, periodId?): ?int
```

| Filter | Behavior |
|--------|----------|
| `section_id` | Exact |
| `subject_id` | Exact |
| `session_date` | Exact |
| `status` | **OPEN only** |
| `period_id` | `WHERE period_id = ?` or `WHERE period_id IS NULL` |
| `school_id` | **Not used** |
| `academic_year_id` | **Not used** |
| `teacher_id` | **Not used** |

### Reachability

| Consumer | Calls it? |
|----------|-----------|
| `CreateAttendanceSessionHandler` | **NO** |
| Other handlers | **NO** |
| HTTP | **NO** |
| Legacy batch | **NO** |

**Dead soft guard** — implemented, never wired.

### Concurrency

Application lookup-then-insert **cannot** guarantee uniqueness under concurrent Creates. Two requests can both observe “no OPEN duplicate” and both insert. Safe enforcement requires a **database unique constraint / unique index** (conceptually: partial unique index on OPEN rows, or unique with NULLS NOT DISTINCT for period).

---

## 6. True Natural Key Analysis

### Field include/exclude

| Field | Include? | Why |
|-------|----------|-----|
| `section_id` | **Yes** | Session is section-scoped; marking roster is section-based |
| `subject_id` | **Yes** | Same section can have Math vs Arabic same day |
| `session_date` | **Yes** | Attendance is day-scoped |
| `period_id` | **Yes (nullable)** | Same day/subject can have period 1 vs period 2; Create allows null |
| `status = OPEN` | **Yes (for duplicate rule)** | Existing helper and ATT-D1 imply “one active marking context”; CLOSED history may remain |
| `school_id` | **Optional in key** | Redundant with section→class uniqueness, but Strategy A column exists; including it aids clarity under RLS and matches stamped ownership — **recommend include for DB index** |
| `academic_year_id` | **Optional** | Create already requires date ∈ year; section/date usually imply year. Including prevents cross-year clone bugs — **SHOULD include** |
| `teacher_id` | **No** | Identity is the teaching slot (section/subject/date/period), not who teaches; two OPEN rows for same slot with different teachers would be operational conflict |

### Questions A–E

| Q | Answer | Evidence |
|---|--------|----------|
| **A.** Two legitimate OPEN sessions same school+section+subject+date+period? | **No** (intended) | Helper exists to find that duplicate; Design Lock one session context per slot |
| **B.** Same section/subject/date, different periods? | **Yes** | `period_id` in Create + helper |
| **C.** Can `period_id` be NULL? | **Yes** | Nullable FK; Create optional |
| **D.** NULL meaning? | **“No scheduled period” / unscheduled or whole-block session** — a **distinct identity** from any non-null period | Helper uses `whereNull('period_id')` as its own bucket |
| **E.** Recreate after CLOSED with same natural identity? | **Allowed by current helper design** (OPEN-only filter) | Supports close-then-create-new without Reopen; all-status UNIQUE would **block** unless Reopen ships |

### Recommended invariant (proposal only — needs human lock)

```text
At most one OPEN session per:
  (school_id, academic_year_id, section_id, subject_id, session_date, period_id)
with NULL period_id treated as a real key value (NULLS NOT DISTINCT / equivalent).
```

```text
NOT: at most one session of any status for that key
(unless human explicitly forbids CLOSED+new OPEN and adopts Reopen instead)
```

Alignment: matches existing `findDuplicateOpenSession` semantics + Strategy A `school_id` + year.

---

## 7. Concurrency Analysis

```text
Request A: SELECT duplicate → none → INSERT OPEN
Request B: SELECT duplicate → none → INSERT OPEN
→ two OPEN rows (TOCTOU)
```

App-only wiring of `findDuplicateOpenSession` **reduces** but does **not eliminate** races.

Safest conceptual PG mechanism (not implemented):

```text
Partial unique index WHERE status = 1
  ON (school_id, academic_year_id, section_id, subject_id, session_date, period_id)
  NULLS NOT DISTINCT (PG15+)
```

Alternative: unique constraint on all statuses — **rejected by default** pending decision E.

---

## 8. Partition / RLS Compatibility

| Concern | Compatibility |
|---------|---------------|
| `attendance.records` LIST partitions | Unaffected (session table unpartitioned) |
| Strategy A `sessions.school_id` | Compatible; prefer include in unique key |
| FORCE RLS | UNIQUE evaluated within writer’s visible rows under GUC; still need global uniqueness for integrity — unique index is table-level and applies regardless of RLS visibility for the inserting role when FORCE applies to owner too |
| Speculative indexes beyond uniqueness | **Out of scope** |

---

## 9. Legacy Writer

```text
Can AttendanceBatchService create duplicate session rows?
→ NO — it only upserts attendance.records (+ summary).
```

| Classification | |
|----------------|--|
| R1.8A blocker | **No** |
| Legacy technical debt | Date/`today()` vs CQRS; no OPEN check on marks — **separate** from session uniqueness |

---

## 10. Test Coverage Audit (gaps only)

### Status

| Case | Covered today? |
|------|----------------|
| Create → OPEN | Indirect (CQRS/HTTP) |
| Close → CLOSED | Yes (unit/PG/HTTP) |
| Invalid session status insert | **No** |
| CHECK rejection | **N/A** (no CHECK) |

### Duplicate sessions

| Case | Covered today? |
|------|----------------|
| Sequential duplicate OPEN | **No** |
| Concurrent duplicate OPEN | **No** |
| Same key CLOSED then new OPEN | **No** |
| Different period | **No** |
| NULL vs non-null period | **No** |
| Different school | Cross-school create tests exist; not uniqueness-focused |
| Different academic year | **No** |

Future R1.8A tests (when authorized): must cover the above.

---

## 11. Decision Matrix

| Integrity Rule | Evidence | Decision | Future Enforcement |
|----------------|----------|----------|--------------------|
| Status vocabulary {1,2,3} | Domain VO + ATT-D1 + writers | **RECOMMENDED** | DB `CHECK` |
| OPEN duplicate prevention | Dead helper + race | **RECOMMENDED → treat as MUST if R1.8A ships** | Partial UNIQUE + wire Create |
| CLOSED recreation same key | Helper OPEN-only; Reopen deferred | **ALLOW** (default) | Do **not** all-status UNIQUE unless human flips |
| NULL period distinct identity | Nullable FK + `whereNull` | **YES** | NULLS NOT DISTINCT / null-bucket |
| Cross-school distinction | Strategy A + section uniqueness | School via `school_id` in key **SHOULD** | Column already exists |
| Academic-year distinction | Create year+date bounds | Year in key **SHOULD** | Column already exists |

---

## 12. Future Implementation Scope (if later authorized)

### MUST (within a locked R1.8A implement grant)

```text
- Human-approved partial UNIQUE for OPEN sessions (exact key locked)
- Wire Create to fail closed on conflict (app + DB)
- Tests: sequential + concurrent duplicate; NULL period; different period
```

### SHOULD

```text
- CHECK (status IN (1,2,3))
- Include school_id + academic_year_id in unique key
- Document CLOSED→new OPEN as supported without Reopen
```

### DEFER

```text
- Reopen / Cancel
- Year partitions
- Legacy writer retirement
- ATT-D2-FUTURE versioning
- Transition triggers in DB
```

### OUT OF SCOPE

```text
- RLS redesign / Strategy A change
- HTTP contract redesign (beyond conflict error mapping if Create gains 409)
- UI
- New permissions
- Records UNIQUE changes (already exists)
```

---

## 13. Is a Separate R1.8A Implementation Phase Justified?

```text
YES — narrowly justified for status CHECK + OPEN-session uniqueness,
subject to human key lock.

NOT justified to bundle Reopen/Cancel or partition work.
```

Verdict code:

```text
READY WITH CONDITIONS
```

Conditions:

1. Human locks natural key (proposed key in §6).  
2. Human confirms OPEN-only uniqueness (allow CLOSED recreate).  
3. Human confirms NULL period = distinct identity.  
4. Explicit phrase required before any DDL, e.g.  
   `APPROVED — IMPLEMENT ATTENDANCE R1.8A INTEGRITY HARDENING ONLY`

---

## 14. Human Decisions Required

1. **Status CHECK** — yes / defer.  
2. **Exact duplicate natural key** — accept proposed  
   `(school_id, academic_year_id, section_id, subject_id, session_date, period_id)` OPEN-only, or amend.  
3. **OPEN-only vs all-status uniqueness** — recommend OPEN-only.  
4. **NULL period semantics** — confirm distinct identity (recommended).  
5. **Is duplicate prevention mandatory for next implement gate?** — audit recommends **yes** if R1.8A proceeds.

Do **not** treat this list as implementation authorization.

---

## 15. Final Hard Stop

```text
R1.8A READINESS AUDIT COMPLETE.

NO IMPLEMENTATION AUTHORIZATION GRANTED.

NO MIGRATIONS CREATED.

NO DDL EXECUTED.

NO APPLICATION CODE MODIFIED.

STOP.

Human authorization is required before any R1.8A implementation.
```

Do not proceed to implementation.  
Do not proceed to R1.9.  
Do not modify Reopen/Cancel decisions.  
**R1.7 Strategy A remains authoritative.**

---

## SIS CHANGE REPORT (R1.8A Audit)

```text
Status: PASS (audit deliverable)
Risk: N/A

Application / Database / API / UI Modified: NO
Governance Modified: YES (this gate only)

Files Added:
  .cursor/database/phase-attendance/13-ATTENDANCE-R1.8A-INTEGRITY-HARDENING-READINESS-GATE.md

Final Gate Status: READY WITH CONDITIONS
Separate R1.8A implementation justified: YES (narrow) — NOT AUTHORIZED
```
