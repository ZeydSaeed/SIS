# PHASE TV — TV-U08
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: TV-U08 — Timetable staff JSON HTTP writers
AuthZ: 19 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED / ACCEPTED
Date: 2026-09-12
```

## Delivered

```text
Permissions: timetable.schedule.{create,update,cancel}, timetable.exception.{create,update}
Role: timetable_manager
Policy: TimetablePolicy + TimetableSchoolAccessService
Gate: ScheduleRecord + ScheduleExceptionRecord
Thin Api\ScheduleController → existing Application handlers
Routes:
  POST   /api/v1/timetable/schedules
  PATCH  /api/v1/timetable/schedules/{schedule}
  POST   /api/v1/timetable/schedules/{schedule}/cancel
  POST   /api/v1/timetable/schedules/{schedule}/exceptions
  PATCH  /api/v1/timetable/schedule-exceptions/{exception}
X-School-Id + required X-Idempotency-Key
Audit: SEC_TIMETABLE_DATA_MODIFIED
```

## Validation

```text
architecture:validate --fitness → PASS
PhaseTvScheduleHttpApiPostgreSqlTest → 3 passed / 32 assertions
```

## Out of scope (still deferred)

```text
Timetable read/list HTTP · Vocational HTTP · Results HTTP · Student portal · Phase 8
```

```text
TV-U08: CLOSED / ACCEPTED
Timetable Application writers now have staff JSON API surface.
```
