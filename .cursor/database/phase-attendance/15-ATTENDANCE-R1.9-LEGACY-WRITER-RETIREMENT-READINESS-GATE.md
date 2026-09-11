# SIS DATABASE — PHASE R1 — ATTENDANCE

# R1.9 — LEGACY WRITER RETIREMENT READINESS GATE

**Document Type:** AUDIT + DESIGN ONLY  
**Date:** 2026-09-11  
**Implementation Authorization:** **NOT GRANTED**

**Upstream locks:** R1.7 Strategy A · R1.8A integrity hardening (PASS / LOW) · CQRS Mark/Correct authoritative

```text
NO CODE CHANGES · NO DELETION · NO DEPRECATION IMPLEMENTATION
NO RLS / SCHEMA / INDEX / CONSTRAINT CHANGES
Reopen / Cancel / UI / year partitions: OUT OF SCOPE
```

---

## 1. Executive Summary

```text
STATUS: READY WITH CONDITIONS

Recommended option: B — DEPRECATE + BLOCK NEW CALLERS
(then full delete only after documented conditions are met)

The legacy writer AttendanceBatchService::recordSectionAttendance has
ZERO proven in-repo production callers (routes/jobs/commands/handlers/
tests/seeders/factories). The CQRS Mark path is the live public writer.

However, the class remains loadable and callable (latent bypass), and
multiple authoritative architecture docs still prescribe the legacy
service as the attendance write path. Absence of static callers is NOT
proof of zero runtime use outside the repository.

Do NOT authorize RETIRE NOW solely on “no callers found.”
```

**Score: 78 / 100**

| Area | /10 |
|------|----:|
| Caller inventory completeness | 9 |
| Runtime reachability honesty | 8 |
| Route/command/job evidence | 9 |
| Test dependency clarity | 9 |
| Semantic difference coverage | 9 |
| Security / bypass analysis | 8 |
| Integrity / concurrency analysis | 8 |
| Doc / contract debt visibility | 7 |
| Option discipline | 8 |
| Scope discipline | 10 |

---

## 2. Current Legacy Writer State

| Attribute | Evidence | Confidence |
|-----------|----------|------------|
| Class path | `app/Services/Attendance/AttendanceBatchService.php` | **PROVEN** |
| Annotation | `@architecture-legacy-allowed migrate to Application/Attendance` | **PROVEN** |
| Public method | `recordSectionAttendance(sessionId, sectionId, academicYearId, schoolId, records, recordedBy): int` | **PROVEN** |
| Secondary method | `refreshDailySummary(...)` (public) | **PROVEN** |
| Creates sessions? | **No** — upserts `attendance.records` + refreshes summary only | **PROVEN** |
| Architecture validator | `ArchitectureValidator` skips `app/Services` files with `@architecture-legacy-allowed` | **PROVEN** |
| Design Lock §16 | CQRS is authoritative; legacy coexistence as alternate public writer **prohibited** after R1 | **PROVEN** (design doc) |
| Feature contract | `features/Attendance.md`: “Do not call legacy …” | **PROVEN** |

### What the legacy writer does (behavior summary)

1. Builds rows with `attendance_date = today()->toDateString()` (not session date).  
2. Chunk-upserts into `attendance.records` (500).  
3. Calls `refreshDailySummary` for **today’s** date.  
4. Returns inserted/upserted count.  
5. Performs **no** session status check, enrollment ATT-D4 check, idempotency, outbox, or authorization.

---

## 3. Complete Caller Inventory

### 3.1 Direct PHP callers of `recordSectionAttendance`

| Location | Call? | Confidence |
|----------|-------|------------|
| `app/Application/Attendance/**` | **No** | **PROVEN** |
| `app/Http/Controllers/**` | **No** | **PROVEN** |
| `app/Infrastructure/**` | **No** | **PROVEN** |
| `app/Jobs/**` | **No** | **PROVEN** |
| `app/Console/**` | **No** | **PROVEN** |
| `app/Listeners/**` | **No** | **PROVEN** |
| `routes/**` | **No** | **PROVEN** |
| `database/seeders/**` | **No** | **PROVEN** |
| `database/factories/**` | **No** | **PROVEN** |
| `clients/**` | **No** | **PROVEN** |
| `tests/**` | **No** | **PROVEN** |
| Self (`AttendanceBatchService.php`) | Definition only | **PROVEN** |

**Repository-wide PHP result:** the **only** PHP occurrence of `AttendanceBatchService` / `recordSectionAttendance` under `app/` is the class definition itself.

### 3.2 Indirect / documentation “callers” (not executable)

| Artifact | Nature | Confidence |
|----------|--------|------------|
| `.cursor/architecture/api-conventions.md` | Example controller injects + calls legacy service | **PROVEN** (stale example) |
| `.cursor/architecture/batch-write-patterns.md` | Documents + example `store()` calling legacy | **PROVEN** (stale) |
| `.cursor/architecture/production-readiness.md` | Checklist: “AttendanceBatchService used for all attendance writes” | **PROVEN** (contradicts R1) |
| `.cursor/architecture/peak-hour-strategy.md` | Names legacy as peak write path | **PROVEN** (stale) |
| `.cursor/architecture/normalization-and-cqrs.md` | Diagram arrow → AttendanceBatchService | **PROVEN** (stale) |
| `.cursor/architecture/cache-invalidation.md` | “After AttendanceBatchService completes” | **PROVEN** (stale) |
| `.cursor/architecture/testing-strategy.md` | References `tests/Unit/Services/AttendanceBatchServiceTest.php` | **PROVEN** (file **does not exist**) |
| `.cursor/architecture/WORK-PLAN.md` / `PHASE-C-OPERATIONS.md` / `laravel-architecture.md` | Legacy inventory / migration notes | **PROVEN** |
| `docs/sis/enrollment/*` | Inventory / compliance mention | **PROVEN** |
| Phase-attendance gates R1.1–R1.8A | Historical dual-path warnings | **PROVEN** |

### 3.3 Non-existent expected artifacts

| Expected | Status |
|----------|--------|
| `AttendanceBatchRequest` | **Not found** |
| `tests/Unit/Services/AttendanceBatchServiceTest.php` | **Not found** |
| Dedicated Artisan command for batch attendance | **Not found** |

---

## 4. Runtime Reachability

| Question | Answer | Confidence |
|----------|--------|------------|
| Is the class autoloadable? | **Yes** (PSR-4 `App\`) | **PROVEN** |
| Can Laravel container resolve it? | **Yes** (concrete class, no special binding required) | **INFERRED** (standard Laravel DI) |
| Can Tinker / one-off script invoke it? | **Yes**, if app bootstraps and GUC/DB allow | **INFERRED** |
| Is it invoked by scheduled tasks? | **No** reference in `routes/console.php` / Console | **PROVEN** (static) |
| Is it invoked by queue jobs in repo? | **No** | **PROVEN** (static) |
| Is it invoked in production ops outside repo? | Unknown scripts, runbooks, unpublished packages | **UNKNOWN** |
| Does CQRS Infrastructure call it? | **No** — `EloquentAttendanceWriteRepository` re-implements upsert/summary | **PROVEN** |

```text
Latent capability: PRESENT
Wired production entry point (in-repo): ABSENT
```

---

## 5. Route / Command / Job Reachability

### HTTP routes (`routes/api.php`)

| Route | Handler | Uses legacy? |
|-------|---------|--------------|
| `POST attendance/sessions` | `AttendanceController::storeSession` → Create CQRS | **No** |
| `POST …/marks` | `mark` → `MarkSectionAttendanceHandler` | **No** |
| `POST …/correct` | `correct` → `CorrectAttendanceRecordHandler` | **No** |
| `POST …/close` | `close` → `CloseAttendanceSessionHandler` | **No** |
| Read endpoints | Query handlers | **No** |

**PROVEN:** No production-facing HTTP route in this repository reaches `recordSectionAttendance`.

### Console / Jobs / Imports

| Surface | Reach legacy? | Confidence |
|---------|---------------|------------|
| Scheduled intelligence/optimization tasks | No attendance writer | **PROVEN** |
| Import jobs | No match | **PROVEN** (static search) |
| Listeners | No match | **PROVEN** |

---

## 6. Test Dependency Inventory

| Suite / file | Depends on legacy writer? | Confidence |
|--------------|---------------------------|------------|
| `tests/Unit/Attendance/*` | **No** | **PROVEN** |
| `tests/Feature/Attendance/AttendanceHttpApiTest.php` | **No** (CQRS HTTP) | **PROVEN** |
| `tests/Feature/Database/PostgreSql/AttendanceWritePathPostgreSqlTest.php` | **No** | **PROVEN** |
| `tests/Feature/Database/PostgreSql/AttendanceR18aIntegrityPostgreSqlTest.php` | **No** | **PROVEN** |
| `tests/Feature/Security/PostgreSql/AttendanceRlsPostgreSqlTest.php` | **No** | **PROVEN** |
| `tests/Unit/Services/AttendanceBatchServiceTest.php` | **Missing** (doc-only ghost) | **PROVEN** |

```text
Test blocker for retirement: NONE (no executable test depends on the class).
Doc/test-strategy cleanup: REQUIRED as a precondition for clean retirement narrative.
```

---

## 7. CQRS vs Legacy Semantic Comparison

Authoritative CQRS path for marking: `MarkSectionAttendanceHandler` + `EloquentAttendanceWriteRepository`.  
Corrections: `CorrectAttendanceRecordHandler` (legacy has **no** correct API).

| Concern | CQRS (authoritative) | Legacy `recordSectionAttendance` | Difference |
|---------|----------------------|----------------------------------|------------|
| **session_date / attendance_date** | Uses locked `session.session_date` | Uses **`today()`** | **CRITICAL semantic conflict** |
| **OPEN/CLOSED lifecycle** | Mark requires OPEN; Correct allows OPEN/CLOSED, rejects CANCELLED | **No status check** — can upsert into CLOSED/CANCELLED sessions | **HIGH** |
| **School isolation** | Session school vs `command.schoolId`; cross-school throws | Accepts caller `schoolId` without verifying session ownership | **HIGH** |
| **Strategy A `sessions.school_id`** | Stamped at Create; Mark reads session stamp | Does not touch sessions; does not validate stamp | **MEDIUM** (indirect) |
| **academic_year_id** | Must match session | Taken from caller args; no session match check | **HIGH** |
| **Section ownership** | Derived from session; enrollment must match section | Caller passes `sectionId` for summary only; not validated vs session | **HIGH** |
| **Subject / period** | Session identity (Create/R1.8A) | Unused | **INFORMATIONAL** |
| **Enrollment ATT-D4** | `AttendanceEnrollmentGuard` | **None** | **HIGH** |
| **Payload validation** | Empty / max 500 / dup students / status VO | **None** (chunk only) | **HIGH** |
| **Idempotency** | Required key + store after success | **None** | **HIGH** |
| **Outbox / domain events** | `SectionAttendanceMarked` staged in UoW | **None** | **HIGH** |
| **Transaction boundary** | `UnitOfWork` wraps upsert + summary + outbox | Upserts then summary; **no explicit UoW/outbox** | **MEDIUM** |
| **Correction semantics** | Separate Correct + reason + previous/new event | Silent upsert overwrite of status/notes | **HIGH** (audit gap) |
| **Authorization** | Policy + FormRequest + permissions | **None inside service** | **CRITICAL** if called outside HTTP policy |
| **Error mapping** | Domain exceptions → HTTP codes | Raw DB exceptions likely | **MEDIUM** |
| **Duplicate records** | DB UNIQUE `(session_id, student_id, academic_year_id)` + upsert | Same UNIQUE/upsert | **Aligned at DB** |
| **R1.8A OPEN session UNIQUE** | N/A to this method (no session insert) | N/A | **INFORMATIONAL** |
| **Summary refresh date** | `session.session_date` | **`today()`** — can refresh wrong day / desync projection | **HIGH** |
| **Summary ON CONFLICT columns** | CQRS updates `school_id` + `academic_year_id` too | Legacy omits school/year on DO UPDATE | **MEDIUM** |
| **Concurrency** | App + DB constraints; Mark vs Close race handled by OPEN check | No OPEN check → race with Close still writes | **HIGH** |
| **RLS interaction** | Runs under SchoolContext GUC (middleware) | Same tables; **depends on caller setting GUC** | **MEDIUM–HIGH** |
| **Creates sessions** | Create command | **No** | Aligned (does not create dups) |

**Do not fix these differences in R1.9 audit.**

---

## 8. Security / RLS / Isolation Analysis

| Bypass target | Can legacy bypass? | Severity | Confidence |
|---------------|--------------------|----------|------------|
| Permission boundaries (`attendance.*`) | **Yes**, if invoked outside FormRequest/Policy | **CRITICAL** | **PROVEN** (no authz in service) |
| SchoolContext isolation | Partially — FORCE RLS still applies if GUC set; caller-supplied `schoolId` can mismatch session | **HIGH** | **PROVEN** / **INFERRED** for GUC |
| CQRS validation (OPEN, ATT-D4, payload) | **Yes** | **HIGH** | **PROVEN** |
| R1.7 Strategy A stamp rules | Does not rewrite sessions; can write records under wrong school_id arg | **HIGH** | **PROVEN** |
| FORCE RLS | **No free pass** — policies still evaluate; empty GUC fail-closed for protected tables | **MEDIUM** residual (integrity ≠ tenant leak) | **INFERRED** from R1.7 design |
| R1.8A duplicate OPEN protection | **N/A** — does not insert sessions | **INFORMATIONAL** | **PROVEN** |
| Lifecycle rules | **Yes** — marks on non-OPEN | **HIGH** | **PROVEN** |
| Audit / outbox | **Yes** — silent writes | **HIGH** | **PROVEN** |
| Idempotency | **Yes** | **HIGH** | **PROVEN** |

### Classification roll-up

| ID | Finding | Severity |
|----|---------|----------|
| LEG-SEC-001 | Callable service with zero internal authorization | **CRITICAL** (capability) |
| LEG-SEC-002 | No in-repo HTTP/job entry point currently exposes it | **INFORMATIONAL** (mitigation) |
| LEG-SEC-003 | `today()` vs `session_date` integrity poison | **HIGH** |
| LEG-SEC-004 | Silent overwrite without Correct audit | **HIGH** |
| LEG-SEC-005 | Stale docs re-teach legacy as preferred writer | **MEDIUM** (reintroduction risk) |
| LEG-SEC-006 | FORCE RLS does not repair date/lifecycle semantics | **MEDIUM** |

No new security mechanisms are proposed in this audit (design-only).

---

## 9. Integrity / Idempotency / Concurrency Analysis

| Topic | Assessment | Confidence |
|-------|------------|------------|
| Record UNIQUE | Shared with CQRS — prevents duplicate identity rows | **PROVEN** |
| Idempotent Mark | Legacy cannot satisfy R1 idempotency contract | **PROVEN** |
| Concurrent Mark vs Close | Legacy ignores CLOSED → can mutate after close | **PROVEN** |
| Concurrent dual writers (CQRS + legacy) | Possible if both invoked → last upsert wins; summary date may diverge | **INFERRED** |
| R1.8A session uniqueness | Unaffected (legacy does not create sessions) | **PROVEN** |
| Summary correctness | Wrong date bucket risk when session_date ≠ today | **PROVEN** |

---

## 10. Data Risk Analysis

| Risk | If legacy invoked | Blast radius |
|------|-------------------|--------------|
| Wrong `attendance_date` | Records attributed to calendar today, not session | Reports/history/summary wrong day |
| Mark after Close | Lifecycle invariant broken | Audit + business process |
| Cross-school `school_id` stamp on records | Tenant data integrity (RLS may still block wrong GUC reads) | HIGH integrity / possible write fail |
| No Correct trail | Overwrites look like first marks | Compliance / dispute |
| Summary desync | Dashboard counts for wrong date | Operational |

Historical data: **UNKNOWN** whether production ever wrote via this class prior to R1 CQRS. No migration/data scan performed in this audit (readiness only).

---

## 11. Retirement Options A–D

| Option | Meaning | Fit to evidence |
|--------|---------|-----------------|
| **A. RETIRE NOW** | Delete class immediately | Tempting (zero PHP callers) but **unsafe as sole decision** — UNKNOWN external use + stale docs still prescribe it |
| **B. DEPRECATE + BLOCK NEW CALLERS** | Keep file temporarily; hard-block new references / runtime use; update SSOT docs | **Best match** — removes latent bypass path without assuming zero external use |
| **C. KEEP TEMPORARILY WITH EXPLICIT QUARANTINE** | Leave callable; document quarantine only | Weaker than B; Design Lock already prohibits public coexistence |
| **D. RETIRE AFTER IDENTIFIED DEPENDENCIES ARE MIGRATED** | Wait for migrations | **No code dependencies to migrate**; only docs/ops confirmation remain — partial fit |

---

## 12. Recommended Option

```text
RECOMMENDED: B — DEPRECATE + BLOCK NEW CALLERS
```

**Rationale (evidence-based):**

1. **PROVEN** zero in-repo executable callers → full delete is *plausible*, not yet *proven safe*.  
2. **PROVEN** latent invokability + **CRITICAL** authz bypass capability if called.  
3. **PROVEN** stale architecture docs still instruct engineers to use the legacy writer.  
4. Design Lock §16 already forbids alternate public writer — B enforces that lock.  
5. After B + ops confirmation + doc SSOT fix, a later authorization can execute **A** (delete) with low risk.

**Not chosen:**

- **A now** — violates “do not treat absence of evidence as proof of absence.”  
- **C** — insufficient against Design Lock and latent bypass.  
- **D alone** — misframes the problem as “migrate callers” when callers are absent; real work is quarantine + doc/ops closure.

---

## 13. Preconditions for Implementation

Concrete, verifiable preconditions before any R1.9 **implementation** authorization:

1. **Human phrase** explicitly approving implementation, e.g.  
   `APPROVED — IMPLEMENT ATTENDANCE R1.9 LEGACY WRITER RETIREMENT ONLY`  
   and naming option **B** (or **A** if conditions 2–6 already met).

2. **Documentation SSOT remediation plan** for stale write-path docs at minimum:  
   `api-conventions.md`, `production-readiness.md`, `batch-write-patterns.md`, `peak-hour-strategy.md`, `normalization-and-cqrs.md`, `cache-invalidation.md`, `testing-strategy.md`, `features/Attendance.md` consistency check.

3. **Ops / deployment confirmation** (human-attested): no production cron, tinker runbook, external package, or unpublished branch calls `AttendanceBatchService`.

4. **Architecture enforcement design** (for implement phase): forbid new references (validator rule and/or runtime `BadMethodCallException` / `LogicException` on `recordSectionAttendance`) — details left to implement gate.

5. **Do not modify** R1.7 RLS, R1.8A CHECK/UNIQUE, CQRS handlers’ business rules, API routes, or permissions as part of retirement unless separately authorized.

6. **Regression pack defined** (§14) must be included in implement authorization.

7. **Rollback story agreed** (§15).

---

## 14. Required Tests for Future Implementation

When implementation is authorized, require at least:

| # | Test | Purpose |
|---|------|---------|
| 1 | Static / architecture check fails on new `AttendanceBatchService` references (except allowlist during deprecate) | Block reintroduction |
| 2 | If runtime block chosen: calling `recordSectionAttendance` throws deterministic domain/infra exception | Prove quarantine |
| 3 | Existing R1.4 CQRS write path PASS | No regression |
| 4 | Existing R1.5 HTTP PASS | No regression |
| 5 | Existing R1.7 RLS PASS | No regression |
| 6 | Existing R1.8A integrity PASS | No regression |
| 7 | Unit Attendance PASS | No regression |
| 8 | `architecture:validate --fitness` PASS | Layer rules intact |
| 9 | Optional: assert HTTP mark path never resolves `AttendanceBatchService` | Wiring proof |

Do **not** add tests that keep exercising legacy upsert as a supported path.

---

## 15. Rollback / Recovery Considerations

| Action | Rollback |
|--------|----------|
| Doc-only SSOT updates | Revert docs |
| Runtime hard-block on method | Revert single class change; CQRS unaffected |
| Full class delete (future A) | Restore file from VCS; no schema rollback needed |
| Schema/RLS | **Must not** be part of R1.9 — N/A |

Data written historically by legacy (if any) is **not** auto-repairable by deleting the class — separate data remediation would need its own authorization (**UNKNOWN** need).

---

## 16. Scope Boundary

### In scope for this audit

- Inventory, reachability, semantic/security comparison, options, readiness verdict.

### Explicitly out of scope (this phase)

```text
Deleting / renaming AttendanceBatchService
Deprecation code / runtime blocks
Route, permission, API, UI changes
RLS / FORCE RLS changes
R1.8A constraint/index changes
Attendance schema / partitions
CQRS handler behavior changes
Reopen / Cancel
Enrollment / Graduation / Certificates
```

### Locked decisions preserved

```text
R1.7 Strategy A + FORCE RLS
R1.8A status {1,2,3} + OPEN partial UNIQUE
PostgreSQL concurrency authority
CLOSED → new OPEN allowed
Reopen/Cancel/UI/year partitions deferred
```

---

## 17. Human Decisions Required

1. Confirm **recommended option B** (or override to A/C/D with written rationale).  
2. Attest **no out-of-repo callers** (or list them for migration).  
3. Authorize (or defer) **doc SSOT cleanup** as part of R1.9 implement vs separate docs task.  
4. Choose quarantine mechanism preference for implement phase:  
   - runtime throw on `recordSectionAttendance`, and/or  
   - architecture validator forbid, and/or  
   - delete class (A) after B soak.  
5. Decide whether historical `today()`-dated rows need a **data audit** (separate authorization).  
6. Issue explicit implementation phrase before any code change.

Do **not** treat this list as implementation authorization.

---

## 18. Final Readiness Verdict

```text
VERDICT: READY WITH CONDITIONS

Recommended next implementable direction: Option B
(Deprecate + block new callers; full delete only after conditions)

NOT READY for unconditional RETIRE NOW (Option A).
NOT an authorization to implement.
```

### Conditions (must all be true before implement grant)

1. Explicit human implementation authorization naming R1.9 + option.  
2. Ops attestation on external callers (or inventory).  
3. Stale write-path documentation remediation included or sequenced.  
4. Quarantine/delete design does not touch R1.7/R1.8A/API/permissions.  
5. Regression pack (§14) mandatory in implement gate.  
6. R1.8A remains closed/unmodified.

---

## Final Hard Stop

```text
R1.9 READINESS AUDIT COMPLETE.
NO IMPLEMENTATION AUTHORIZED.
HUMAN REVIEW REQUIRED.
NO AUTOMATIC NEXT PHASE.
```

Do not retire the legacy writer.  
Do not begin Reopen / Cancel / UI / year partitioning.  
Do not modify R1.7 or R1.8A artifacts.

**R1.7 Strategy A remains authoritative. R1.8A remains PASS / closed.**

---

## SIS CHANGE REPORT (R1.9 Audit)

```text
Status: PASS (audit deliverable)
Risk: N/A (no runtime change)

Application / Database / API / UI Modified: NO
Governance Modified: YES (this gate only)

Files Added:
  .cursor/database/phase-attendance/15-ATTENDANCE-R1.9-LEGACY-WRITER-RETIREMENT-READINESS-GATE.md

Files Modified: NONE (application/database)

Final Gate Status: READY WITH CONDITIONS
Recommended Option: B — DEPRECATE + BLOCK NEW CALLERS
Implementation authorized: NO
```
