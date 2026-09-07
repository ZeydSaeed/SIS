# Phase 3.3 — Students Vertical Slice

**Phase:** 3.3  
**Date:** 2026-09-07  
**Previous:** Phase 3.2 Database Foundation  
**Status:** Complete — first business API vertical slice

---

## Executive Summary

Phase 3.3 delivers the **first SIS business feature**: Students CRUD + List/Search over JSON API, following Clean Architecture + CQRS. Controllers delegate to Application handlers; persistence uses existing `students.students` schema; domain events stage via Outbox.

**Auth:** Not applied to API yet (Phase 3.4+ / Security module). Endpoints are open for local integration testing.

---

## API Endpoints

| Method | Route | Handler | Description |
|--------|-------|---------|-------------|
| GET | `/api/v1/students` | `ListStudentsHandler` | Paginated list (`status`, `page`, `per_page`) |
| GET | `/api/v1/students/search` | `SearchStudentsHandler` | Search by name/code/national ID (`q`) |
| GET | `/api/v1/students/{id}` | `GetStudentHandler` | Student detail |
| POST | `/api/v1/students` | `CreateStudentHandler` | Create student |
| PUT/PATCH | `/api/v1/students/{id}` | `UpdateStudentHandler` | Update profile |

Headers: `X-Correlation-ID`, `X-Idempotency-Key` (create only).

---

## Architecture

```text
StudentController (thin)
    → Command/Query + Handler
        → StudentRepositoryInterface (write)
        → StudentReadRepositoryInterface (read — Application/Student)
            → EloquentStudentRepository / EloquentStudentManagementReadRepository
                → students.students (PostgreSQL schema)
    → Outbox: StudentRegistered / StudentProfileUpdated
```

Enrollment continues using `Domain\Enrollment\Repositories\StudentReadRepositoryInterface` → delegates to `StudentRepositoryInterface`.

---

## Domain Events

| Event | Trigger |
|-------|---------|
| `StudentRegistered` | After successful create |
| `StudentProfileUpdated` | After successful update |

Staged via `OutboxRepository` inside transaction — not direct `event()`.

---

## Validation

**Create / Update** (Form Request):
- `first_name`, `last_name` — required, max 100
- `gender` — 1 or 2
- `birth_date` — required, before today
- `student_code` — optional on create (auto `STU-{sequence}` if omitted)
- `national_id` — optional, unique

**Domain errors** → JSON 404/422 via `SisDomainException` renderer.

---

## Example

```bash
curl -X POST http://sis.test/api/v1/students \
  -H "Content-Type: application/json" \
  -H "X-Correlation-ID: REQ-STU-001" \
  -d '{
    "first_name": "Ali",
    "last_name": "Karim",
    "gender": 1,
    "birth_date": "2010-05-15"
  }'

curl "http://sis.test/api/v1/students/search?q=Ali"
```

---

## Tests

```bash
php artisan test --filter=StudentApi
php artisan test --filter=CreateStudentHandler
php artisan architecture:validate --fitness
```

---

## Next: Phase 3.4 — Application Observability

- Real HTTP request metrics (latency, query count)
- Wire into Intelligence telemetry collectors
- Performance budget validation for `student_search` workload

---

*Phase 3.3 complete. Students API ready for frontend integration and observability wiring.*
