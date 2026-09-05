# API & Inertia Conventions

> **Stack:** Laravel 13 + Inertia.js 3 + React 19  
> **Scope:** All HTTP endpoints and Inertia page props

## Architecture Flow

```
HTTP Request
     ↓
Middleware (auth, school context, correlation ID)
     ↓
Controller (thin — authorize + delegate)
     ↓
Form Request (validation)
     ↓
Service / Action (business logic)
     ↓
Repository or Eloquent (data access)
     ↓
PostgreSQL
```

**Forbidden:**

```
Controller → 500 lines business logic → 20 queries
```

---

## Route Naming

```
{module}.{resource}.{action}

Examples:
enrollment.students.index
enrollment.students.show
attendance.sections.batch-store
reports.school.dashboard
admin.schools.update
```

---

## Controller Rules

```php
// ✅ Thin controller
public function batchStore(
    AttendanceBatchRequest $request,
    Section $section,
    AttendanceBatchService $service,
): RedirectResponse {
    $this->authorize('recordAttendance', $section);

    $count = $service->recordSectionAttendance(
        sessionId: $request->validated('session_id'),
        records: $request->validated('records'),
        recordedBy: $request->user()->id,
    );

    return back()->with('success', __('attendance.saved', ['count' => $count]));
}
```

---

## Response Patterns

| Type | Use |
|------|-----|
| Inertia::render | Page responses |
| redirect()->back() | Form submissions |
| 202 + queue | Heavy operations (bulk import) |
| JSON API | Mobile/external integrations only |

---

## Inertia Shared Props

```php
// HandleInertiaRequests
'auth' => ['user', 'permissions', 'school', 'role'],
'flash' => ['success', 'error'],
'academicYear' => fn () => Cache::remember(...),
'locale' => app()->getLocale(),
'rtl' => app()->getLocale() === 'ar',
```

---

## Pagination

```php
// ✅ Cursor/keyset for large lists (students 45K)
Enrollment::forSchool($schoolId)
    ->forAcademicYear($yearId)
    ->cursorPaginate(50);

// ❌ Avoid offset on huge tables
Student::paginate(50); // page 900 = slow OFFSET
```

---

## Error Handling

| Code | Use |
|------|-----|
| 403 | Policy denied |
| 404 | Resource not in user's school scope |
| 422 | Validation (Form Request) |
| 429 | Rate limit (attendance peak) |
| 500 | Logged with correlation_id — generic user message |

```php
// Never expose SQL errors to user
```

---

## Idempotency (Write APIs)

```php
// Header: Idempotency-Key: cert-{school}-{year}-{batch}
// Store in job record — reject duplicate within 24h
```

Required for: payments, bulk import, certificate generation, mass notifications.

---

## Correlation ID

Every request:

```
X-Correlation-ID: REQ-2026-000123
```

Middleware logs + passes to audit + queue jobs.

---

## Module URL Structure

```
/dashboard
/students
/students/{student}
/enrollment/sections/{section}/attendance
/exams/sessions/{session}/grades
/reports/school
/reports/directorate
/admin/settings
```

---

## API Versioning (External)

If ministry integration added later:

```
/api/v1/students/{public_id}
/api/v1/certificates/verify/{verification_code}
```

Use `public_id` UUID — never internal BIGINT in public APIs.

---

## Related

- [laravel-architecture.md](./laravel-architecture.md)
- [rules/laravel-patterns.mdc](../rules/laravel-patterns.mdc)
- [rules/react-inertia.mdc](../rules/react-inertia.mdc)
