# SIS DATABASE PHASE 3B.1 — GRADE APPLICATION HARDENING GATE

**Date:** 2026-09-10  
**Status:** PASS WITH CONDITIONS  
**Phase:** 3B.1 — Grade Application Hardening only  

---

## Executive Summary

Phase 3B.1 delivers a production-grade CQRS application layer around the already-approved `exams.student_grades` database foundation: Enter / Correct / Void / Finalize commands, read queries, Policy + permissions, SchoolContext API, UnitOfWork + Outbox + IdempotencyStore + SecurityAuditLogger, thin controllers, and tests. **No schema migrations were added or modified.** Phase 3C (results/GPA/transcripts) was not implemented.

---

## Human Approval Reference

Phase 3B.1 implementation was authorized by **explicit human approval** of the Phase 3B.1 Grade Application Hardening implementation prompt. This approval does **not** authorize Phase 3C or unrelated features.

> Phase 3B.1 is complete only within its approved scope. Phase 3C has NOT been implemented and requires separate human approval.

---

## Scope

| In scope | Out of scope |
|----------|--------------|
| Application/CQRS around `exams.student_grades` | `results.term_results` / `annual_results` / `transcripts` |
| Enter / Correct / Void / Finalize | GPA / ranking / report cards |
| Permissions, Policy, API, tests, gate | UI (React/Blade/desktop) |
| Outbox events (no consumers) | Phase 3C consumers |
| Security + feature contracts | Schema DDL changes |

---

## Files Added

### Domain
- `app/Domain/Exams/Data/*` (context, snapshot, create data)
- `app/Domain/Exams/Events/StudentGrade{Entered,Corrected,Voided,Finalized}.php`
- `app/Domain/Exams/Exceptions/*` (not found, seat, session, conflict, score, state, correction, partition, enrollment)
- `app/Domain/Exams/Repositories/StudentGradeRepositoryInterface.php`
- `app/Domain/Exams/Services/StudentGradeRules.php`
- `app/Domain/Exams/Services/StudentGradeWriteGuard.php`

### Application
- Commands/Handlers/Results: Enter, Correct, Void, Finalize
- Queries/Handlers: GetStudentGrade, ListGradesForExamSession, ListGradesForEnrollment, GetCurrentGradeForExamEnrollment
- `DTOs/StudentGradeDTO.php`
- `Contracts/StudentGradeReadRepositoryInterface.php`

### Infrastructure / HTTP / Security
- `EloquentStudentGradeRepository`, `EloquentStudentGradeReadRepository`, `StudentGradeRecord`
- `GradeController`, FormRequests (Enter/Correct/Void/Finalize)
- `GradePolicy`, `GradeSchoolAccessService`
- Routes under `/api/v1/grades` (+ list helpers)

### Docs / Tests
- `docs/sis/exams/SECURITY-CONTRACT.md`
- `docs/sis/exams/SECURITY-TEST-MATRIX.md`
- `docs/sis/exams/FEATURE-CONTRACT.md`
- Unit/handler/API/security/concurrency tests + seed trait

---

## Files Modified

- `routes/api.php` — grade routes (no DELETE)
- `ArchitectureServiceProvider` — repository bindings
- `SecurityServiceProvider` — GradePolicy registration
- `EloquentOutboxRepository` — grade event rehydration
- `Permission` / `config/security.php` / `SecurityPermissionSeeder` — grades permissions/roles
- `SecurityEventType` — Grade + restored Enrollment audit cases
- `SecuritySensitiveFieldGuard` — grade-sensitive prohibited fields
- `ARCHITECTURE-BASELINE.json` — `Grade` sensitive pattern
- `bootstrap/app.php` — 409 for `conflict` error codes
- `StudentGradesPartitionManager::partitionExists()`
- `Phase3AExamFoundationSchemaTest` — assert Phase 3C tables absent (student_grades exists from 3B)
- `Phase3FeatureContractTest` — Exams contract + GradeController thinness
- `tests/Concerns/InteractsWithSecurity.php` — grades acting helpers

---

## Database Changes

```text
NONE
```

No migrations created or altered in Phase 3B.1. Existing Phase 3B DDL, RLS, FORCE RLS, composite FKs, unique current-grade index, hard-delete trigger, and LIST partitioning remain authoritative.

---

## Architecture

Cloned the **Enrollment** pattern:

```text
HTTP → FormRequest → Policy → SchoolContext → Command → Handler
  → UnitOfWork(transaction: repository + Outbox::stage)
  → IdempotencyStore (post-commit)
  → SecurityAuditLogger (controller)
```

No God `GradeService`. Domain stays free of Illuminate. Unique-constraint mapping lives in Infrastructure repository.

---

## Commands/Handlers

| Command | Handler | Result |
|---------|---------|--------|
| `EnterStudentGrade` | `EnterStudentGradeHandler` | `EnterStudentGradeResult` |
| `CorrectStudentGrade` | `CorrectStudentGradeHandler` | `CorrectStudentGradeResult` |
| `VoidStudentGrade` | `VoidStudentGradeHandler` | `VoidStudentGradeResult` |
| `FinalizeStudentGrade` | `FinalizeStudentGradeHandler` | `FinalizeStudentGradeResult` |

---

## Queries

| Query | Handler |
|-------|---------|
| `GetStudentGrade` | `GetStudentGradeHandler` |
| `ListGradesForExamSession` | `ListGradesForExamSessionHandler` |
| `ListGradesForEnrollment` | `ListGradesForEnrollmentHandler` |
| `GetCurrentGradeForExamEnrollment` | `GetCurrentGradeForExamEnrollmentHandler` |

---

## Authorization

| Permission | Roles (config) |
|------------|----------------|
| `grades.view` | manager, teacher, viewer |
| `grades.create` | manager, teacher |
| `grades.correct` | manager |
| `grades.void` | manager |
| `grades.finalize` | manager |

Policy: `GradePolicy` + `GradeSchoolAccessService`.

---

## School Isolation

```text
Policy → SchoolContext (X-School-Id) → Handler schoolId scoping
  → Composite FKs → PostgreSQL RLS → FORCE RLS
```

Client `school_id` prohibited. Cross-school enter resolves as not-found / forbidden path.

---

## Idempotency

Shared `IdempotencyStore` with distinct command names. Header: `X-Idempotency-Key`. First write executes; replay returns cached result (`200` for enter). Unique current-grade index remains concurrency backstop. Known post-commit store edge case unchanged (same as Enrollment).

---

## Transactions

Handler owns `UnitOfWork::transaction` covering grade mutation/insert + outbox staging.

---

## Outbox

| Event | When |
|-------|------|
| `StudentGradeEntered` | Enter |
| `StudentGradeCorrected` | Correct (VOID+INSERT) |
| `StudentGradeVoided` | Void |
| `StudentGradeFinalized` | Finalize |

No Phase 3C consumers.

---

## Audit

`SecurityAuditLoggerInterface` with `GradeDataAccess` / `GradeDataModified` (and Enrollment audit cases restored). Controllers record actor/action/resource; correction/void include reason in context.

---

## Correction Model

**VOID + INSERT:** lock current (`FOR UPDATE` on PostgreSQL) → mark prior `is_current=false` + `status=Voided` → insert replacement with immutable `correction_of_grade_id` → stage outbox. `max_score` copied from prior snapshot (original session snapshot). Application cycle walk rejects corrupted chains.

---

## Finalization

`Draft|Entered|Submitted` → `Finalized` (Submitted not used as a workflow in 3B.1). After finalize: no in-place score edit; use Correct or Void.

---

## Concurrency

- Pre-check current grade + unique partial index
- Repository maps unique violations → `CurrentGradeAlreadyExistsException` (`409`)
- Correct uses row lock + uniqueness

---

## Tests

| Category | Count (executed) | Result |
|----------|------------------|--------|
| Unit (rules/status) | 6 | PASS |
| Application/handler (mocked) | 9 | PASS |
| API (feature) | 6 | PASS |
| Security (authz/cross-school) | 3 | PASS |
| Concurrency | 1 | PASS |
| Architecture (Exams filter + thin controller) | included | PASS |
| PostgreSQL 3A/3B foundation (sis_test) | 15 | PASS |
| Regression Enrollment + Phase3A/3B schema | 22 passed (2 skipped) | PASS |

Phase 3B.1-focused new suite (rules+handlers+API+security+concurrency+architecture filter): **26 passed**.

---

## Architecture Validation

```text
php artisan architecture:validate --fitness
→ Architecture validation passed.
```

```text
php artisan architecture:feature-check Exams
→ Feature contract validation passed.
```

---

## Security Validation

```text
php artisan security:validate
→ Security validation passed.
```

---

## Database Verification

```text
php artisan sis:verify-database --no-seed-check
→ Checks passed 44/44 — Database foundation verification passed.
```

Database changes in this phase: **NONE**.

---

## Production Readiness

**CONDITIONAL**

### Implementation readiness

PASS — application layer complete, validators green, tests green within approved scope.

### Production deployment readiness

CONDITIONAL on operational evidence not claimed here:

- Academic years present in target environment
- Required `student_grades_ay_{id}` partitions created via `StudentGradesPartitionManager` (no DEFAULT partition)
- Roles/permissions seeded for operators
- API smoke tests in staging
- Outbox processor health
- Partition creation runbook
- Load/concurrency smoke
- Monitoring

Live `sis` was not wiped; PostgreSQL integration tests used `sis_test` via `phpunit.database-pgsql.xml`.

---

## Risks

1. Idempotency store remains post-commit (shared Enrollment pattern) — rare duplicate-key race still defended by DB unique index.
2. Environments with zero academic years have zero year partitions — writes fail clear (`grades.partition_missing`).
3. Teacher role cannot correct/void/finalize by design — may need future fine-grained subject ownership.
4. Correction reason is audit/outbox-only (no DB `reason` column) — intentional for 3B.1.

---

## Deferred Items

- Submitted workflow
- Bulk import
- GPA / ranking
- Transcript / term / annual result generation (Phase 3C)
- Autosave
- Separate `grades.correct_finalized` permission
- Moving idempotency inside the DB transaction
- UI
- Phase 3C consumers of grade outbox events

---

## Final Gate

```text
PASS WITH CONDITIONS
```

Conditions: production deployment readiness items above; Phase 3C explicitly not started.

---

## SIS CHANGE REPORT

Status: PASS WITH CONDITIONS  
Risk: MEDIUM  

Summary: Phase 3B.1 grade application hardening (CQRS/API/security/tests) over existing `exams.student_grades`; no DDL.  
Scope: Exams grades application layer only  

Application Code Modified: YES  
Database Modified: NO  
API Modified: YES  
UI Modified: NO  
Dependencies Modified: NO  
Governance Modified: YES (exams security/feature docs; security config permissions)

Database: NONE  
API: `/api/v1/grades` enter/show/correct/void/finalize + list helpers; no DELETE  
UI: NONE  

Security: permissions + GradePolicy + SchoolContext + RLS unchanged  
Authorization: `grades.*`  

Tests: see table above  
Validation: architecture fitness PASS; feature-check Exams PASS; security:validate PASS; sis:verify-database PASS  

Regression: LOW (Enrollment audit enum restored; Phase3A assertion updated for post-3B schema)  
Technical Debt: post-commit idempotency edge case (shared)  
Remaining Issues: production partition/role/smoke evidence  
Known Risks: see Risks  

Human Approval Required: YES — before Phase 3C only  
Recommended Next Step: Human-approved Phase 3C audit/plan when ready; do not auto-start  
Final Gate Status: PASS WITH CONDITIONS
