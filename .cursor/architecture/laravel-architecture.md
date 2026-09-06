# Laravel Application Architecture

> **Authoritative stack:** [ARCHITECTURE-STACK.md](./ARCHITECTURE-STACK.md) — Clean + DDD + CQRS + enforcement  
> **Target:** Thin controllers, testable use cases, domain independent of Laravel  
> **Enforcement:** `.cursor/rules/clean-architecture.mdc` + `php artisan architecture:validate`

## Layer Diagram

```
┌─────────────────────────────────────────┐
│  HTTP: Controllers + Form Requests      │
├─────────────────────────────────────────┤
│  Authorization: Policies + Middleware   │
├─────────────────────────────────────────┤
│  Application: Services + Actions        │
├─────────────────────────────────────────┤
│  Domain: Models + Enums + Value Objects │
├─────────────────────────────────────────┤
│  Infrastructure: Repositories (optional)│
├─────────────────────────────────────────┤
│  Async: Jobs + Events + Listeners       │
└─────────────────────────────────────────┘
           ↓
      PostgreSQL + Redis + Queue
```

---

## Directory Structure

```
app/
├── Actions/              # Single-purpose invokable classes
├── Console/Commands/
├── Data/                 # DTOs, readonly data objects
├── Enums/                # StudentStatus, AttendanceStatus
├── Events/
├── Exceptions/           # Domain exceptions
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   ├── Attendance/
│   │   ├── Enrollment/
│   │   └── Reports/
│   ├── Middleware/       # SchoolContext, CorrelationId
│   └── Requests/
├── Jobs/
├── Listeners/
├── Models/
│   ├── Organization/
│   ├── Students/
│   ├── Enrollment/
│   └── Attendance/
├── Observers/            # Cache invalidation
├── Policies/
├── Providers/
└── Services/
    ├── Attendance/
    ├── Enrollment/
    ├── Reports/
    └── Audit/
```

---

## Layer Responsibilities

| Layer | Does | Does NOT |
|-------|------|----------|
| **Controller** | Authorize, validate input, call service, return response | Business rules, raw SQL |
| **Form Request** | Input validation, authorization hook | DB writes |
| **Service** | Business logic, transactions, orchestration | HTTP concerns |
| **Model** | Relationships, casts, scopes | Complex multi-entity workflows |
| **Job** | Async heavy work, retries | User-facing validation |
| **Policy** | Authorization rules | Data fetching |
| **Observer** | Cache invalidation, audit triggers | Business decisions |

---

## Service Pattern

```php
namespace App\Services\Attendance;

final class AttendanceBatchService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function recordSectionAttendance(/* ... */): int
    {
        return DB::transaction(function () use (/* ... */) {
            // batch insert
            // refresh daily_summary
            // audit log
            return $inserted;
        });
    }
}
```

---

## CQRS Lite (See normalization-and-cqrs.md)

| Side | Location | Examples |
|------|----------|----------|
| **Commands** (writes) | `App\Actions\` or `Services\` | EnrollStudent, RecordAttendance |
| **Queries** (reads) | `App\Queries\` or dedicated read services | GetSchoolDashboard, SearchStudents |

Do **not** use same fat service for both without clear separation.

```php
// Command
app(EnrollStudentAction::class)->execute($dto);

// Query
app(SchoolDashboardQuery::class)->get($schoolId, $yearId);
```

---

## Transactions

```php
// ✅ Multi-table academic operations in transaction
DB::transaction(function () {
    $enrollment = Enrollment::create([...]);
    EnrollmentSubject::insert($subjects);
    AuditService::log('created', $enrollment, ...);
});

// ❌ Transaction across HTTP + Queue — use job instead
```

---

## Events & Listeners

| Event | Listener |
|-------|----------|
| AttendanceBatchRecorded | RefreshDashboardCache |
| StudentTransferred | NotifyGuardian, InvalidateCache |
| GradesPublished | GenerateTermResults (queue) |

Keep listeners thin — delegate to services.

---

## Exception Handling

```php
// Domain exceptions
throw new EnrollmentAlreadyExistsException($studentId, $yearId);

// Handler maps to user-friendly Inertia error
```

Never expose PostgreSQL error text to users.

---

## Logging

```php
Log::info('Attendance batch recorded', [
    'correlation_id' => request()->header('X-Correlation-ID'),
    'section_id' => $sectionId,
    'count' => $inserted,
]);
```

---

## Caching in Application Layer

```php
// Reference data only — see cache-invalidation.md
Cache::remember("org:schools:{$directorateId}", 3600, fn () => ...);
```

Invalidate via Observers on model update.

---

## Anti-Patterns (Forbidden)

| ❌ | ✅ |
|----|-----|
| Fat controller | Thin controller + service |
| Model in Blade/React directly | DTO / Inertia props |
| N+1 in loops | Eager load |
| Sync 1000+ records | Queue job |
| Business logic in migration | Service layer |
| `$guarded = []` on academic models | Explicit `$fillable` |

---

## Related

- [api-conventions.md](./api-conventions.md)
- [normalization-and-cqrs.md](./normalization-and-cqrs.md)
- [rules/laravel-patterns.mdc](../rules/laravel-patterns.mdc)
