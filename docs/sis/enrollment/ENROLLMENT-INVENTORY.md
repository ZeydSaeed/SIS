# Enrollment Module — Inventory

**Date:** 2026-09-07  
**Phase:** Enrollment Discovery & Baseline Audit  
**Status:** READ-ONLY AUDIT — no code modified

---

## Executive Summary

Enrollment is a **partial backend vertical slice**: one write command (`EnrollStudent`), four migrated PostgreSQL tables, RLS on `enrollment.enrollments` only, and **no HTTP API, policy, permissions, frontend, or read queries**. Admission and Transfer exist in blueprint only.

---

## 4.1 Backend

### Controllers
| File | Status |
|------|--------|
| — | **None** — no `EnrollmentController` or API routes |

### Application Layer

| File | Type | Purpose |
|------|------|---------|
| `app/Application/Enrollment/Commands/EnrollStudentCommand.php` | Command | Write DTO: school, year, student, class, section, dates |
| `app/Application/Enrollment/Commands/EnrollStudentHandler.php` | Handler | Idempotent enroll + UoW + outbox |
| `app/Application/Enrollment/Results/EnrollStudentResult.php` | Result | Success / idempotency replay |
| `app/Application/Enrollment/DTOs/EnrollmentDTO.php` | DTO | Read DTO — **unused** (no queries) |
| `app/Application/Enrollment/DTOs/StudentSummaryDTO.php` | DTO | Read DTO — **unused** |

**Missing:** `Application/Enrollment/Queries/*` (list, show, search, cancel, transfer)

### Domain Layer

| File | Type | Purpose |
|------|------|---------|
| `app/Domain/Enrollment/Data/CreateEnrollmentData.php` | Data | Persistence input VO |
| `app/Domain/Enrollment/Events/StudentEnrolled.php` | Domain event | Outbox payload |
| `app/Domain/Enrollment/Exceptions/StudentAlreadyEnrolledException.php` | Exception | Duplicate per academic year |
| `app/Domain/Enrollment/Exceptions/StudentInactiveException.php` | Exception | Eligibility failure |
| `app/Domain/Enrollment/Repositories/EnrollmentRepositoryInterface.php` | Port | Write repository |
| `app/Domain/Enrollment/Repositories/StudentReadRepositoryInterface.php` | Port | Cross-context student read |
| `app/Domain/Enrollment/Specifications/EligibleForEnrollmentSpecification.php` | Specification | Student must be Active |

**Missing:** Domain `Enrollment` entity, `EnrollmentStatus` enum, withdraw/transfer commands

### Infrastructure Layer

| File | Type | Purpose |
|------|------|---------|
| `app/Infrastructure/Persistence/Enrollment/EloquentEnrollmentRepository.php` | Repository | save, hasActiveEnrollment, generateEnrollmentNumber |
| `app/Infrastructure/Persistence/Eloquent/EnrollmentRecord.php` | Eloquent | `enrollment.enrollments` table |
| `app/Infrastructure/Persistence/Student/EloquentStudentReadRepository.php` | Adapter | Enrollment's student read port |
| `app/Infrastructure/Events/StudentEnrolledBridgeEvent.php` | Bridge event | Laravel event from outbox |
| `app/Infrastructure/Jobs/ProcessOutboxJob.php` | Job | Dispatches bridge events (lines 30–32) |
| `app/Infrastructure/Persistence/Outbox/EloquentOutboxRepository.php` | Outbox | Rehydrates `StudentEnrolled` |

**Missing:** `ClassRecord`, `SectionRecord`, `EnrollmentSubjectRecord`, read repositories

### Policies / Authorization

| File | Status |
|------|--------|
| — | **No `EnrollmentPolicy`** |
| `app/Security/Policies/StudentPolicy.php` | Students only — not enrollment |
| `config/security.php` | No `enrollment.*` permissions |

### Middleware (shared, not enrollment-specific)

| File | Wired to enrollment? |
|------|---------------------|
| `app/Security/Middleware/SchoolContextMiddleware.php` | No routes |
| `app/Security/Middleware/RequireSchoolContextMiddleware.php` | No routes |
| `auth:sanctum` | No enrollment routes |

### Events / Listeners / Jobs

| File | Purpose |
|------|---------|
| `app/Domain/Enrollment/Events/StudentEnrolled.php` | Domain event |
| `app/Infrastructure/Events/StudentEnrolledBridgeEvent.php` | Infrastructure bridge |
| `app/Listeners/Enrollment/RecordStudentEnrolledAudit.php` | `Log::info` only — **not** `SecurityAuditLogger` |
| `app/Infrastructure/Jobs/ProcessOutboxJob.php` | Async outbox processing |

Registered in `app/Providers/ArchitectureServiceProvider.php` (DI + listener).

### Form Requests / Validators
| Status |
|--------|
| **None** |

### Routes

| File | Enrollment routes |
|------|-------------------|
| `routes/api.php` | **None** |
| `routes/web.php` | **None** |

Hypothetical routes documented in `.cursor/architecture/api-conventions.md` only.

### Providers

| File | Enrollment bindings |
|------|---------------------|
| `app/Providers/ArchitectureServiceProvider.php` | Repository ports, outbox listener |
| `app/Providers/SecurityServiceProvider.php` | No enrollment policy |

### Cross-cutting references

| File | Reference |
|------|-----------|
| `app/Services/Attendance/AttendanceBatchService.php` | Writes `enrollment_id` to attendance |
| `app/Database/DatabaseFoundationVerifier.php` | Requires `enrollment.enrollments` |
| `app/Intelligence/Guardian/SchemaGuardian.php` | FK integrity on enrollments |
| `app/Console/Commands/ArchitectureFeatureCheckCommand.php` | `architecture:feature-check Enrollment` |

### Frontend (Inertia/React)

| Status |
|--------|
| **None** — zero matches under `resources/js/` |

---

## 4.2 Database

**Schema:** `enrollment` (4 tables migrated)  
**Migrations:** `database/migrations/2026_09_05_100500_create_enrollment_tables.php`  
**FK patches:** `100600` (specialization, subject), `100700` (homeroom teacher)  
**RLS:** `100900` + fail-closed fix `2026_09_07_120100_fix_rls_fail_closed.php`  
**Blueprint:** `.cursor/architecture/database-blueprint.md` lines 416–487

| Table | Purpose | PK | school_id | Constraints | Used By |
|-------|---------|----|-----------|-------------|---------|
| `enrollment.classes` | Class within school + year + grade | `id` BIGINT | **Yes** FK → `organization.schools` | UNIQUE(school_id, academic_year_id, code); INDEX(school_id, academic_year_id) | Enrollments (class_id) |
| `enrollment.sections` | Section within class | `id` BIGINT | **Indirect** via class_id | UNIQUE(class_id, code); homeroom_teacher_id nullable | Enrollments (section_id) |
| `enrollment.enrollments` | Student enrollment per academic year | `id` BIGINT | **Yes** FK → schools | UNIQUE(enrollment_number); INDEX(student_id); INDEX(school_id, academic_year_id); INDEX(student_id, academic_year_id) | Attendance, exams, reports MV |
| `enrollment.enrollment_subjects` | Subject assignments per enrollment | `id` BIGINT | **Indirect** via enrollment | UNIQUE(enrollment_id, subject_id) | Curriculum linkage |

### `enrollment.enrollments` — column detail

| Column | Type | Nullable | Tenant / Audit | Notes |
|--------|------|----------|----------------|-------|
| id | BIGINT | NO | — | PK |
| student_id | BIGINT | NO | — | FK → students.students |
| academic_year_id | BIGINT | NO | — | FK → academic.academic_years |
| school_id | BIGINT | NO | **Tenant** | FK → organization.schools |
| class_id | BIGINT | NO | — | FK → enrollment.classes |
| section_id | BIGINT | NO | — | FK → enrollment.sections |
| specialization_id | BIGINT | YES | — | FK → vocational.specializations |
| enrollment_number | VARCHAR(50) | NO | — | UNIQUE |
| status | SMALLINT | NO | Lifecycle | Default **1** (active in code) |
| effective_from | DATE | NO | Temporal | |
| effective_to | DATE | YES | Temporal | NULL = active |
| enrolled_by | BIGINT | YES | **Audit** | FK → users |
| created_at, updated_at | TIMESTAMPTZ | NO | Audit | |

**Soft delete:** None — lifecycle uses `status` + `effective_to` per project rules.

**Blueprint gap:** Partial unique `(student_id, academic_year_id) WHERE status = 1` documented in blueprint/indexing-matrix but **not migrated**.

### Related blueprint-only (not migrated)

| Schema | Tables | Status |
|--------|--------|--------|
| `admission` | applications, application_periods, documents | Blueprint only |
| `transfers` | transfer_requests, transfer_records | Schema in SchemaHelper; no migrations |

---

## Tests Inventory

| File | Level | Count | Coverage |
|------|-------|-------|----------|
| `tests/Unit/Enrollment/EnrollStudentHandlerTest.php` | Unit | 5 | Happy path, idempotency, inactive, duplicate, not found (mocked) |
| `tests/Unit/Enrollment/EligibleForEnrollmentSpecificationTest.php` | Unit | 2 | Active vs suspended |
| `tests/Architecture/ArchitectureHardeningTest.php` | Architecture | 1 | `architecture:feature-check Enrollment` |
| `tests/Feature/Security/PostgreSqlRlsFailClosedTest.php` | Security | 2 | RLS on enrollments (PG only, skipped SQLite) |

**Missing:** Feature/API tests, integration DB tests, enrollment security matrix, cross-school tests, policy tests.

---

## Search Terms — Coverage

| Term | Matches |
|------|---------|
| Enrollment / EnrollStudent | Core module |
| Registration | Laravel user auth only — **not academic** |
| Admission | Blueprint/docs only |
| Transfer | Blueprint/docs only |
| SchoolAssignment | **Zero matches** |
| StudentRegistration | **Zero matches** |

---

## Dependency Graph (implemented)

```text
EnrollStudentCommand
    → EnrollStudentHandler
        → StudentReadRepositoryInterface (Student context)
        → EligibleForEnrollmentSpecification
        → EnrollmentRepositoryInterface
        → UnitOfWork + Outbox + IdempotencyStore
        → StudentEnrolled (domain event)
            → ProcessOutboxJob
                → StudentEnrolledBridgeEvent
                    → RecordStudentEnrolledAudit (Log::info)
```

**Not wired:** Handler has no HTTP/CLI/job caller.
