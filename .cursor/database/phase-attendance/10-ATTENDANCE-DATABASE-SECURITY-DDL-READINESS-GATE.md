# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.6 — DATABASE SECURITY & DDL READINESS GATE

**Document Type:** DATABASE SECURITY + DDL READINESS AUDIT ONLY  
**Date:** 2026-09-11  
**Status:** AUDIT COMPLETE  
**Implementation Authorization:** **NOT GRANTED**  
**Live DB:** `sis` (PostgreSQL) — **VERIFIED READ-ONLY**

```text
R1.6 AUDIT: COMPLETE
R1.7 DDL/RLS IMPLEMENTATION: NOT AUTHORIZED

NO MIGRATIONS CREATED
NO DDL EXECUTED
NO APPLICATION / HTTP / PERMISSION CHANGES
```

---

## 1. Executive Summary

Attendance has a **usable physical schema** (`timetable.periods`, `attendance.sessions`, partitioned `attendance.records`, `attendance.daily_section_summary`) aligned with R1.4 CQRS and R1.5 HTTP. Application isolation via `SchoolContext` + section→class ownership (ATT-D5) is implemented.

Database defense-in-depth is **incomplete**:

| Object | LIVE RLS | LIVE FORCE | Policy |
|--------|----------|------------|--------|
| `attendance.records` | **ENABLED** | **false** | fail-closed school_id ALL (WITH CHECK null) |
| `attendance.sessions` | **disabled** | false | **none** |
| `attendance.daily_section_summary` | **disabled** | false | **none** |
| `timetable.periods` | disabled | false | none |
| `attendance.records_default` (partition) | catalog `rls=false` | false | inherits access via parent |

**Authoritative session school** remains **derived** (`session → section → class.school_id`). Records and summaries already carry denormalized `school_id`. There is **no human-locked decision** for R1.7 on whether sessions should gain `school_id` (Strategy A) or use join policies (Strategy B).

```text
FINAL VERDICT: READY WITH CONDITIONS

R1.7 may be authorized only after the conditions in §26 are accepted/locked.
A READY-WITH-CONDITIONS verdict does NOT authorize implementation.
```

---

## 2. Scope Lock

| Allowed in R1.6 | Forbidden (confirmed not done) |
|-----------------|--------------------------------|
| Read docs / code / migrations | Migrations / DDL / RLS enable |
| Read-only live catalog | `sessions.school_id` addition |
| Write this gate only | CQRS / HTTP / permissions / UI |
| Report defects | Fixes, “quick” patches, Reopen/Cancel |

Probe script used for catalog inspection was **deleted** after use; no durable code changes from R1.6.

---

## 3. Evidence Sources

| Source | Role |
|--------|------|
| LIVE `sis` PostgreSQL catalog (`pg_class`, `pg_policies`, `information_schema`, `pg_indexes`, `pg_inherits`) | Authoritative physical state |
| `database/migrations/2026_09_05_100800_create_attendance_tables.php` | Code schema |
| `database/migrations/2026_09_05_100900_enable_row_level_security.php` | Initial RLS |
| `database/migrations/2026_09_07_120100_fix_rls_fail_closed.php` | Fail-closed records policy |
| Peer: `phase3a` exams RLS + FORCE; `phase3b` student_grades RLS + FORCE + WITH CHECK | Project convention |
| `.cursor/architecture/database-blueprint.md` | Documented model |
| `.cursor/architecture/rls-policies.md` | Doc RLS intent (partially stale vs LIVE) |
| Design Lock 02 (ATT-D1–D6, ATT-SEC-*) | Ownership + deferred security |
| R1.4 write/read repositories + Gate 07 | CQRS → table mapping |
| R1.5 Gate 09 | HTTP surface (unchanged) |
| `AttendanceBatchService` | Legacy parallel writer |

---

## 4. Actual Attendance Database Inventory

### LIVE objects (`sis`)

| Object | Schema | Exists in Code | Exists in DB | Intended Purpose | Ownership Path | RLS Needed | Status |
|--------|--------|---------------:|-------------:|------------------|----------------|-----------:|--------|
| `sessions` | attendance | YES | YES | Session lifecycle aggregate | section→class→school | **YES** | **NO RLS** |
| `records` | attendance | YES | YES (partitioned parent) | Mark/correct facts | **direct `school_id`** + session | YES | ENABLE, **no FORCE** |
| `records_default` | attendance | YES (migration) | YES | DEFAULT LIST partition | same as records | YES (via parent) | child `relrowsecurity=false` |
| `daily_section_summary` | attendance | YES | YES | Dashboard projection | **direct `school_id`** | **YES** (doc + risk) | **NO RLS** |
| `periods` | timetable | YES | YES | Optional period FK | **direct `school_id`** | optional / school-scoped | NO RLS |
| Views / MVs | — | NO | NO | — | — | — | N/A |
| Attendance triggers/functions | — | NO | NO | — | — | — | N/A |

**Row counts (LIVE):** sessions=0, records=0, daily_section_summary=0, periods=0.

**Authoritative Attendance tables for R1.7:** `attendance.sessions`, `attendance.records` (+ year partitions), `attendance.daily_section_summary`. `timetable.periods` is supporting, not Attendance aggregate.

---

## 5. Documentation vs Code vs DB Drift

| Area | Documentation | Application Code | Live DB | Verdict |
|------|---------------|------------------|---------|---------|
| Table set (3 + periods) | Blueprint matches | Migration matches | Matches | **ALIGNED** |
| `sessions.school_id` | Blueprint: **absent**; Design Lock: derived; ATT-SEC-004 deferred | Resolve via join | **Absent** | **ALIGNED (deferred DDL)** |
| `records.school_id` | Denorm for RLS | Written by CQRS | Present NOT NULL | **ALIGNED** |
| Summary name | Blueprint `daily_section_summary` | Same | Same | **ALIGNED** (not `daily_summaries`) |
| Records UNIQUE | Blueprint often `UNIQUE(session_id, student_id)` | PG upsert keys include `academic_year_id` | `UNIQUE(session_id, student_id, academic_year_id)` | **DOC ≠ DB** (partition-aware unique) |
| Records PK | Blueprint composite `(id, academic_year_id)` | Assumed | LIVE matches | **ALIGNED** |
| Partitioning | P0 LIST by year; year partitions | DEFAULT only in migration | **Only `records_default`** | **CODE/DB < DOC intent** (year partitions not created) |
| RLS on records | Doc wants school isolation | App also filters school_id | ENABLE + fail-closed | **PARTIAL** |
| FORCE RLS records | Exams convention FORCE; Enrollment NOT FORCE | — | **false** | **Convention conflict (module-dependent)** |
| RLS sessions | Gate 01 / Design Lock deferred | App join filter | **None** | **DOC/APP > DB** |
| RLS summary | `rls-policies.md` lists summary | App filters school_id | **None** | **DOC > DB** |
| Status CHECK | Design Lock deferred | Domain VO 1/2/3 | **No CHECK** | **APP > DB** |
| Session natural UNIQUE | Deferred optional | App `findDuplicateOpenSession` soft | **No UNIQUE** | **APP > DB** |
| Teacher section RLS in `rls-policies.md` | Example policy | Not implemented; permissions differ | Absent | **DOC speculative / stale** |

---

## 6. Ownership / School Isolation Analysis

### Authoritative path (LOCKED ATT-D5)

```text
attendance.sessions
    → section_id → enrollment.sections
    → class_id   → enrollment.classes.school_id   ★ authoritative for sessions
```

| Question | Evidence | Answer |
|----------|----------|--------|
| Does Session contain `school_id`? | LIVE columns | **NO** |
| Is it authoritative if added later? | Would be **denormalized stamp** at create (exams pattern) | Only if human locks ATT-SEC-004 as denorm SSOT for RLS |
| Section school? | sections have `class_id` only | School via class |
| Can Class move schools? | `classes.school_id` updatable (no immutability trigger) | **YES at DB** — risk for Strategy B historical ownership |
| Can Section move class? | `sections.class_id` FK, updatable | **YES at DB** — same risk |
| Session outlive section? | FK `ON DELETE RESTRICT` | Section delete blocked while sessions exist |
| Records without Session join? | `records.school_id` + session_id FK | **YES** — direct school column |
| Summaries without Session? | `daily_section_summary.school_id` | **YES** — direct school column |
| Deterministic ownership every object? | sessions: join; records/summary: column | **Partial** — sessions lack direct column |

### Critical rule applied

Do **not** add `school_id` “everywhere.” Records + summary already have it. The open decision is **sessions only** (and whether RLS for sessions uses that column vs joins).

---

## 7. RLS Readiness

### LIVE Attendance

| Table | ENABLE | FORCE | SELECT | INSERT | UPDATE | DELETE | Notes |
|-------|--------|-------|--------|--------|--------|--------|-------|
| records | YES | NO | via ALL | via ALL | via ALL | via ALL | `with_check` **null** (PG defaults WITH CHECK to USING for ALL) |
| sessions | NO | NO | open | open | open | open | App-only isolation |
| daily_section_summary | NO | NO | open | open | open | open | App-only isolation |
| periods | NO | NO | open | open | open | open | Has school_id |

### Peer conventions (LIVE)

| Module | ENABLE | FORCE | Predicate style |
|--------|--------|-------|-----------------|
| `enrollment.enrollments` | YES | **NO** | direct `school_id` fail-closed |
| `exams.exams` / `exam_sessions` / `exam_enrollments` | YES | **YES** | direct `school_id` |
| `exams.student_grades` | YES | **YES** | direct `school_id` + **explicit WITH CHECK** |

**Project trend for high-sensitivity academic facts:** ENABLE + **FORCE** + direct `school_id` (Exams/Grades). Enrollment remains ENABLE-without-FORCE (known softer baseline).

**Attendance records today match Enrollment softness, not Exams hardness** — despite Attendance being high-volume academic evidence.

---

## 8. FORCE RLS Analysis

| Factor | Finding |
|--------|---------|
| Application DB role | Typically table owner / privileged → **ENABLE-only RLS is bypassable** by owner |
| Migrations / seeds | Need documented bypass (superuser / temporary DISABLE) — peer pattern exists |
| Background jobs / CLI | Must set `app.current_school_id` or use BYPASSRLS intentionally |
| Reporting | Same GUC requirement |
| Testing | RLS tests require non-owner / FORCE to be meaningful |

**Recommendation (audit only):** FORCE RLS is **mandatory** for R1.7 on:

- `attendance.records` (and partitions)
- `attendance.sessions` (once RLS strategy locked)
- `attendance.daily_section_summary`

Rationale: R1.5 exposes HTTP; owner bypass on ENABLE-only is a real tenant leak path; Exams already set the stricter precedent for academic write tables. Do **not** copy Enrollment’s softer FORCE posture for Attendance.

---

## 9. INSERT / UPDATE / DELETE Security (intended DB behavior)

| Table | INSERT | UPDATE | DELETE | Reason |
|-------|--------|--------|--------|--------|
| Sessions | Allow under school policy | Allow status OPEN→CLOSED (and reserved CANCELLED later) | **Deny / no casual DELETE** | Academic session history; FK RESTRICT from records |
| Records | Allow under school + OPEN session (app) | Allow correction UPDATE (ATT-D2) | **Deny hard DELETE** | Historical evidence; Constitution soft-delete ethos |
| Summaries | Allow upsert refresh | Allow refresh UPDATE | Soft allow / prefer upsert-only | Derived; not source of truth |

Correction today = **in-place UPDATE** + outbox `AttendanceCorrected` (no record_versions). DB must allow UPDATE under RLS; DELETE should remain unavailable to app roles (REVOKE or `USING (false)` policy) — **recommend in R1.7**, do not implement here.

---

## 10. Constraint Audit

### Sessions

| Concern | DB today | R1.4 need | R1.7 candidate |
|---------|----------|-----------|----------------|
| Valid section / year / teacher / subject | FKs RESTRICT | Yes | Keep |
| Valid status ∈ {1,2,3} | **No CHECK** | Domain VO | CHECK (P1/P2) |
| Duplicate open session | **No UNIQUE** | Soft app lookup | Partial UNIQUE or exclusion (P2) — natural key `(section_id, subject_id, session_date, period_id)` with NULLS NOT DISTINCT if PG15+ |
| Date in academic year | **No CHECK** | App validates | Optional; year bounds live on academic_years |

### Records

| Concern | DB today | R1.4 need | R1.7 candidate |
|---------|----------|-----------|----------------|
| Unique student/session | **UNIQUE (session_id, student_id, academic_year_id)** | Yes | Keep |
| Status ∈ {1,2,3} | **No CHECK** | Domain | CHECK |
| Enrollment ATT-D4 window | **Not in DB** | App specification | Keep app-authoritative (trigger = high complexity P2+) |
| school_id consistent with session school | **No composite FK** | App stamps resolved school | Optional consistency trigger/composite after sessions.school_id (if Strategy A) |

### Summaries

| Concern | DB today | Notes |
|---------|----------|-------|
| Uniqueness | PK `(section_id, attendance_date)` | OK |
| Derived | Refresh from records joined sessions; uses `r.attendance_date` (CQRS sets from **session.session_date**) | OK if writers use session date — **legacy writer uses `today()`** (see §18) |

---

## 11. FK Audit

| Relationship | Exists | ON DELETE | Nullable | Cross-schema | Historical note |
|--------------|--------|-----------|----------|--------------|-----------------|
| sessions.section_id → sections | YES | RESTRICT | NO | YES | OK |
| sessions.subject_id → subjects | YES | RESTRICT | NO | YES | OK |
| sessions.academic_year_id → academic_years | YES | RESTRICT | NO | YES | OK |
| sessions.period_id → periods | YES | SET NULL | YES | YES | OK |
| sessions.teacher_id → teachers | YES | RESTRICT | NO | YES | OK |
| records.session_id → sessions | YES | RESTRICT | NO | same | OK |
| records.student_id → students | YES | RESTRICT | NO | YES | OK |
| records.enrollment_id → enrollments | YES | RESTRICT | NO | YES | OK |
| records.academic_year_id → years | YES | RESTRICT | NO | YES | Partition key |
| records.school_id → schools | YES | RESTRICT | NO | YES | Denorm RLS |
| records.recorded_by → users | YES | SET NULL | YES | public | OK |
| summary.section_id / school_id / year | YES | RESTRICT | NO | YES | OK |
| Composite session↔school consistency | **NO** | — | — | — | Only if Strategy A |

---

## 12. Uniqueness / Concurrency Audit

| Invariant | Application | Database | Concurrent-writer safe? |
|-----------|-------------|----------|-------------------------|
| One record per student per session (per year partition) | Upsert conflict target | **UNIQUE index LIVE** | **YES** |
| Duplicate open sessions | Soft find | **None** | **NO** — race can create duplicates |
| Summary one row per section/date | upsert ON CONFLICT | **PK LIVE** | **YES** |
| Close OPEN→CLOSED | `UPDATE … WHERE status=OPEN` optimistic | No status machine | **YES** for single row close; no DB forbid illegal status values |
| Corrections | UPDATE by id+year+school | No row version column | Last-write-wins; audit via outbox |

---

## 13. Index Readiness

Existing indexes largely match CQRS filters. Candidates below are **recommendations only** (no creation).

| Query | Filter/Join | Candidate Index | Selectivity | Expected Benefit | Risk |
|-------|-------------|-----------------|-------------|------------------|------|
| GetAttendanceSession | sess.id + class.school_id join | PK sess.id sufficient; school via join | High | Already OK | Low |
| ListAttendanceSessions | year + optional section/date/status + school join | `(academic_year_id, session_date)` LIVE; optional `(section_id, academic_year_id, session_date)` | Med | Moderate list speedup | Low–Med write cost |
| GetSectionAttendance | section + date + year | `(section_id, session_date)` LIVE | High | Already OK | Low |
| GetStudentAttendance | school + student + year + date range | `(student_id, attendance_date)` + year filter; school_id filter | High | Already strong | Low |
| GetDailySectionSummary | school + section + date | PK + `(school_id, attendance_date)` LIVE | High | Already OK | Low |
| RLS on records | school_id | `(school_id, attendance_date)` LIVE | High | Required for RLS | Low |
| RLS on sessions (if Strategy A) | school_id | **Need `school_id` column first** then BTREE(school_id) | High | RLS feasibility | Depends on DDL |
| RLS on sessions (Strategy B) | join section/class | Index on sections.class_id LIVE; classes.school_id indexed | Med | Multi-join per row | Perf risk at scale |

**Do not** index every FK blindly. No EXPLAIN evidence on LIVE (empty tables) — R1.7 index adds beyond RLS columns should follow PERFORMANCE-BUDGET / adaptive governance.

---

## 14. Partitioning Readiness

| Object | Status | Verdict |
|--------|--------|---------|
| `attendance.records` | LIST `(academic_year_id)` parent + **DEFAULT only** | Partition **framework exists**; **year partitions Required later / Recommended in R1.7 ops**, not blocking RLS design |
| `attendance.sessions` | Unpartitioned | **Not justified now** (far smaller than records) |
| `daily_section_summary` | Unpartitioned | **Not justified now** (section×day scale) |

```text
Required now (for R1.7 security): NO new partition design
Recommended later: per-year LIST partitions + detach/archive strategy
Not justified: partitioning sessions/summary in R1.7
```

Empty LIVE data → no measured vacuum/index pressure yet. Constitution still treats records partition as P0 baseline — **operational year partitions remain a capacity gap**, classified **P2** relative to RLS readiness.

---

## 15. Academic Year / Date Model

| Object | academic_year_id | session/attendance date | Supports GetStudentAttendance year constraint? |
|--------|------------------|-------------------------|-----------------------------------------------|
| sessions | YES NOT NULL | `session_date` | Via join / list filters |
| records | YES NOT NULL + partition key | `attendance_date` (CQRS from session_date) | **YES** — direct filter |
| summary | YES NOT NULL | `attendance_date` | YES |

HTTP/Application already require `academic_year_id` for student attendance — DB supports efficient year-scoped reads.

---

## 16. Summary / Derived Data Audit

| Topic | Finding |
|-------|---------|
| Authoritative source | `attendance.records` (+ session for section scope) |
| Refresh | Synchronous in Mark/Correct UoW (`refreshDailySectionSummary`) |
| Date basis (CQRS) | Record `attendance_date` = **session.session_date** |
| Legacy writer | Uses **`today()`** — can desync summary/session date (**application dual-path risk**, not fixed here) |
| School isolation | Column present; **no RLS** |
| Staleness | In-tx refresh; no async lag if CQRS path used |

---

## 17. CQRS → DB Mapping

| Command | Tables Touched | Required DB Constraints | RLS Requirement | Transaction Requirement |
|---------|----------------|-------------------------|-----------------|-------------------------|
| CreateAttendanceSession | sessions (+ outbox) | FKs; optional natural UNIQUE; status CHECK | sessions school policy | UoW + optional idempotency after success |
| MarkSectionAttendance | records upsert; summary upsert; outbox | UNIQUE student/session/year; status CHECK; school_id = context | records + summary (+ session read) | Single UoW; batch ≤500 |
| CorrectAttendanceRecord | records UPDATE; summary refresh; outbox | school_id match; row exists | records + summary | UoW; required idempotency |
| CloseAttendanceSession | sessions status UPDATE; outbox | optimistic OPEN filter | sessions policy | UoW; optional idempotency |

### Create / Mark / Correct / Close (verification)

- **Create:** school via section→class; year date bounds app; uniqueness soft.  
- **Mark:** OPEN check app; ATT-D4 app; unique DB; atomic chunk upsert.  
- **Correct:** school on find/update; UPDATE model + outbox.  
- **Close:** optimistic UPDATE; conflict → 409 via Application.

---

## 18. RLS + CQRS Transaction Analysis

| Concern | Risk |
|---------|------|
| Multi-table Mark (records + summary) | Both need same `app.current_school_id`; summary currently **unprotected** → leak/write cross-school if GUC wrong and app bug |
| Indirect session ownership | Session SELECT without RLS can leak IDs/metadata even if records blocked |
| Outbox / idempotency tables | Outside Attendance RLS; assume shared infra conventions — **no Attendance-specific finding** |
| Workers without GUC | Fail-closed on records policy if setting empty; sessions/summary still fully visible |
| Strategy B join policies | Extra joins inside Mark validation paths — latency under FORCE |

Compatibility: CQRS can run under FORCE RLS **if** middleware sets GUC (already done for SchoolContext) and policies use direct `school_id` where stamped.

---

## 19. Legacy Writer Analysis

`App\Services\Attendance\AttendanceBatchService::recordSectionAttendance`

| Aspect | Finding |
|--------|---------|
| Tables | Same `attendance.records` + `daily_section_summary` |
| Session date | **`today()`** vs CQRS **session.session_date** — **semantic conflict** |
| Authz / ATT-D4 / OPEN check | **Bypasses** Application CQRS |
| RLS | Same records policy if GUC set; still writes summary without RLS |
| Callable | Yes (legacy-allowed annotation) |
| DB implication | Dual write path undermines “one business model”; RLS alone does not fix date semantics |

**Do not remove in R1.6/R1.7 unless separately authorized.** Report as residual dual-path risk.

---

## 20. Existing SIS Convention Comparison

| Convention | Attendance | Verdict |
|------------|------------|---------|
| Direct `school_id` for RLS | records + summary YES; sessions NO | **Missing on sessions** |
| ENABLE RLS | records YES | **Partial** |
| FORCE RLS (Exams-style) | records NO | **Missing / conflict with Exams** |
| Fail-closed GUC | records YES | **Followed** |
| Explicit WITH CHECK (grades) | records null | **Missing (soft)** |
| Partition high-growth facts | records YES | **Followed (DEFAULT only)** |
| No hard-delete academic | No DELETE in CQRS; DB allows DELETE | **App followed; DB soft** |
| Denorm for RLS (exams Phase 3A) | Precedent for Strategy A | **Applicable pattern** |

---

## 21. Security Threat Model

| Threat | Current Protection | Database Protection Needed | Residual Risk |
|--------|--------------------|----------------------------|---------------|
| T1 School A reads School B session | App join filter (R1.4/R1.5) | RLS on sessions | **Med** — DB open |
| T2 School A reads School B record | App filter + ENABLE RLS | **FORCE** RLS + WITH CHECK | **Med** — owner bypass |
| T3 School A reads School B summary | App filter only | RLS + FORCE on summary | **Med–High** |
| T4 Owner/app role bypasses RLS | ENABLE without FORCE | FORCE RLS | **High today** |
| T5 Concurrent duplicate records | UNIQUE index | Already OK | **Low** |
| T6 Correct other school record | App school on update + RLS USING | FORCE + WITH CHECK | **Med** |
| T7 Section/class school mutation changes session ownership | App resolves live class.school_id | Strategy A stamp **or** immutability rules on class/section | **Med** under Strategy B |
| T8 Summary cross-school | No RLS | RLS on summary | **Med–High** |
| T9 Legacy writer bypass | Not removed | RLS still applies to records if GUC set; date bug remains | **Med** (integrity) |

---

## 22. Findings (concise)

1. LIVE schema matches migration for Attendance core tables.  
2. Records RLS ENABLE fail-closed; **FORCE missing**.  
3. Sessions + daily_section_summary **unprotected at DB**.  
4. ATT-SEC-004 (`sessions.school_id` vs join RLS) **unresolved** — blocks precise R1.7 migration design.  
5. Year partitions beyond DEFAULT **not created** (capacity P2).  
6. No status CHECKs; no session natural UNIQUE.  
7. Legacy writer date semantics conflict with CQRS.  
8. `rls-policies.md` contains stale/speculative teacher policies ≠ LIVE.  
9. Empty data — no measured index/EXPLAIN evidence.

---

## 23. P0 / P1 / P2 / P3 Classification

### P0 — Critical (none that block *design* of R1.7 after human locks)

No P0 “schema missing” — tables exist. Tenant leak via unprotected sessions/summary is **severe** but currently mitigated by Application; classified **P1 for R1.7 authorization** (same as Gate 01 ATT-004/005), not an audit blocker that says “schema unusable.”

### P1 — Required before / in R1.7 DDL+RLS

| ID | Item |
|----|------|
| R16-P1-01 | **Human lock ownership strategy for `attendance.sessions` RLS** (A denorm `school_id` vs B join vs C helper) |
| R16-P1-02 | ENABLE + **FORCE** RLS on `attendance.records` (+ verify partition behavior) |
| R16-P1-03 | ENABLE + **FORCE** RLS + policies on `attendance.sessions` (per locked strategy) |
| R16-P1-04 | ENABLE + **FORCE** RLS + policies on `attendance.daily_section_summary` |
| R16-P1-05 | Explicit **WITH CHECK** on write policies (peer grades) |
| R16-P1-06 | R1.7 implementation authorization phrase + scope lock (which CHECKs/UNIQUEs in/out) |

### P2 — Important, deferrable behind P1

| ID | Item |
|----|------|
| R16-P2-01 | CHECK constraints on session/record status |
| R16-P2-02 | Natural UNIQUE / anti-duplicate for sessions |
| R16-P2-03 | Per-year LIST partitions (beyond DEFAULT) |
| R16-P2-04 | Deny DELETE policies / REVOKE DELETE |
| R16-P2-05 | Legacy writer retirement or date alignment (app; separate auth) |
| R16-P2-06 | Composite consistency session.school ↔ records.school (if Strategy A) |

### P3 — Optimization / docs

| ID | Item |
|----|------|
| R16-P3-01 | Sync `rls-policies.md` / blueprint UNIQUE wording |
| R16-P3-02 | Extra list indexes after EXPLAIN evidence |
| R16-P3-03 | Partition sessions/summary — not justified |

---

## 24. Readiness Matrix

| Area | Ready? | Note |
|------|--------|------|
| Physical tables | YES | LIVE verified |
| CQRS compatibility | YES | R1.4 PASS |
| HTTP boundary | YES | R1.5 PASS — do not modify |
| Records RLS baseline | PARTIAL | ENABLE yes; FORCE no |
| Sessions/summary RLS | NO | Missing |
| Ownership strategy lock | NO | Human decision required |
| Constraint hardening | PARTIAL | Uniques on records OK; CHECKs missing |
| Partition ops | PARTIAL | Parent exists; year parts missing |
| R1.7 implementable after conditions | YES | With §26 |

---

## 25. Score

| Area | /10 |
|------|----:|
| Domain/schema correctness | 8 |
| School isolation (app+db) | 5 |
| RLS readiness | 4 |
| Constraint integrity | 6 |
| FK integrity | 9 |
| Uniqueness/concurrency | 7 |
| Index readiness | 8 |
| Partitioning readiness | 6 |
| CQRS/database compatibility | 8 |
| Long-term scalability | 7 |
| **Overall** | **/68** |

**Explanation:** Strong FKs, records unique, partition parent, and CQRS alignment raise the floor. Incomplete RLS/FORCE and unresolved sessions ownership strategy pull security/RLS scores down. Score **does not override** P1 conditions — overall 68 with **READY WITH CONDITIONS**.

---

## 26. Final R1.6 Gate

```text
R1.6 DATABASE SECURITY & DDL READINESS GATE: READY WITH CONDITIONS
```

### Exact Conditions for R1.7

Before any R1.7 implementation authorization:

1. **Human locks ATT-SEC-004 strategy** for `attendance.sessions`:
   - **A — Denormalized `sessions.school_id`** (stamp at create; RLS direct; exams precedent; protects against class/section school mutation rewriting history), **or**
   - **B — Indirect join RLS** (no column; accept multi-join + mutation risk / immutability rules), **or**
   - **C — SECURITY DEFINER helper** (centralize predicate; still need clear ownership source).
2. Human accepts **FORCE RLS** on records + sessions + daily_section_summary as mandatory for R1.7.
3. R1.7 scope document lists in/out: status CHECKs, session UNIQUE, year partitions, DELETE deny, periods RLS (optional).
4. Explicit phrase required, e.g.:

```text
APPROVED — IMPLEMENT ATTENDANCE R1.7 DDL + RLS ONLY
```

(with strategy A/B/C named in the approval).

### Audit recommendation (non-binding)

**Prefer Strategy A** for sessions — justified by integrity (historical ownership), RLS feasibility, consistency with existing `records.school_id` / `summary.school_id`, and Exams Phase 3A precedent — **not** mere convenience. Strategy B remains acceptable if human rejects denorm and accepts join cost + class/section mutation controls.

---

## 27. Explicit STOP

```text
R1.6 AUDIT: COMPLETE
R1.7 DDL/RLS IMPLEMENTATION: NOT AUTHORIZED

STOP.

Do not:
  - create migrations
  - enable/force RLS
  - add sessions.school_id
  - add indexes/partitions/constraints
  - change CQRS / HTTP / permissions / UI
  - remove AttendanceBatchService
  - start Reopen/Cancel
  - touch Certificates / Graduation / Enrollment schema
```

**Next action:** Human review of this gate → optional Strategy lock → separate R1.7 authorization phrase.

---

## SIS CHANGE REPORT (R1.6)

```text
Status: PASS (audit deliverable)
Risk: N/A (no mutation)

Summary: Readiness audit READY WITH CONDITIONS; LIVE sis catalog verified.

Application Code Modified: NO
Database Modified: NO
API Modified: NO
UI Modified: NO
Dependencies Modified: NO
Governance Modified: YES (this gate only)

Files Added:
  .cursor/database/phase-attendance/10-ATTENDANCE-DATABASE-SECURITY-DDL-READINESS-GATE.md

Files Modified: NONE (application/database)
Files Deleted: temporary probe only (storage), not product code

Human Approval Required: YES — for R1.7 + ownership strategy
Final Gate Status: READY WITH CONDITIONS
```
