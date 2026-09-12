# PHASE TV — U11 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر» + explicit Schedule-exception list HTTP
Selected: TV-U11 Schedule-exception staff JSON HTTP readers
Rejected for now: Student portal · Phase 8
Status: GRANTED
```

## Scope (IN)

```text
Application Queries: ListScheduleExceptions / GetScheduleException
Repo read methods on ScheduleExceptionRepository
GET /api/v1/timetable/schedules/{schedule}/exceptions
GET /api/v1/timetable/schedule-exceptions/{exception}
GET /api/v1/timetable/schedule-exceptions (optional filters)
AuthZ: timetable.view
Pagination max 100
PG feature tests
```

## Scope (OUT)

```text
Exception soft-delete HTTP
Student portal · Phase 8
```
