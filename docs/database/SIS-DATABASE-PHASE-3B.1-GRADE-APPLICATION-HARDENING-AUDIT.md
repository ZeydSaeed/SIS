# DATABASE / APPLICATION — PHASE 3B.1

# GRADE ENTRY & CORRECTION APPLICATION HARDENING

# AUDIT + DESIGN + IMPLEMENTATION PLAN ONLY

```text
STATUS: AUDIT + DESIGN + PLAN ONLY
DATE: 2026-09-10
PRECONDITION: PHASE 3B GATE PASS WITH CONDITIONS
BLUEPRINT OBJECTS: 87 (unchanged — no schema work in 3B.1)
```

---

## 1. Executive Summary

Phase 3B delivered a secure **database contract** for `exams.student_grades`. Production-safe grade entry still requires an **Enrollment-shaped Application layer**: Commands/Handlers, Policy + permissions, school-scoped API, UnitOfWork transactions, Outbox domain events, IdempotencyStore, and SecurityAuditLogger — none of which exist yet under `Application/Exams` or `Http` for grades.

**Findings:**

| Layer | State |
|-------|--------|
| Domain VO `GradeStatus` | Present |
| `Application/Exams/**` | **Absent** |
| Grade Policy / permissions | **Absent** |
| Grade API / FormRequests | **Absent** |
| Outbox events for grades | **Absent** |
| Enrollment pattern (reference) | Mature — reuse |

**Design locks for future implementation:**

| Topic | Decision |
|-------|----------|
| Context | `Exams` (matches `Domain/Exams` + schema) |
| Writes | `EnterStudentGrade`, `CorrectStudentGrade`, `VoidStudentGrade` (+ optional `FinalizeStudentGrade`) |
| Correction | VOID + INSERT only; `correction_of_grade_id` **immutable after INSERT** |
| Cycle prevention | **Option A** (immutable correction pointer) — preferred |
| Idempotency | Existing `IdempotencyStore` + `X-Idempotency-Key` |
| Authz | `grades.*` permissions + Policy + SchoolContext + RLS |
| `max_score` | Snapshot from `exam_sessions.max_grade` — **no client override** |
| Finalization | **Include minimal Finalize** in 3B.1 (small, unlocks safe correction policy) |
| Schema | **No DDL** in 3B.1 unless a proven blocker appears |

```text
PHASE 3B.1 PLAN READY FOR HUMAN APPROVAL
```

```text
No Phase 3B.1 application code was implemented.
No database schema was modified.
No live database was modified.
Phase 3C was not implemented.
Human approval is required before implementation.
```

---

## 2. Existing Architecture Findings

### Inspected

`app/Domain`, `app/Application`, `app/Infrastructure`, `app/Http`, `app/Security`, migrations, tests, audit/outbox/idempotency, Phase 3B gate.

### Grade-related inventory

| Asset | Status |
|-------|--------|
| `exams.student_grades` + partitions/RLS/CHECKs | LIVE (Phase 3B) |
| `App\Domain\Exams\ValueObjects\GradeStatus` | LIVE |
| `App\Database\StudentGradesPartitionManager` | LIVE |
| `Application/Exams/*` | **Missing** |
| Grade Eloquent model / repository | **Missing** |
| Grade Policy | **Missing** |
| Grade routes/controllers | **Missing** |

### Shared platform (reuse — do not reinvent)

| Contract | Location |
|----------|----------|
| `Command` / `CommandHandler` | `Application/Contracts` |
| `UnitOfWork` | Handler-owned transactions |
| `OutboxRepository::stage(DomainEvent)` | Inside transaction |
| `IdempotencyStore` (`key` + `command_name`) | `audit.idempotency_keys` |
| `SchoolContext::requireId()` | HTTP school header |
| `SecurityAuditLoggerInterface` | Controller-side security audit |
| `CorrelationContext` | Outbox correlation |
| `sis:make-command` / `sis:make-query` | Scaffolding |

---

## 3. Existing Enrollment Pattern Analysis

Enrollment is the **canonical write path** to clone.

```text
HTTP FormRequest + Policy authorize
      ↓
SchoolContext.requireId()  (school never from body)
      ↓
Command (+ optional X-Idempotency-Key)
      ↓
Handler:
  idempotency find → early return
  domain/spec validation
  UnitOfWork.transaction {
      repository write
      outbox->stage(DomainEvent)
  }
  idempotency store (post-commit today)
  Result
      ↓
Controller securityAudit.record(...)
```

**Notable Enrollment details:**

| Concern | Pattern |
|---------|---------|
| Command naming | Verb + noun (`EnrollStudent`, `CancelEnrollment`) |
| School injection | Controller from `SchoolContext`, not request body |
| Cross-school | Policy + handler school checks + RLS |
| Permissions | `enrollment.view|create|update|cancel` via `Permission` + seeder |
| Mass assignment | FormRequest rejects injected `school_id` / actor ids |
| Domain events | Readonly classes implementing `DomainEvent` + `payload()` |
| Idempotency | Command name constant + TTL 86400s |
| Reads | Separate Query/Handler + DTO |
| Delete | Cancel (status), not hard delete |

**Gap vs ideal atomicity:** Enrollment stores idempotency **after** commit. For grades, keep compatibility but catch unique-violation races and map to conflict/replay (see §13).

---

## 4. Grade Application Boundary

```text
Api\GradeController (thin)
      ↓ authorize Policy + SchoolContext
EnterStudentGradeCommand | CorrectStudentGradeCommand | VoidStudentGradeCommand
| FinalizeStudentGradeCommand (recommended)
      ↓
*Handler (Application/Exams/Commands)
      ↓ Domain exceptions / specifications
UnitOfWork.transaction
      ↓ StudentGradeRepositoryInterface (Domain port)
exams.student_grades  (+ RLS GUC already set by middleware)
      ↓
Outbox DomainEvent  +  (post) IdempotencyStore
      ↓
Controller SecurityAuditLogger
```

**Scaffold (when approved):**

```bash
php artisan sis:make-feature Exams --command=EnterStudentGrade --query=GetStudentGrade
php artisan sis:make-command Exams CorrectStudentGrade
php artisan sis:make-command Exams VoidStudentGrade
php artisan sis:make-command Exams FinalizeStudentGrade
```

Do **not** place business logic in controllers or Eloquent models.

---

## 5. Create Grade Design

### Command (conceptual)

`EnterStudentGradeCommand`

| Field | Source |
|-------|--------|
| `schoolId` | SchoolContext |
| `examEnrollmentId` | Request (validated) |
| `score` / `isAbsent` | Request |
| `status` | Default `Entered` (2); allow `Draft` (1) only if product needs drafts |
| `enteredBy` | Auth user id |
| `idempotencyKey` | `X-Idempotency-Key` |
| `reason` | Optional note (not a DB column unless later ADR) |

**Derived by handler (never trust client):**

`academic_year_id`, `exam_session_id`, `enrollment_id`, `student_id`, `subject_id`, `max_score` ← from exam enrollment → session → enrollment graph.

### Preconditions

| Check | Layer |
|-------|-------|
| Exam enrollment exists & `school_id` matches context | Application |
| Seat status active (`Registered`/`Confirmed`/`Present`) — reject `Withdrawn` | Domain/App |
| Academic enrollment active | Application |
| Exam/session not Cancelled | Application |
| No current grade for enrollment/year | App pre-check + **DB unique** |
| Partition exists for year | App calls `StudentGradesPartitionManager` or maps PG error |
| Score/absent invariants | Domain + DB CHECK |

### Insert row

```text
is_current = true
correction_of_grade_id = null
max_score = session.max_grade snapshot
status = Draft|Entered (3B.1 default: Entered)
entered_at = now
```

---

## 6. Correction Design

### Command

`CorrectStudentGradeCommand`

| Field | Source |
|-------|--------|
| `schoolId` | SchoolContext |
| `currentGradeId` + `academicYearId` | Request (composite identity) |
| `score` / `isAbsent` | Request |
| `reason` | **Required** (audit/outbox payload) |
| `correctedBy` | Auth user |
| `idempotencyKey` | Header |

### Atomic sequence (single UnitOfWork)

```text
1. Load current grade WHERE id + academic_year_id + school_id + is_current
2. Authorize grades.correct (+ elevate if status=Finalized)
3. SELECT … FOR UPDATE (optional but recommended on current row)
4. UPDATE current → is_current=false, status=Voided
5. INSERT replacement:
     is_current=true
     correction_of_grade_id = prior.id
     max_score = prior.max_score  (preserve historical max unless session retake rules say otherwise)
     status = Entered or Finalized (policy: default Entered; require finalize separately)
6. outbox->stage(GradeCorrected)  [includes prior + new ids]
```

**Forbidden:** UPDATE of `score` / `max_score` on finalized rows; UPDATE of `correction_of_grade_id` on any row after insert.

---

## 7. Void Design

### Command

`VoidStudentGradeCommand` — void **without** replacement (e.g. erroneous entry cancelled).

```text
UPDATE is_current=false, status=Voided
outbox GradeVoided
```

If a replacement is needed → use **Correct**, not Void+manual insert outside handler.

---

## 8. Correction Chain Strategy

| Option | Verdict |
|--------|---------|
| **A. Immutable `correction_of_grade_id` after INSERT** | **SELECTED** — lowest complexity; cycles require mutating pointers |
| B. App recursive walk | Backup only if Option A insufficient |
| C. DB recursive CTE trigger | **Rejected** for 3B.1 — complexity / portability |

**Preventions:**

| Case | Enforcement |
|------|-------------|
| A → A | DB CHECK `correction_of_grade_id <> id` |
| A → B → A | Impossible if pointers immutable and only new rows point backward |
| Mutating `correction_of_grade_id` | Repository forbids column on UPDATE; no public API |

Document remaining residual: malicious raw SQL as table owner — out of app threat model; RLS/FORCE still applies to non-bypass roles.

---

## 9. Authorization Matrix

Extend `Permission` + `config/security.php` + seeder (Enrollment style):

| Permission | Use |
|------------|-----|
| `grades.view` | List/show |
| `grades.create` | Enter grade |
| `grades.correct` | Correct / void-with-replace |
| `grades.void` | Void without replace |
| `grades.finalize` | Finalize current grade |

| Role (seed example) | Permissions |
|---------------------|-------------|
| Teacher | view, create (own school) |
| Registrar / Academic admin | view, create, correct, void, finalize |
| Read-only staff | view |

**Policy:** `GradePolicy` on `StudentGradeRecord` (or equivalent), mirroring `EnrollmentPolicy` + `EnrollmentSchoolAccessService` → `GradeSchoolAccessService`.

**Defense in depth:** Policy → Handler school checks → composite FKs → FORCE RLS.

Correction of **Finalized** grades: require `grades.correct` **and** (same or elevated) — recommend same permission + security audit reason mandatory; optional future `grades.correct_finalized`.

---

## 10. State Transition Matrix

`GradeStatus`: Draft=1, Entered=2, Submitted=3, Finalized=4, Voided=5.

| Current | Operation | Next | Notes |
|---------|-----------|------|-------|
| (none) | Enter | Entered (or Draft) | Creates current |
| Draft | Enter save / promote | Entered | Prefer single Enter command |
| Entered | Finalize | Finalized | Sets `finalized_at` |
| Submitted | Finalize | Finalized | Submitted optional in 3B.1 — can skip using Submitted |
| Finalized | Correct | Prior→Voided; New→Entered | Elevated audit |
| Finalized | Void | Voided | No replacement |
| Any current | Void | Voided | `is_current=false` |
| Voided | * | — | Terminal; not current |
| * | Hard DELETE | **Forbidden** | DB trigger + no API |

**3B.1 simplification (recommended):** Do not require `Submitted` in v1 API — use Draft (optional) → Entered → Finalized → Voided/Correct. Keep enum value 3 reserved.

---

## 11. Idempotency Design

Reuse `IdempotencyStore` exactly.

| Command | `command_name` |
|---------|----------------|
| Enter | `EnterStudentGrade` |
| Correct | `CorrectStudentGrade` |
| Void | `VoidStudentGrade` |
| Finalize | `FinalizeStudentGrade` |

| Aspect | Rule |
|--------|------|
| Key | Client `X-Idempotency-Key` |
| Scope | `(key, command_name)` — same as Enrollment |
| Payload stored | New grade id, academic_year_id, exam_enrollment_id, status |
| Replay | Return `Result::fromIdempotency` — no second write/outbox |
| Conflict | Same key, different logical body — **out of scope for store** (Enrollment does not fingerprint); document: clients must not reuse keys across different intents |
| TTL | 86400s default |
| Race | Unique current-grade index is SSOT; on unique violation → map to conflict or re-read idempotency |

---

## 12. Transaction Boundary

```text
BEGIN (UnitOfWork)
  validate + authorize (may be outside if read-only)
  lock current row if correction
  UPDATE void prior (correction/void)
  INSERT new grade (enter/correct)
  ensure partition already exists (ops) / fail
  outbox.stage(event)
COMMIT
THEN idempotency.store (Enrollment-compatible)
THEN controller securityAudit.record
```

**Must be inside TX:** grade writes + outbox.  
**Security audit:** Enrollment records at controller **after** success (keep consistent).  
**Idempotency:** post-commit OK if unique index + conflict mapping exist.

---

## 13. Concurrency Strategy

| Scenario | Behavior |
|----------|----------|
| Two Enter for same `exam_enrollment_id` | One wins; other hits partial UNIQUE → `GradeConflictException` |
| Two Correct on same current | `FOR UPDATE` on current + unique on new current; loser conflicts |
| Retry after conflict | Client retries with new idempotency key or GET current |

**Smallest correct mechanism:** DB uniqueness + optional `SELECT FOR UPDATE` on correction + exception mapping. No distributed locks.

---

## 14. Audit Design

Extend `SecurityEventType`:

| Type | When |
|------|------|
| `GradeDataAccess` | List/show |
| `GradeDataModified` | Enter / correct / void / finalize |
| Reuse `IdorBlocked` | Cross-school denial |

Payload (non-PII heavy): school_id, grade_id, academic_year_id, exam_enrollment_id, student_id, action, actor_id, correlation_id, reason (correct/void).

Do **not** invent a second audit bus — use `SecurityAuditLoggerInterface` like Enrollment.

---

## 15. Outbox Design

Domain events (`App\Domain\Exams\Events\`):

| Event | Trigger |
|-------|---------|
| `StudentGradeEntered` | Enter |
| `StudentGradeCorrected` | Correct (includes `previous_grade_id`, `new_grade_id`) |
| `StudentGradeVoided` | Void |
| `StudentGradeFinalized` | Finalize |

`payload()` includes ids, school_id, academic_year_id, exam_enrollment_id, score/is_absent (score is academic data — acceptable in internal outbox), actor, occurred_at. Correlation via `OutboxRepository` + `CorrelationContext`.

No direct broker publish from handler.

---

## 16. Observability

Use existing logging/metrics only if present; minimum:

| Signal | Purpose |
|--------|---------|
| Log + security audit | Success/deny |
| Log warning on `GradeConflictException` | Concurrent conflict |
| Optional counters later | `grade.enter.success/failure` — only if project metrics facade exists |

Do not add Prometheus stack in 3B.1 unless already standard.

---

## 17. API Design (design only)

Mirror Enrollment REST style (exact prefix per `routes/api.php` conventions):

| Method | Route (conceptual) | Command/Query |
|--------|-------------------|---------------|
| POST | `/grades` | EnterStudentGrade |
| POST | `/grades/{id}/correct` | CorrectStudentGrade |
| POST | `/grades/{id}/void` | VoidStudentGrade |
| POST | `/grades/{id}/finalize` | FinalizeStudentGrade |
| GET | `/grades/{id}` | GetStudentGrade |
| GET | `/exam-sessions/{id}/grades` | ListGradesForExamSession |
| GET | `/enrollments/{id}/grades` | ListGradesForEnrollment |

**Headers:** school context (existing), `X-Idempotency-Key` on writes.  
**Reject body:** `school_id`, `entered_by`, `max_score`, `academic_year_id`, `student_id`, `is_current`.  
**Status:** 201 create; 200 idempotent replay; 403 authz; 409 conflict; 422 validation; 404 not found/school mismatch.

**No DELETE route.**

---

## 18. Query Design

Minimum CQRS reads:

| Query | Purpose |
|-------|---------|
| `GetStudentGrade` | By `(id, academic_year_id)` + school |
| `ListGradesForExamSession` | Markbook |
| `ListGradesForEnrollment` | Student exam history for enrollment |
| `GetCurrentGradeForExamEnrollment` | Correction UI precondition |

DTO: ids, score, max_score, is_absent, status, is_current, correction_of_grade_id, timestamps — no denormalized names required (join in read repo if needed).

---

## 19. Security Contract

Create (when implementing): `docs/sis/exams/SECURITY-CONTRACT.md` + `SECURITY-TEST-MATRIX.md` cloned from Enrollment.

Must cover: authn, `grades.*` authz, school scope, RLS/FORCE, mass-assignment, idempotency, audit, outbox, no hard delete, IDOR, finalized correction rules.

---

## 20. Architecture Fitness

Implementation must pass:

```text
php artisan architecture:validate --fitness
php artisan architecture:feature-check Exams
php artisan security:validate
```

Rules: Domain pure; handlers own UoW; no controller DB writes; repositories in Infrastructure; sensitive commands require idempotency key when flagged in feature contract.

---

## 21. Test Matrix (design — not implement)

| Area | Cases |
|------|-------|
| Unit | GradeStatus transitions; score/absent rules; correction immutability helpers |
| Handler | Enter success; duplicate current → conflict; cross-school reject; withdrawn seat reject; idempotent replay; correct chain A→B→C; void; finalize; partition missing |
| API/Security | Unauth 401; no permission 403; IDOR; mass-assignment; audit persistence; school context missing |
| PG | Existing StudentGrades RLS/partition/unique/delete tests remain green |
| Regression | Admission, ExamFoundation, StudentGrades, Enrollment, security:validate, sis:verify-database |

---

## 22. Error Contract

| Condition | App error_code (conceptual) | HTTP |
|-----------|------------------------------|------|
| Validation | `grades.validation` | 422 |
| Unauthorized | `grades.forbidden` | 403 |
| Not found / wrong school | `grades.not_found` | 404 |
| Duplicate current / concurrent | `grades.conflict` | 409 |
| Invalid correction target | `grades.invalid_correction` | 422 |
| Seat withdrawn | `grades.seat_inactive` | 422 |
| Missing partition | `grades.partition_missing` | 503/422 |
| Idempotent replay | success + `from_idempotency_cache` | 200/201 |
| Hard delete attempt | never exposed | — |

Map PG exceptions internally; do not leak SQL to clients.

---

## 23. Production Readiness

| Level | After 3B.1 implementation? |
|-------|----------------------------|
| Development Ready | Yes (handlers + sqlite/PG tests) |
| Test Ready | Yes if security matrix + PG suite pass |
| Staging Ready | Yes if permissions seeded + year partitions ops runbook followed |
| **Production Ready** | **No** until: academic years+partitions exist, roles assigned, API smoke, outbox consumer healthy, runbook for partition create, load smoke on enter/correct |

Operational prerequisites remain from Phase 3B Condition 1 (partitions per year).

---

## 24. Implementation Plan

| Step | Scope | Likely files | Risk | Tests | Rollback |
|------|-------|--------------|------|-------|----------|
| 1 | Permissions + config + seeder | `Permission.php`, `config/security.php`, seeder | Low | Permission presence | Revert seeder |
| 2 | Security contract docs | `docs/sis/exams/*` | Low | — | Delete docs |
| 3 | Domain: exceptions, events, data objects, repo interfaces | `Domain/Exams/**` | Low | Unit | Revert |
| 4 | Infrastructure: Eloquent record + repositories | `Infrastructure/Persistence/**` | Med | Repo tests | Revert |
| 5 | `EnterStudentGrade` command/handler/result | `Application/Exams/Commands/**` | Med | Handler + idempotency | Revert |
| 6 | `CorrectStudentGrade` + `VoidStudentGrade` | same | High | Correction chain | Revert |
| 7 | `FinalizeStudentGrade` | same | Med | Finalize rules | Revert |
| 8 | Queries + DTOs | `Application/Exams/Queries/**` | Low | Query tests | Revert |
| 9 | Policy + school access service | `Security/Policies`, Authorization | Med | Authz tests | Revert |
| 10 | FormRequests + Controller + routes | `Http/**`, `routes/api.php` | Med | API + security matrix | Revert routes |
| 11 | DI bindings | `ArchitectureServiceProvider` | Low | fitness | Revert |
| 12 | Feature contract registration | architecture configs | Low | `feature-check Exams` | Revert |
| 13 | Full regression + PG | tests | — | Gate | — |
| 14 | **Phase 3B.1 GATE report** | `docs/database/...GATE.md` | — | — | STOP |

**No migrations** unless implementation discovers a hard blocker (then STOP and request DDL approval).

---

## 25. Risk Register

| Risk | Severity | Probability | Mitigation |
|------|---------:|------------:|------------|
| Dual current grades under race | CRITICAL | Medium | Partial UNIQUE + conflict mapping |
| Correction overwrites score in place | CRITICAL | Low | Handler forbids; only VOID+INSERT |
| Cross-school grade write | CRITICAL | Medium | Policy + handler + RLS FORCE |
| Missing year partition in prod | HIGH | High | Ops runbook + clear error |
| Idempotency post-commit gap | MEDIUM | Medium | Unique index + replay/conflict |
| Correction cycles | MEDIUM | Low | Immutable correction_of |
| Skipping Finalize → weak audit | MEDIUM | Medium | Include Finalize in 3B.1 |
| Premature 3C coupling | HIGH | Low | Boundary below |
| God “GradeService” | MEDIUM | Medium | Commands only — architecture fitness |

---

## 26. Deferred Items

| Item | Defer to |
|------|----------|
| `Submitted` workflow / multi-step approval | Later product phase |
| Bulk grade import | Queue feature + separate approval |
| GPA / ranking | Phase 3C+ |
| In-place draft autosave API | Optional follow-on |
| `grades.correct_finalized` separate permission | If compliance requires |
| Moving idempotency inside TX | Shared platform improvement (Enrollment too) |
| UI / Inertia pages | Separate UI approval |

---

## 27. Phase 3C Boundary

```text
3B.1 DOES NOT CREATE:
  results.term_results
  results.annual_results
  results.transcripts
  GPA persistence
  ranking tables
```

Outbox events may later **feed** 3C recompute jobs — consumers are out of scope until 3C approval.

---

## 28. Final Recommendation

```text
PHASE 3B.1 PLAN READY FOR HUMAN APPROVAL
```

**Recommended first implementation slice when approved:** permissions → Enter + Correct handlers → Policy/API → tests → Gate — then stop (no 3C).

**Schema:** treat Phase 3B DB as frozen for 3B.1.

---

```text
HUMAN APPROVAL REQUIRED

This task was AUDIT + DESIGN + PLAN ONLY.

No Phase 3B.1 application code was implemented.
No database schema was modified.
No live database was modified.
Phase 3C was not implemented.

Await explicit human approval before implementing Phase 3B.1.
```
