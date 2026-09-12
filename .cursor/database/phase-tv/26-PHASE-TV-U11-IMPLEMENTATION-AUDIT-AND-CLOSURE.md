# PHASE TV — TV-U11
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: TV-U11 — Schedule-exception staff JSON HTTP readers
AuthZ: 25 GRANTED («استمر» + explicit path)
Audit: PASS
Closure: CLOSED / ACCEPTED
Date: 2026-09-12
```

## Delivered

```text
Queries: ListScheduleExceptions / GetScheduleException
Repo: findById + listForSchool (schedule_id / date range filters)
Routes:
  GET /api/v1/timetable/schedules/{schedule}/exceptions
  GET /api/v1/timetable/schedule-exceptions
  GET /api/v1/timetable/schedule-exceptions/{exception}
AuthZ: timetable.view
Pagination max 100
```

## Validation

```text
architecture:validate --fitness → PASS
security:validate → PASS
PhaseTvScheduleExceptionReadHttpApiPostgreSqlTest → 3 passed / 12 assertions
```

```text
TV-U11: CLOSED / ACCEPTED
Schedule exceptions now have staff list/show HTTP alongside writers.
```
