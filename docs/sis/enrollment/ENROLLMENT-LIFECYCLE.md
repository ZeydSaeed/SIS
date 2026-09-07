# Enrollment Module — Lifecycle

**Date:** 2026-09-07  
**Source:** Code + migrations + `.cursor/brain/student-lifecycle.md` (documented intent)  
**Rule:** States below are **extracted from implementation**; documented-only states are marked.

---

## Domain Model (Current)

```text
Student (students.students)
   |
   +---- Enrollment (enrollment.enrollments) — per Academic Year
            |
            +---- School (organization.schools) — school_id
            |
            +---- AcademicYear (academic.academic_years)
            |
            +---- Class (enrollment.classes) — class_id
            |
            +---- Section (enrollment.sections) — section_id
            |
            +---- Specialization (vocational.specializations) — optional
            |
            +---- Status (SMALLINT) + effective_from / effective_to
            |
            +---- enrolled_by (users) — audit actor
```

### Cardinality (implemented / enforced)

| Relationship | Cardinality | Enforcement |
|--------------|-------------|-------------|
| Student → Enrollment per year | 1 active per academic year | `hasActiveEnrollment()` in repository (status=1, effective_to NULL) — **app layer only** |
| Enrollment → School | N:1 | FK + RLS on PostgreSQL |
| Class → School + Year | N:1 each | FK on `enrollment.classes` |
| Section → Class | N:1 | FK |
| Enrollment → Class + Section | N:1 each | FK — **no cross-school validation in handler** |

### Ownership

- **Tenant owner:** `enrollment.enrollments.school_id`
- **Temporal anchor:** `academic_year_id` + `effective_from` / `effective_to`
- **Actor:** `enrolled_by` (nullable in DB; passed from command)

---

## States (Extracted from Code)

### Student eligibility (pre-enrollment)

**Source:** `app/Domain/Student/ValueObjects/StudentStatus.php`

| Value | Name | Can enroll? |
|-------|------|-------------|
| 0 | Inactive | No |
| 1 | Active | **Yes** (`canEnroll()`) |
| 2 | Suspended | No |
| 3 | Graduated | No |
| 4 | Withdrawn | No |

**Enforced by:** `EligibleForEnrollmentSpecification` → `StudentInactiveException`

### Enrollment record status

**Source:** `EloquentEnrollmentRepository::ACTIVE_STATUS = 1`

| Value | Meaning in code | Evidence |
|-------|-----------------|----------|
| **1** | Active enrollment | Default on create; `hasActiveEnrollment` checks status=1 AND effective_to IS NULL |
| Other | **Not implemented** | No enum, no commands to transition |

**Repository active check** (`EloquentEnrollmentRepository.php:13-20`):

```text
status = 1 AND effective_to IS NULL
```

### Classes / Sections / Enrollment subjects

| Table | status default | Lifecycle logic |
|-------|----------------|-----------------|
| `enrollment.classes` | 1 | No application transitions |
| `enrollment.sections` | 1 | No application transitions |
| `enrollment.enrollment_subjects` | 1 | No application code |

---

## Documented Intent (NOT implemented in code)

From `.cursor/brain/student-lifecycle.md`:

```text
status: ACTIVE | INACTIVE | GRADUATED | TRANSFERRED | WITHDRAWN
effective_from / effective_to / archived_at
```

**Gap:** No PHP `EnrollmentStatus` enum, no withdraw/transfer/complete commands, no `archived_at` column.

---

## Lifecycle Flow (Implemented Today)

```text
[Student exists, status=Active]
        |
        v
EnrollStudentCommand (no HTTP entry point)
        |
        +-- Student not found --> StudentNotFoundException
        +-- Student not eligible --> StudentInactiveException
        +-- Active enrollment exists for year --> StudentAlreadyEnrolledException
        |
        v
Create enrollment (status=1, effective_from=command, effective_to=NULL)
        |
        v
Stage StudentEnrolled --> Outbox --> BridgeEvent --> Log::info audit
```

**Only create path exists.** No update, cancel, approve, or transfer paths.

---

## Allowed / Forbidden Transitions (As-Is)

| From | To | Allowed? | Mechanism |
|------|-----|----------|-----------|
| (none) | Active (status=1) | **Yes** | `EnrollStudentHandler` |
| Active | Closed (effective_to set) | **No command** | — |
| Active | Transferred | **Not implemented** | Blueprint: `transfers.*` |
| Active | Withdrawn | **Not implemented** | — |
| Pending → Approved → Active | **Not implemented** | No workflow states |

---

## Side Effects (Implemented)

| Trigger | Side effect | Async |
|---------|-------------|-------|
| EnrollStudent success | Insert `enrollment.enrollments` | No |
| EnrollStudent success | Stage `StudentEnrolled` in outbox | Processed by `ProcessOutboxJob` |
| Outbox processed | `RecordStudentEnrolledAudit` → log channel | Yes (queue) |

**Not implemented:** Security audit DB record, cache invalidation, attendance auto-provisioning.

---

## Audit Requirements (Current vs Required)

| Field | Current | Target (Security Contract) |
|-------|---------|----------------------------|
| Who | `enrolled_by` in DB (client-supplied in command) | Trusted server actor |
| What | `StudentEnrolled` event | + SecurityAuditLogger |
| When | `created_at`, `occurredAt` | Server-controlled |
| Target | enrollment_id, student_id | Structured |
| School | school_id in event | Trusted context |
| Before/After | Not captured | Required for mutations |

---

## Referential Integrity Notes

- Handler accepts `schoolId`, `classId`, `sectionId` from command **without validating**:
  - Student belongs to school
  - Class belongs to school + year
  - Section belongs to class
- FK constraints enforce existence only, not cross-tenant consistency at application layer.
