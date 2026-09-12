# PHASE TV — U08 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: Timetable staff JSON HTTP writers (TV-U08)
Rejected for now: Results HTTP · Student portal · Phase 8
Status: GRANTED
```

## Why this path

```text
- Application writers (U04 + U07) exist; product surface still missing
- Mirrors Attendance/Grades HTTP AuthZ pattern
- Still no Phase 8; no Results student portal
```

## Scope (IN)

```text
Permissions + TimetablePolicy + school access
FormRequests + thin Api\ScheduleController
Routes under /api/v1/timetable/*
Wire: Create/Update/Cancel Schedule
Wire: Create/Update ScheduleException
X-School-Id + X-Idempotency-Key
Feature PostgreSQL API tests + AuthZ deny
```

## Scope (OUT)

```text
Timetable read queries / list UI
Vocational HTTP
Results HTTP
Hard delete
Phase 8
```
